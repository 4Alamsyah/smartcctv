"""Pixel -> WGS84 coordinate mapping via a per-camera homography.

A single CCTV camera is a fixed, calibrated view: once you know four
pixel<->lng/lat correspondences on the ground plane (surveyed once during
camera installation), a homography accurately maps any other pixel on that
same ground plane to real-world coordinates. This is what actually produces
the "Coordinates" field in the payload sent to Laravel -- YOLO only gives us
pixel bounding boxes.

Calibration points are stored per camera in Laravel (cctv_cameras) in
production; `CALIBRATION_POINTS` below is the local-dev fallback so this
script is runnable standalone.
"""

from __future__ import annotations

import numpy as np
import cv2

# (pixel_x, pixel_y) -> (lng, lat), four ground-plane reference points.
CALIBRATION_POINTS: list[tuple[tuple[float, float], tuple[float, float]]] = [
    ((120, 640), (106.80190, -6.22480)),
    ((1180, 640), (106.80270, -6.22480)),
    ((1180, 300), (106.80270, -6.22420)),
    ((120, 300), (106.80190, -6.22420)),
]


class GeoMapper:
    def __init__(self, calibration_points=CALIBRATION_POINTS):
        pixel_pts = np.array([p for p, _ in calibration_points], dtype=np.float32)
        geo_pts = np.array([g for _, g in calibration_points], dtype=np.float32)
        self._homography, _ = cv2.findHomography(pixel_pts, geo_pts)

    def pixel_to_lnglat(self, pixel_x: float, pixel_y: float) -> tuple[float, float]:
        point = np.array([[[pixel_x, pixel_y]]], dtype=np.float32)
        mapped = cv2.perspectiveTransform(point, self._homography)
        lng, lat = mapped[0][0]
        return float(lng), float(lat)
