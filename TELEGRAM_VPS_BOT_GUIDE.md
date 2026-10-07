# Telegram VPS Interaction Bot

This bot lets you interact with your VPS via Telegram commands.

## Files
- `telegram_vps_bot.php`
- `.env` (you create this from `.env.example`)

## 1) Add config to `.env`

Add these variables:

```env
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_ALLOWED_CHAT_ID=your_chat_id_here
VPS_DOMAIN_PATHS=/www/server/panel/vhost/nginx,/www/server/panel/vhost/apache,/www/server/panel/vhost/openlitespeed
```

Notes:
- `TELEGRAM_ALLOWED_CHAT_ID` is optional but strongly recommended.
- If `TELEGRAM_ALLOWED_CHAT_ID` is set, only that chat can use the bot.

## 2) Run bot

```bash
php telegram_vps_bot.php
```

For one polling cycle only:

```bash
php telegram_vps_bot.php --once
```

## 3) Telegram commands

- `/help` - show command list
- `/domains` - list unique domains from aaPanel vhost configs
- `/count_domains` - count unique domains
- `/uptime` - show server uptime

## 4) Run in background (Linux)

```bash
nohup php telegram_vps_bot.php > /var/log/telegram-vps-bot.log 2>&1 &
```

## 5) Security checklist

- Use a strong bot token and keep `.env` private.
- Set `TELEGRAM_ALLOWED_CHAT_ID`.
- Rotate Telegram token if leaked.
- Run bot as non-root when possible.
