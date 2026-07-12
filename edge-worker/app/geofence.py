"""Point-in-polygon zone check for the camera's lane/no-parking area.

The polygon comes from Laravel's `cctv_cameras.lane_geofence` (WGS84 lng/lat,
same as GeoMapper's output), so the check happens *after* a pixel detection
has been converted to real-world coordinates -- see ViolationPipeline.
"""

from __future__ import annotations

from shapely.geometry import Point, Polygon


class Geofence:
    def __init__(self, points: list[tuple[float, float]] | None):
        # Fewer than 3 points can't form a polygon -- treat as "no geofence
        # configured" rather than raising, so an un-surveyed camera still
        # reports violations for the whole frame (the pre-geofence behavior)
        # instead of silently going blind.
        self._polygon = Polygon(points) if points and len(points) >= 3 else None

    @property
    def is_configured(self) -> bool:
        return self._polygon is not None

    def contains(self, lng: float, lat: float) -> bool:
        if self._polygon is None:
            return True
        return self._polygon.contains(Point(lng, lat))
