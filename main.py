import os
import time
import json
import requests
from datetime import datetime
from fastapi import FastAPI, Request, BackgroundTasks
from fastapi.responses import PlainTextResponse
from logging.handlers import RotatingFileHandler
import logging

BOT_TOKEN = os.getenv("BOT_TOKEN", "")
WEBHOOK_SECRET = os.getenv("WEBHOOK_SECRET", "")
LOG_DIR = "logs"
LOG_FILE = f"{LOG_DIR}/telegram_audit.log"

API = f"https://api.telegram.org/bot{BOT_TOKEN}"

app = FastAPI()

# =============================
# Create log directory
# =============================
os.makedirs(LOG_DIR, exist_ok=True)

# =============================
# Configure logger
# =============================
logger = logging.getLogger("telegram_bot")
logger.setLevel(logging.INFO)

handler = RotatingFileHandler(
    LOG_FILE,
    maxBytes=5 * 1024 * 1024,  # 5MB
    backupCount=3              # keep 3 old files
)

formatter = logging.Formatter('%(message)s')
handler.setFormatter(formatter)
logger.addHandler(handler)

# Also print to Railway logs
console_handler = logging.StreamHandler()
console_handler.setFormatter(formatter)
logger.addHandler(console_handler)

def log_json(data: dict):
    data["ts"] = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")
    logger.info(json.dumps(data, ensure_ascii=False))


# =============================
# Telegram Sender
# =============================
def tg_send_message(chat_id: int, text: str):
    start = time.time()

    try:
        r = requests.post(
            f"{API}/sendMessage",
            data={"chat_id": chat_id, "text": text},
            timeout=10
        )

        ms = int((time.time() - start) * 1000)
        resp = r.json()

        log_json({
            "type": "outgoing",
            "chat_id": chat_id,
            "telegram_api_ms": ms,
            "telegram_ok": resp.get("ok"),
            "telegram_resp": resp
        })

    except Exception as e:
        ms = int((time.time() - start) * 1000)
        log_json({
            "type": "telegram_error",
            "telegram_api_ms": ms,
            "error": str(e)
        })


# =============================
# Health Check
# =============================
@app.get("/health")
def health():
    return {"ok": True}


# =============================
# Webhook
# =============================
@app.post("/webhook")
async def webhook(request: Request, background: BackgroundTasks):

    start_time = time.time()

    if WEBHOOK_SECRET:
        incoming_secret = request.headers.get("X-Telegram-Bot-Api-Secret-Token", "")
        if incoming_secret != WEBHOOK_SECRET:
            log_json({"type": "blocked", "reason": "invalid_secret"})
            return PlainTextResponse("Forbidden", status_code=403)

    data = await request.json()

    update_id = data.get("update_id")
    message = data.get("message") or {}
    chat = message.get("chat") or {}
    user = message.get("from") or {}

    chat_id = chat.get("id")
    username = user.get("username")
    text = message.get("text", "")

    processing_ms = int((time.time() - start_time) * 1000)

    log_json({
        "type": "incoming",
        "update_id": update_id,
        "chat_id": chat_id,
        "username": username,
        "text": text,
        "processing_ms": processing_ms
    })

    if chat_id:
        reply = (
            "🚀 RAILWAY LOG TEST\n"
            f"You said: {text}\n"
            f"Processing: {processing_ms} ms"
        )

        background.add_task(tg_send_message, chat_id, reply)

    return PlainTextResponse("OK", status_code=200)
