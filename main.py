import os
import time
import json
import tempfile
import requests
from datetime import datetime
from threading import Lock
from fastapi import FastAPI, Request, BackgroundTasks
from fastapi.responses import PlainTextResponse

BOT_TOKEN = os.getenv("BOT_TOKEN", "")
WEBHOOK_SECRET = os.getenv("WEBHOOK_SECRET", "")  # optional
API = f"https://api.telegram.org/bot{BOT_TOKEN}"

app = FastAPI()

LOCK = Lock()

# Per chat buffer:
# {
#   chat_id: {
#       "count": int,          # how many user messages collected in current batch
#       "lines": [dict, ...]   # audit lines (incoming/outgoing)
#   }
# }
STATE: dict[int, dict] = {}

def ts_utc() -> str:
    return datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")

def log_add(chat_id: int, row: dict):
    row["ts"] = ts_utc()
    with LOCK:
        st = STATE.setdefault(chat_id, {"count": 0, "lines": []})
        st["lines"].append(row)

def tg_send_message(chat_id: int, text: str) -> tuple[bool, dict, int]:
    """
    Returns: (ok, resp_json, telegram_api_ms)
    """
    start = time.time()
    try:
        r = requests.post(
            f"{API}/sendMessage",
            data={"chat_id": chat_id, "text": text},
            timeout=10
        )
        ms = int((time.time() - start) * 1000)
        resp = r.json()
        return bool(resp.get("ok")), resp, ms
    except Exception as e:
        ms = int((time.time() - start) * 1000)
        return False, {"error": str(e)}, ms

def tg_send_document(chat_id: int, filepath: str, caption: str = ""):
    try:
        with open(filepath, "rb") as f:
            files = {"document": (os.path.basename(filepath), f)}
            data = {"chat_id": chat_id, "caption": caption}
            requests.post(f"{API}/sendDocument", data=data, files=files, timeout=20)
    except Exception:
        pass

def build_and_send_batch_if_ready(chat_id: int, username: str | None):
    """
    When 10 user messages collected -> send .log file to user and reset batch.
    """
    with LOCK:
        st = STATE.get(chat_id)
        if not st or st["count"] < 10:
            return

        # Take current batch lines and reset
        lines = st["lines"]
        STATE[chat_id] = {"count": 0, "lines": []}

    safe_user = username or f"chat_{chat_id}"
    filename = f"railway_audit_{safe_user}_{int(time.time())}.log"
    filepath = os.path.join(tempfile.gettempdir(), filename)

    # JSON Lines, same as your previous logs
    with open(filepath, "w", encoding="utf-8") as f:
        for row in lines:
            f.write(json.dumps(row, ensure_ascii=False) + "\n")

    tg_send_document(
        chat_id,
        filepath,
        caption="✅ Railway audit log (10 messages) attached"
    )

    try:
        os.remove(filepath)
    except Exception:
        pass

def process_message_background(update_id: int, chat_id: int, username: str | None, text: str, received_at: float):
    """
    Runs after webhook ACK. Sends reply and logs outgoing + timing.
    """
    reply = "🚀 RAILWAY VPS AUDIT\nYou said: " + (text or "")

    ok, resp, tg_ms = tg_send_message(chat_id, reply)
    done_at = time.time()

    log_add(chat_id, {
        "type": "outgoing",
        "update_id": update_id,
        "chat_id": chat_id,
        "username": username,
        "reply": reply,
        "latency_ms": int((done_at - received_at) * 1000),
        "telegram_api_ms": tg_ms,
        "telegram_ok": ok,
        "telegram_resp": resp
    })

    # After outgoing is logged, check if 10 messages completed
    build_and_send_batch_if_ready(chat_id, username)

@app.get("/health")
def health():
    return {"ok": True}

@app.post("/webhook")
async def webhook(request: Request, background: BackgroundTasks):
    received_at = time.time()

    # Optional secret token validation (recommended)
    if WEBHOOK_SECRET:
        incoming_secret = request.headers.get("X-Telegram-Bot-Api-Secret-Token", "")
        if incoming_secret != WEBHOOK_SECRET:
            return PlainTextResponse("Forbidden", status_code=403)

    data = await request.json()

    update_id = data.get("update_id")
    msg = data.get("message") or {}
    chat = msg.get("chat") or {}
    frm = msg.get("from") or {}

    chat_id = chat.get("id")
    username = frm.get("username")
    text = msg.get("text", "")

    # ACK instantly (fast webhook)
    resp = PlainTextResponse("OK", status_code=200)

    # Only handle real text messages
    if not chat_id:
        return resp

    # Log incoming
    log_add(chat_id, {
        "type": "incoming",
        "update_id": update_id,
        "chat_id": chat_id,
        "username": username,
        "text": text,
        "recv_ms": 0
    })

    # Count message in batch (10 user messages)
    with LOCK:
        st = STATE.setdefault(chat_id, {"count": 0, "lines": []})
        st["count"] += 1
        current = st["count"]

    # Optional: show counter to user (you can remove if you want silent)
    background.add_task(tg_send_message, chat_id, f"🧾 Audit count: {current}/10")

    # Send main reply + outgoing audit in background
    background.add_task(process_message_background, update_id, chat_id, username, text, received_at)

    return resp
