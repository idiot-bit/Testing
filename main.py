import os
import time
import json
import tempfile
import requests
from datetime import datetime
from fastapi import FastAPI, Request, BackgroundTasks
from fastapi.responses import PlainTextResponse
from threading import Lock

BOT_TOKEN = os.getenv("BOT_TOKEN", "")
WEBHOOK_SECRET = os.getenv("WEBHOOK_SECRET", "")  # optional

API_BASE = f"https://api.telegram.org/bot{BOT_TOKEN}"

app = FastAPI()

# Store last 10 messages per chat_id in memory
# { chat_id: [ {log_line}, {log_line}, ... ] }
BUFFER: dict[int, list[dict]] = {}
LOCK = Lock()

def now_utc():
    return datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")

def tg_send_message(chat_id: int, text: str):
    try:
        requests.post(
            f"{API_BASE}/sendMessage",
            data={"chat_id": chat_id, "text": text},
            timeout=10
        )
    except Exception:
        pass

def tg_send_document(chat_id: int, filepath: str, caption: str = ""):
    """
    Send a file to Telegram DM using sendDocument (multipart/form-data).
    """
    try:
        with open(filepath, "rb") as f:
            files = {"document": (os.path.basename(filepath), f)}
            data = {"chat_id": chat_id, "caption": caption}
            requests.post(
                f"{API_BASE}/sendDocument",
                data=data,
                files=files,
                timeout=20
            )
    except Exception:
        pass

def build_and_send_log_if_ready(chat_id: int, username: str | None):
    """
    If buffer has 10 items, write to .log and send to user, then clear buffer.
    Runs in background task.
    """
    with LOCK:
        items = BUFFER.get(chat_id, [])
        if len(items) < 10:
            return
        # take exactly 10 and reset
        batch = items[:10]
        BUFFER[chat_id] = items[10:]  # keep extra if any

    # Write to a temp .log file
    safe_user = username or f"chat_{chat_id}"
    filename = f"audit_{safe_user}_{int(time.time())}.log"

    tmpdir = tempfile.gettempdir()
    filepath = os.path.join(tmpdir, filename)

    # Write JSON lines for easy parsing later
    with open(filepath, "w", encoding="utf-8") as f:
        for row in batch:
            f.write(json.dumps(row, ensure_ascii=False) + "\n")

    # Send file to user
    caption = "✅ Your last 10 messages audit log"
    tg_send_document(chat_id, filepath, caption=caption)

    # Cleanup temp file
    try:
        os.remove(filepath)
    except Exception:
        pass

@app.get("/health")
def health():
    return {"ok": True}

@app.post("/webhook")
async def webhook(request: Request, background: BackgroundTasks):
    # Optional secret verification
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

    # ACK instantly
    # (Fast response for Telegram webhook)
    resp = PlainTextResponse("OK", status_code=200)

    # Ignore non-text or missing chat_id
    if not chat_id:
        return resp

    # Save to per-user buffer
    row = {
        "ts": now_utc(),
        "type": "incoming",
        "update_id": update_id,
        "chat_id": chat_id,
        "username": username,
        "text": text,
    }

    with LOCK:
        BUFFER.setdefault(chat_id, []).append(row)
        count = len(BUFFER[chat_id])

    # Optional: small reply so user knows it's counting
    # (You can remove this if you want silent logging)
    background.add_task(
        tg_send_message,
        chat_id,
        f"🧾 Audit saved ({count}/10). Send 10 messages to get .log file."
    )

    # If reached 10, build + send .log
    background.add_task(build_and_send_log_if_ready, chat_id, username)

    return resp
