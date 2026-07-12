"""Pushes a confirmed violation event to Laravel's ingestion endpoint.

Uses `requests` with a bounded retry/backoff: the edge worker must never
crash the whole detection loop because Laravel or the network hiccuped for a
few seconds, but it also must not retry forever and build an unbounded queue
of stale events.
"""

from __future__ import annotations

import logging

import requests
from tenacity import retry, retry_if_exception_type, stop_after_attempt, wait_exponential

from .config import Settings

logger = logging.getLogger(__name__)


class LaravelClient:
    def __init__(self, settings: Settings):
        self._base_url = settings.laravel_base_url
        self._camera_code = settings.camera_code
        self._session = requests.Session()
        self._session.headers.update({
            "Authorization": f"Bearer {settings.laravel_token}",
            "Accept": "application/json",
        })

    @retry(
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=1, min=1, max=15),
        retry=retry_if_exception_type(requests.exceptions.RequestException),
        reraise=True,
    )
    def fetch_camera_config(self) -> dict:
        """GET /cctv-cameras/{code}: zone_type + lane_geofence polygon, straight
        from the camera's DB record. Called once at startup so main.py doesn't
        have to hardcode which zone this deployment is watching."""
        response = self._session.get(f"{self._base_url}/cctv-cameras/{self._camera_code}", timeout=10)
        response.raise_for_status()
        return response.json()

    @retry(
        stop=stop_after_attempt(4),
        wait=wait_exponential(multiplier=1, min=1, max=15),
        retry=retry_if_exception_type(requests.exceptions.RequestException),
        reraise=True,
    )
    def post_violation(self, payload: dict, frame_jpeg_bytes: bytes) -> dict:
        files = {"frame": ("frame.jpg", frame_jpeg_bytes, "image/jpeg")}

        response = self._session.post(
            f"{self._base_url}/violations",
            data=payload,
            files=files,
            timeout=10,
        )

        if response.status_code == 422:
            # Validation error (e.g. duplicate event_uuid) -- not retryable,
            # and not a transport failure, so log and move on instead of raising.
            logger.warning("Laravel rejected violation payload: %s", response.text)
            return {}

        response.raise_for_status()
        return response.json()
