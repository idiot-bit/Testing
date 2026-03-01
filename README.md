# telegram-railway-audit-bot

Fast Telegram webhook bot for Railway (VPS-like) speed testing.

## Env vars (Railway Variables)
- BOT_TOKEN = Telegram bot token
- WEBHOOK_SECRET = optional (setWebhook secret_token)
- LOG_TO_FILE = 0 or 1 (default 0)
- LOG_FILE = filename (default tg_railway_audit.log)

## Endpoints
- GET /health
- POST /webhook

## Set webhook
Use:
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<YOUR-RAILWAY-URL>/webhook