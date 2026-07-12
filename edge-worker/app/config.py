"""Central config object loaded once from environment. Every other module
receives a `Settings` instance instead of reading os.environ directly, so the
whole pipeline is unit-testable without env-var patching gymnastics."""

from __future__ import annotations

import os
from dataclasses import dataclass, field

from dotenv import load_dotenv

load_dotenv()


@dataclass(frozen=True)
class Settings:
    laravel_base_url: str
    laravel_token: str
    groq_api_key: str
    groq_vision_model: str

    camera_code: str

    yolo_model_path: str
    yolo_confidence_threshold: float
    vehicle_classes: set[str]

    stationary_movement_tolerance_px: float
    stationary_threshold_seconds: int
    frame_sample_interval_seconds: float

    lane_geofence_override: list[tuple[float, float]] = field(default_factory=list)

    @staticmethod
    def load() -> "Settings":
        geofence_raw = os.environ.get("LANE_GEOFENCE_OVERRIDE", "").strip()
        geofence = []
        if geofence_raw:
            for pair in geofence_raw.split(";"):
                lng_str, lat_str = pair.split(",")
                geofence.append((float(lng_str), float(lat_str)))

        return Settings(
            laravel_base_url=os.environ["LARAVEL_API_BASE_URL"].rstrip("/"),
            laravel_token=os.environ["LARAVEL_API_TOKEN"],
            groq_api_key=os.environ["GROQ_API_KEY"],
            groq_vision_model=os.environ.get("GROQ_VISION_MODEL", "llama-3.2-90b-vision-preview"),
            camera_code=os.environ["CAMERA_CODE"],
            yolo_model_path=os.environ.get("YOLO_MODEL_PATH", "yolov8n.pt"),
            yolo_confidence_threshold=float(os.environ.get("YOLO_CONFIDENCE_THRESHOLD", "0.45")),
            vehicle_classes=set(os.environ.get("VEHICLE_CLASSES", "car,motorcycle,bus,truck").split(",")),
            stationary_movement_tolerance_px=float(os.environ.get("STATIONARY_MOVEMENT_TOLERANCE_PX", "18")),
            stationary_threshold_seconds=int(os.environ.get("STATIONARY_THRESHOLD_SECONDS", "180")),
            frame_sample_interval_seconds=float(os.environ.get("FRAME_SAMPLE_INTERVAL_SECONDS", "1.0")),
            lane_geofence_override=geofence,
        )
