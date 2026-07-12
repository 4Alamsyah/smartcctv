"""Runs off the main detection loop, on a background thread pool, so a slow
Groq call or a Laravel network hiccup never stalls frame processing.

Flow per breached track: crop the vehicle region -> ask Groq for the plate
text -> resolve pixel coordinates to lng/lat -> POST multipart to Laravel.
"""

from __future__ import annotations

import logging
import uuid
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone

import cv2

from .config import Settings
from .detector import Detection
from .geo_mapper import GeoMapper
from .geofence import Geofence
from .groq_ocr import GroqAnprClient
from .laravel_client import LaravelClient

logger = logging.getLogger(__name__)


class ViolationPipeline:
    def __init__(
        self,
        settings: Settings,
        geo_mapper: GeoMapper,
        groq_client: GroqAnprClient,
        laravel_client: LaravelClient,
        geofence: Geofence,
        zone_type: str,
        max_workers: int = 4,
    ):
        self._settings = settings
        self._geo_mapper = geo_mapper
        self._groq_client = groq_client
        self._laravel_client = laravel_client
        self._geofence = geofence
        self._violation_type = "busway_lane_intrusion" if zone_type == "busway_lane" else "illegal_parking"
        self._executor = ThreadPoolExecutor(max_workers=max_workers, thread_name_prefix="violation")

    def submit(self, frame, detection: Detection, stationary_seconds: int) -> None:
        # Snapshot the frame now (main loop's buffer will be overwritten);
        # the background thread only ever touches this copy.
        frame_copy = frame.copy()
        self._executor.submit(self._process, frame_copy, detection, stationary_seconds)

    def shutdown(self) -> None:
        self._executor.shutdown(wait=True)

    def _process(self, frame, detection: Detection, stationary_seconds: int) -> None:
        try:
            lng, lat = self._geo_mapper.pixel_to_lnglat(*detection.center)

            if not self._geofence.contains(lng, lat):
                # Stationary, but outside the surveyed lane/no-parking polygon
                # -- e.g. a car parked on a side street just inside frame.
                # Skip before the Groq OCR call, which is the expensive part.
                logger.debug("track=%s stationary but outside geofence, skipping", detection.track_id)
                return

            plate_number, plate_confidence, plate_source = self._extract_plate(frame, detection)

            ok, jpeg_buffer = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
            if not ok:
                logger.error("Failed to encode evidence frame, dropping violation")
                return

            payload = {
                "event_uuid": str(uuid.uuid4()),
                "camera_code": self._settings.camera_code,
                "violation_type": self._violation_type,
                "plate_number": plate_number or "",
                "plate_confidence": plate_confidence,
                "plate_source": plate_source,
                "lat": lat,
                "lng": lng,
                "stationary_seconds": stationary_seconds,
                "threshold_seconds": self._settings.stationary_threshold_seconds,
                "detected_at": datetime.now(timezone.utc).isoformat(),
            }

            self._laravel_client.post_violation(payload, jpeg_buffer.tobytes())
            logger.info(
                "Violation reported: track=%s plate=%s(%s) type=%s",
                detection.track_id, plate_number, plate_source, self._violation_type,
            )
        except Exception:
            logger.exception("Violation pipeline failed for track_id=%s", detection.track_id)

    def _extract_plate(self, frame, detection: Detection) -> tuple[str | None, int, str]:
        x1, y1, x2, y2 = (int(v) for v in detection.bbox_xyxy)
        vehicle_crop = frame[max(y1, 0):y2, max(x1, 0):x2]

        if vehicle_crop.size == 0:
            return None, 0, "groq_fallback"

        plate_number, confidence = self._groq_client.read_plate(vehicle_crop)
        return plate_number, confidence, "groq_fallback"
