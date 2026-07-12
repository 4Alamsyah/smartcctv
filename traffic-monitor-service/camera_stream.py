"""Threaded RTSP reader.

cv2.VideoCapture buffers frames internally; if the processing loop is slower
than the stream's frame rate (true for any YOLO inference loop vs. a 25fps
RTSP feed), naively calling .read() in the main loop falls further and
further behind real time. This reader runs in its own thread, continuously
discards old frames, and always exposes only the latest one via `read()`.
"""

from __future__ import annotations

import logging
import threading
import time

import cv2

logger = logging.getLogger(__name__)


class RtspStream:
    def __init__(self, rtsp_url: str, reconnect_delay_seconds: float = 3.0):
        self._rtsp_url = rtsp_url
        self._reconnect_delay = reconnect_delay_seconds
        self._capture: cv2.VideoCapture | None = None
        self._latest_frame = None
        self._lock = threading.Lock()
        self._stop_event = threading.Event()
        self._thread = threading.Thread(target=self._run, daemon=True)

    def start(self) -> "RtspStream":
        self._open()
        self._thread.start()
        return self

    def stop(self) -> None:
        self._stop_event.set()
        self._thread.join(timeout=5)
        if self._capture is not None:
            self._capture.release()

    def read(self):
        """Returns the most recently decoded frame, or None if not connected yet."""
        with self._lock:
            return None if self._latest_frame is None else self._latest_frame.copy()

    def _open(self) -> None:
        self._capture = cv2.VideoCapture(self._rtsp_url, cv2.CAP_FFMPEG)
        self._capture.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        if not self._capture.isOpened():
            logger.warning("Failed to open RTSP stream: %s", self._rtsp_url)

    def _run(self) -> None:
        while not self._stop_event.is_set():
            if self._capture is None or not self._capture.isOpened():
                logger.info("Reconnecting RTSP stream in %.1fs...", self._reconnect_delay)
                time.sleep(self._reconnect_delay)
                self._open()
                continue

            ok, frame = self._capture.read()
            if not ok:
                logger.warning("RTSP read failed, forcing reconnect")
                self._capture.release()
                self._capture = None
                continue

            with self._lock:
                self._latest_frame = frame
