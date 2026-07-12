"""Read-only Postgres access for the traffic monitor page.

Connects directly to the same database Laravel uses instead of going through
its HTTP API -- this service only ever renders a dashboard, so there's no
mutation path that needs Eloquent's model layer. Mirrors the query shape of
App\\Http\\Controllers\\Api\\V1\\TrafficViolationController::index()'s default
view (status=pending_review, newest first, capped at 50).
"""

from __future__ import annotations

import os

import psycopg2
import psycopg2.extras
from dotenv import load_dotenv

load_dotenv()


def get_connection():
    return psycopg2.connect(
        host=os.environ["DB_HOST"],
        port=os.environ["DB_PORT"],
        dbname=os.environ["DB_DATABASE"],
        user=os.environ["DB_USERNAME"],
        password=os.environ["DB_PASSWORD"],
    )


def fetch_pending_violations(limit: int = 50) -> list[dict]:
    app_url = os.environ["APP_URL"].rstrip("/")

    query = """
        SELECT
            tv.id,
            tv.event_uuid,
            tv.violation_type,
            tv.plate_number,
            tv.plate_confidence,
            tv.plate_source,
            tv.stationary_seconds,
            tv.threshold_seconds,
            tv.frame_path,
            tv.status,
            tv.detected_at,
            ST_X(tv.location::geometry) AS lng,
            ST_Y(tv.location::geometry) AS lat,
            cc.code AS camera_code,
            cc.name AS camera_name
        FROM traffic_violations tv
        JOIN cctv_cameras cc ON cc.id = tv.camera_id
        WHERE tv.status = 'pending_review'
        ORDER BY tv.detected_at DESC
        LIMIT %s
    """

    with get_connection() as conn, conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(query, (limit,))
        rows = [dict(row) for row in cur.fetchall()]

    for row in rows:
        row["frame_url"] = f"{app_url}/storage/{row['frame_path']}" if row["frame_path"] else None
        row["detected_at"] = row["detected_at"].isoformat() if row["detected_at"] else None
        del row["frame_path"]

    return rows


def fetch_active_cameras() -> list[dict]:
    """code/name only -- rtsp_url is encrypted, resolved separately via
    laravel_client.fetch_camera_config() only when a feed is actually opened."""
    query = "SELECT code, name FROM cctv_cameras WHERE is_active = true ORDER BY code"

    with get_connection() as conn, conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(query)
        return [dict(row) for row in cur.fetchall()]
