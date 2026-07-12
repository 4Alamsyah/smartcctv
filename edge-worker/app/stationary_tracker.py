"""Per-track stationary duration measurement.

A vehicle is "stationary" while its bbox center stays within
`movement_tolerance_px` of where it was last sample. We track, per track_id,
when it *first* became stationary and whether we've already reported it as a
violation (so a single stop only fires one event, not one per frame past
threshold).
"""

from __future__ import annotations

import time
from dataclasses import dataclass, field

from .detector import Detection


@dataclass
class _TrackState:
    last_center: tuple[float, float]
    stationary_since: float
    reported: bool = False


class StationaryTracker:
    def __init__(self, movement_tolerance_px: float, threshold_seconds: int):
        self._movement_tolerance_px = movement_tolerance_px
        self._threshold_seconds = threshold_seconds
        self._states: dict[int, _TrackState] = {}

    def update(self, detections: list[Detection]) -> list[tuple[Detection, int]]:
        """Feed the latest frame's detections in; returns (detection, stationary_seconds)
        for every vehicle that has just crossed the threshold for the first time."""
        now = time.monotonic()
        newly_breached: list[tuple[Detection, int]] = []
        seen_ids = set()

        for det in detections:
            seen_ids.add(det.track_id)
            state = self._states.get(det.track_id)

            if state is None:
                self._states[det.track_id] = _TrackState(last_center=det.center, stationary_since=now)
                continue

            displacement = _distance(state.last_center, det.center)
            if displacement > self._movement_tolerance_px:
                # Vehicle moved -- reset the stationary clock.
                state.last_center = det.center
                state.stationary_since = now
                state.reported = False
                continue

            stationary_seconds = int(now - state.stationary_since)
            if stationary_seconds >= self._threshold_seconds and not state.reported:
                state.reported = True
                newly_breached.append((det, stationary_seconds))

        # Drop tracks YOLO/ByteTrack has lost (vehicle left the frame).
        for stale_id in set(self._states) - seen_ids:
            del self._states[stale_id]

        return newly_breached


def _distance(a: tuple[float, float], b: tuple[float, float]) -> float:
    return ((a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2) ** 0.5
