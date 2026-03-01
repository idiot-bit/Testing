import os
import time
import json
import requests
from datetime import datetime
from fastapi import FastAPI, Request, BackgroundTasks
from fastapi.responses import PlainTextResponse

BOT_TOKEN = os.getenv("BOT_TOKEN", "")
WEBHOOK_SECRET = os.getenv("WEBHOOK_SECRET", "")  # optional
LOG_TO_FILE = os.getenv("LOG_TO_FILE", "0") == "1"
LOG_FILE = os.getenv("LOG_FILE", "tg_railway_audit.log")

API = f"https://api.telegram.org/bot{BOT_TOKEN}"

app = FastAPI()

def log_line(obj: dict):
    obj["ts"] = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")
    line = json.dumps(obj, ensure_ascii=False)

    # Railway logs (best)
    print(line, flush=True)

    # Optional file log (not persistent on Railway unless you mount volume)
    if LOG_TO_FILE:
        try:
            with open(LOG_FILE, "a", encoding="utf-8") as f:
                f.write(line + "\n")
        except Exception:
            pass

def tg_send_message(chat_id: int, text: str):
    if not BOT_TOKEN:
        log_line({"type": "error", "msg": "BOT_TOKEN missing"})
        return

    start = time.time()
    try:
        r = requests.post(
            f"{API}/sendMessage",
            data={"chat_id": chat_id, "text": text},
            timeout=10
        )
        ms = int((time.time() - start) * 1000)
        resp = r.json()
        log_line({
            "type": "telegram_send",
            "telegram_api_ms": ms,
            "telegram_ok": bool(resp.get("ok")),
            "telegram_resp": resp
        })
    except Exception as e:
        ms = int((time.time() - start) * 1000)
        log_line({"type": "telegram_send_error", "telegram_api_ms": ms, "error": str(e)})

@app.get("/")
def root():
    return {"ok": True, "service": "telegram-railway-audit-bot"}

@app.get("/health")
def health():
    return {"ok": True}

@app.post("/webhook")
async def webhook(request: Request, background: BackgroundTasks):
    received_at = time.time()

    # Optional security: Telegram secret header
    if WEBHOOK_SECRET:
        incoming = request.headers.get("X-Telegram-Bot-Api-Secret-Token", "")
        if incoming != WEBHOOK_SECRET:
            log_line({"type": "blocked", "reason": "bad_secret"})
            return PlainTextResponse("Forbidden", status_code=403)

    data = await request.json()

    update_id = data.get("update_id")
    msg = data.get("message") or {}
    chat = msg.get("chat") or {}
    frm = msg.get("from") or {}

    chat_id = chat.get("id")
    username = frm.get("username")
    text = msg.get("text", "")

    # Log incoming immediately
    log_line({
        "type": "incoming",
        "update_id": update_id,
        "chat_id": chat_id,
        "username": username,
        "text": text
    })

    # ACK instantly to Telegram (webhook speed)
    # Then do work in background.
    if chat_id:
        process_ms = int((time.time() - received_at) * 1000)
        reply = (
            "🚀 RAILWAY VPS AUDIT\n"
            f"You said: {text}\n"
            f"App processing: {process_ms} ms"
        )
        background.add_task(tg_send_message, chat_id, reply)

    return PlainTextResponse("OK", status_code=200)