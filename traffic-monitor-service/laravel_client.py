"""Fetches a camera's decrypted config from Laravel.

cctv_cameras.rtsp_url is encrypted at rest (Laravel's `encrypted` Eloquent
cast) -- db.py's direct Postgres reads only ever see ciphertext for that one
column, so the stream URL has to come from Laravel's own API instead, same
endpoint the Python edge worker already uses.
"""

from __future__ import annotations

import os

import requests
from dotenv import load_dotenv

load_dotenv()

_base_url = os.environ["LARAVEL_API_BASE_URL"].rstrip("/")
_session = requests.Session()
_session.headers.update({
    "Authorization": f"Bearer {os.environ['LARAVEL_API_TOKEN']}",
    "Accept": "application/json",
})


def fetch_camera_config(code: str) -> dict:
    response = _session.get(f"{_base_url}/cctv-cameras/{code}", timeout=10)
    response.raise_for_status()
    return response.json()
