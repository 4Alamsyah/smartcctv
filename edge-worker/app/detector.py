"""YOLO vehicle detection + multi-object tracking.

Uses Ultralytics' built-in ByteTrack integration (model.track) rather than a
hand-rolled tracker: it gives every detected vehicle a stable `track_id`
across frames for free, which StationaryTracker relies on to measure how
long *that specific vehicle* has stayed in one place.
"""

from __future__ import annotations

from dataclasses import dataclass

from ultralytics import YOLO

from .config import Settings


@dataclass(frozen=True)
class Detection:
    track_id: int
    class_name: str
    confidence: float
    bbox_xyxy: tuple[float, float, float, float]

    @property
    def center(self) -> tuple[float, float]:
        x1, y1, x2, y2 = self.bbox_xyxy
        return ((x1 + x2) / 2.0, (y1 + y2) / 2.0)


class VehicleDetector:
    def __init__(self, settings: Settings):
        self._settings = settings
        self._model = YOLO(settings.yolo_model_path)

    def detect(self, frame) -> list[Detection]:
        results = self._model.track(
            frame,
            persist=True,
            conf=self._settings.yolo_confidence_threshold,
            verbose=False,
            tracker="bytetrack.yaml",
        )

        detections: list[Detection] = []
        result = results[0]
        if result.boxes is None or result.boxes.id is None:
            return detections

        for box, track_id, cls_idx, conf in zip(
            result.boxes.xyxy.tolist(),
            result.boxes.id.tolist(),
            result.boxes.cls.tolist(),
            result.boxes.conf.tolist(),
        ):
            class_name = result.names[int(cls_idx)]
            if class_name not in self._settings.vehicle_classes:
                continue

            detections.append(
                Detection(
                    track_id=int(track_id),
                    class_name=class_name,
                    confidence=float(conf),
                    bbox_xyxy=tuple(box),
                )
            )

        return detections
