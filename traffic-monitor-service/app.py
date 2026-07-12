"""Traffic Monitor dashboard, served from inside Laravel via a server-side
reverse proxy (see app/Http/Controllers/TrafficMonitorProxyController.php).

This process must never be reachable from outside 127.0.0.1 -- it has no
auth of its own. Laravel's /traffic-monitor route (behind `auth`+`verified`
middleware) is the only thing allowed to call it; the browser never talks to
this port directly. Real-time updates bypass this service entirely: the
page's JS connects straight to Reverb using the browser's own Laravel
session cookie against /broadcasting/auth (see routes/channels.php).
"""

from __future__ import annotations

import json
import logging
import os
import time

from dotenv import load_dotenv
from flask import Flask, Response, render_template

from camera_worker import get_or_start_worker
from db import fetch_active_cameras, fetch_pending_violations

load_dotenv()

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(name)s: %(message)s")
logger = logging.getLogger("traffic_monitor")

app = Flask(__name__)


@app.route("/")
def index():
    violations = fetch_pending_violations()
    cameras = fetch_active_cameras()

    reverb_config = {
        "key": os.environ["REVERB_APP_KEY"],
        "host": os.environ["REVERB_HOST"],
        "port": int(os.environ["REVERB_PORT"]),
        "scheme": os.environ["REVERB_SCHEME"],
    }

    return render_template(
        "index.html",
        violations_json=json.dumps(violations),
        reverb_config_json=json.dumps(reverb_config),
        total_count=len(violations),
        cameras=cameras,
    )


@app.route("/camera/<code>/feed")
def camera_feed(code):
    worker = get_or_start_worker(code)

    def generate():
        while True:
            jpeg = worker.latest_jpeg()
            if jpeg:
                yield b"--frame\r\nContent-Type: image/jpeg\r\n\r\n" + jpeg + b"\r\n"
            time.sleep(0.2)

    return Response(generate(), mimetype="multipart/x-mixed-replace; boundary=frame")


if __name__ == "__main__":
    port = int(os.environ.get("FLASK_PORT", "5001"))
    logger.info("Starting traffic-monitor-service on 127.0.0.1:%d", port)
    # host=127.0.0.1 only: this must stay unreachable from outside localhost,
    # Laravel's reverse proxy is the only intended caller.
    # threaded=True is required: an open MJPEG feed holds its request thread
    # for as long as someone's watching, and would otherwise block the index
    # route and every other camera's feed on Flask's single-threaded default.
    app.run(host="127.0.0.1", port=port, debug=False, threaded=True)
