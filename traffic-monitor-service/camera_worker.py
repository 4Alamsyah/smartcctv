"""One YOLO detection loop per camera, shared across every browser tab
watching it -- multiple viewers of /camera/<code>/feed poll the same cached
annotated frame instead of each triggering their own inference pass.

Workers are started lazily (first request for a camera's feed) and kept
running afterwards; there's no explicit shutdown since this is a long-running
dev/demo service (see plan for the concurrency caveats at larger scale).
"""

from __future__ import annotations

import logging
import os
import threading
import time

import cv2

from camera_stream import RtspStream
from detector import Detection, VehicleDetector
from laravel_client import fetch_camera_config

logger = logging.getLogger("traffic_monitor.camera_worker")

_BOX_COLOR = (0, 220, 0)  # BGR: green


class CameraWorker:
    def __init__(self, code: str, rtsp_url: str, detection_interval_seconds: float):
        self.code = code
        self._detection_interval = detection_interval_seconds
        self._stream = RtspStream(rtsp_url).start()
        self._detector = VehicleDetector(
            model_path=os.environ.get("YOLO_MODEL_PATH", "yolov8n.pt"),
            confidence_threshold=float(os.environ.get("YOLO_CONFIDENCE_THRESHOLD", "0.45")),
            vehicle_classes=set(os.environ.get("VEHICLE_CLASSES", "car,motorcycle,bus,truck").split(",")),
        )
        self._latest_jpeg: bytes | None = None
        self._lock = threading.Lock()
        self._stop_event = threading.Event()
        self._thread = threading.Thread(target=self._run, daemon=True)
        self._thread.start()

    def latest_jpeg(self) -> bytes | None:
        with self._lock:
            return self._latest_jpeg

    def stop(self) -> None:
        self._stop_event.set()
        self._thread.join(timeout=5)
        self._stream.stop()

    def _run(self) -> None:
        while not self._stop_event.is_set():
            loop_start = time.monotonic()
            frame = self._stream.read()

            if frame is not None:
                detections = self._detector.detect(frame)
                _draw_detections(frame, detections)
                ok, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
                if ok:
                    with self._lock:
                        self._latest_jpeg = buf.tobytes()

            elapsed = time.monotonic() - loop_start
            time.sleep(max(0.0, self._detection_interval - elapsed))


def _draw_detections(frame, detections: list[Detection]) -> None:
    for d in detections:
        x1, y1, x2, y2 = (int(v) for v in d.bbox_xyxy)
        cv2.rectangle(frame, (x1, y1), (x2, y2), _BOX_COLOR, 2)
        label = f"{d.class_name} {d.confidence:.0%}"
        cv2.putText(frame, label, (x1, max(y1 - 8, 12)), cv2.FONT_HERSHEY_SIMPLEX, 0.5, _BOX_COLOR, 2)


_workers: dict[str, CameraWorker] = {}
_workers_lock = threading.Lock()


def get_or_start_worker(code: str) -> CameraWorker:
    with _workers_lock:
        worker = _workers.get(code)
        if worker is None:
            logger.info("Starting camera worker for %s", code)
            config = fetch_camera_config(code)
            detection_interval = float(os.environ.get("DETECTION_INTERVAL_SECONDS", "0.3"))
            worker = CameraWorker(code, config["rtsp_url"], detection_interval)
            _workers[code] = worker
        return worker
