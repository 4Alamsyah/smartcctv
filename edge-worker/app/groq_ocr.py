"""Groq ANPR fallback: when the local plate crop is too blurry/small for a
lightweight on-device OCR, ship the crop to Groq's vision-capable LLM for a
fast read. Groq is chosen specifically for its low first-token latency, which
matters here because we're on the critical path of a live violation event.
"""

from __future__ import annotations

import base64
import json
import logging

import cv2
from groq import Groq
from tenacity import retry, stop_after_attempt, wait_exponential

from .config import Settings

logger = logging.getLogger(__name__)

_EXTRACTION_PROMPT = (
    "You are an ANPR (Automatic Number Plate Recognition) system for Indonesian "
    "vehicle plates. Look at this cropped CCTV frame and return ONLY a JSON "
    "object: {\"plate_number\": string|null, \"confidence\": integer 0-100}. "
    "Use the exact plate text as printed (uppercase, no spaces added). If no "
    "plate is legible, return {\"plate_number\": null, \"confidence\": 0}."
)


class GroqAnprClient:
    def __init__(self, settings: Settings):
        self._client = Groq(api_key=settings.groq_api_key)
        self._model = settings.groq_vision_model

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=0.5, max=4))
    def read_plate(self, plate_crop_bgr) -> tuple[str | None, int]:
        ok, buffer = cv2.imencode(".jpg", plate_crop_bgr)
        if not ok:
            return None, 0

        b64_image = base64.b64encode(buffer.tobytes()).decode("utf-8")

        completion = self._client.chat.completions.create(
            model=self._model,
            messages=[
                {
                    "role": "user",
                    "content": [
                        {"type": "text", "text": _EXTRACTION_PROMPT},
                        {
                            "type": "image_url",
                            "image_url": {"url": f"data:image/jpeg;base64,{b64_image}"},
                        },
                    ],
                }
            ],
            temperature=0.0,
            response_format={"type": "json_object"},
        )

        try:
            payload = json.loads(completion.choices[0].message.content)
            return payload.get("plate_number"), int(payload.get("confidence", 0))
        except (json.JSONDecodeError, KeyError, TypeError) as exc:
            logger.warning("Groq ANPR returned unparsable payload: %s", exc)
            return None, 0
