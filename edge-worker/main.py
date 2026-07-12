"""Entrypoint: continuously monitors one RTSP stream, detects+tracks vehicles,
measures stationary duration, and hands off threshold breaches to the
violation pipeline. Run one process per camera (see README for a
multi-camera supervisor pattern using this same module).
"""

from __future__ import annotations

import logging
import time

from app.camera_stream import RtspStream
from app.config import Settings
from app.detector import VehicleDetector
from app.geo_mapper import GeoMapper
from app.geofence import Geofence
from app.groq_ocr import GroqAnprClient
from app.laravel_client import LaravelClient
from app.stationary_tracker import StationaryTracker
from app.violation_pipeline import ViolationPipeline

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(name)s: %(message)s")
logger = logging.getLogger("edge_worker")


def main() -> None:
    settings = Settings.load()
    logger.info("Starting edge worker for camera=%s", settings.camera_code)

    laravel_client = LaravelClient(settings)

    # rtsp_url, zone_type, and the lane/no-parking polygon all live on the
    # camera's DB record, not in this process's env vars -- one edge worker
    # deployment per camera, but the camera's own config (editable from the
    # dashboard) decides what it watches and what counts as a violation.
    # LANE_GEOFENCE_OVERRIDE (.env) wins over Laravel's geofence when set,
    # for local testing without touching the DB.
    camera_config = laravel_client.fetch_camera_config()
    rtsp_url = camera_config["rtsp_url"]
    zone_type = camera_config["zone_type"]
    geofence_points = settings.lane_geofence_override or camera_config.get("lane_geofence")
    geofence = Geofence(geofence_points)
    logger.info(
        "Camera config: rtsp_url=%s zone_type=%s geofence=%s",
        rtsp_url, zone_type, "configured" if geofence.is_configured else "none (whole frame counts)",
    )

    stream = RtspStream(rtsp_url).start()
    detector = VehicleDetector(settings)
    tracker = StationaryTracker(
        movement_tolerance_px=settings.stationary_movement_tolerance_px,
        threshold_seconds=settings.stationary_threshold_seconds,
    )
    geo_mapper = GeoMapper()
    groq_client = GroqAnprClient(settings)
    pipeline = ViolationPipeline(
        settings=settings,
        geo_mapper=geo_mapper,
        groq_client=groq_client,
        laravel_client=laravel_client,
        geofence=geofence,
        zone_type=zone_type,
    )

    frames_seen = 0
    heartbeat_interval_seconds = 10.0
    last_heartbeat = time.monotonic()
    stream_confirmed = False

    try:
        while True:
            loop_start = time.monotonic()
            frame = stream.read()

            if frame is not None:
                if not stream_confirmed:
                    logger.info("First frame received -- stream is connected.")
                    stream_confirmed = True

                frames_seen += 1
                detections = detector.detect(frame)
                breaches = tracker.update(detections)

                for detection, stationary_seconds in breaches:
                    pipeline.submit(frame, detection, stationary_seconds)

            if loop_start - last_heartbeat >= heartbeat_interval_seconds:
                status = "connected" if stream_confirmed else "waiting for first frame..."
                logger.info("Heartbeat: %s, frames_processed=%d", status, frames_seen)
                last_heartbeat = loop_start

            elapsed = time.monotonic() - loop_start
            time.sleep(max(0.0, settings.frame_sample_interval_seconds - elapsed))
    except KeyboardInterrupt:
        logger.info("Shutting down edge worker...")
    finally:
        stream.stop()
        pipeline.shutdown()


if __name__ == "__main__":
    main()
