"""YOLO vehicle detection + multi-object tracking.

Adapted from edge-worker/app/detector.py -- same detection logic (Ultralytics'
built-in ByteTrack via model.track), but takes plain constructor params
instead of the edge worker's Settings dataclass, since this service has no
equivalent per-camera settings object.
"""

from __future__ import annotations

from dataclasses import dataclass

from ultralytics import YOLO


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
    def __init__(self, model_path: str, confidence_threshold: float, vehicle_classes: set[str]):
        self._confidence_threshold = confidence_threshold
        self._vehicle_classes = vehicle_classes
        self._model = YOLO(model_path)

    def detect(self, frame) -> list[Detection]:
        results = self._model.track(
            frame,
            persist=True,
            conf=self._confidence_threshold,
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
            if class_name not in self._vehicle_classes:
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
