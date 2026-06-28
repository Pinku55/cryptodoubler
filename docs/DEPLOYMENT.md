# MTASK — Deployment Guide (cPanel)

This guide walks through deploying MTASK on standard cPanel shared hosting.
No Composer, SSH, Node.js, Docker or cron jobs are required.

---

## 1. Prerequisites

- A cPanel hosting account with **PHP 8.3+** and **MySQL 8+ / MariaDB 10.4+**.
- An **HTTPS** domain or subdomain (Telegram requires HTTPS).
- A **Telegram bot** created via [@BotFather](https://t.me/BotFather) — note the
  **bot token** and **bot username**.
- A **Monetag** account with a **Rewarded Popup** zone ID.

---

## 2. Upload the files

1. Compress the project into a ZIP (or use the provided release ZIP).
2. In cPanel → **File Manager**, open your web root
   (e.g. `public_html` or a subdomain folder).
3. Upload the ZIP and **Extract** it so that `index.php` and `install.php`
   sit at the web root.

### Recommended permissions
- Folders: `755`
- Files: `644`
- Ensure `config/` and `storage/` are writable by PHP (usually `755` is fine on
  cPanel since PHP runs as your user).

---

## 3. Create the database

1. cPanel → **MySQL® Databases**.
2. Create a new database (e.g. `user_mtask`).
3. Create a new database user with a strong password.
4. **Add the user to the database** and grant **All Privileges**.
5. Note the database **name**, **user** and **password**.

> Host is almost always `localhost` on cPanel.

---

## 4. Run the installer

1. Visit `https://yourdomain.com/install.php`.
2. Complete the 6 steps:
   1. **Database** — host, name, user, password, site name, base URL.
   2. **Administrator** — username, email, password.
   3. **Telegram Bot** — bot token and username (from @BotFather).
   4. **Webhook** — defaults to `https://yourdomain.com/bot/webhook.php`.
   5. **Monetag** — your rewarded zone ID.
   6. **Finish** — creates tables, seeds defaults, writes `config/config.php`
      and registers the Telegram webhook automatically.
3. On success, **delete `install.php`** from the server (important!).

If the webhook step cannot reach Telegram during install, you can re-register it
later from **Admin → Settings → Webhook**.

---

## 5. Configure the Telegram bot

In [@BotFather](https://t.me/BotFather):

1. `/setdomain` → choose your bot → enter `https://yourdomain.com` (enables the
   Mini App login).
2. `/newapp` or **Bot Settings → Menu Button** → set the Mini App URL to
   `https://yourdomain.com/app.php` so users get an "Open App" button.
3. Optionally set bot commands with `/setcommands`:
   ```
   start - Open the app
   account - Your account summary
   balance - Check your balance
   referral - Get your referral link
   withdraw - Withdraw earnings
   help - Show help
   ```

---

## 6. Verify the deployment

- **Landing page:** `https://yourdomain.com/` should render.
- **Bot:** send `/start` to your bot — you should get a welcome message with an
  "Open MTASK App" button.
- **Mini App:** tap the button inside Telegram; the dashboard should load with
  your balance.
- **Admin:** `https://yourdomain.com/admin` → log in with the admin account.

---

## 7. Going live checklist

- [ ] `install.php` deleted.
- [ ] HTTPS enforced (cPanel → SSL/TLS, AutoSSL).
- [ ] Admin password is strong and unique.
- [ ] `config/`, `classes/`, `storage/`, `database/`, `includes/` are not
      directly accessible (the bundled `.htaccess` files handle this).
- [ ] Monetag zone ID set and ads enabled (Admin → Rewarded Ads).
- [ ] Payment methods reviewed (Admin → Payment Methods).
- [ ] Economy values reviewed: MT-per-USD, minimum withdrawal, rewards.
- [ ] Webhook shows as registered (Admin → Settings → Register Webhook).

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| Redirected to `install.php` repeatedly | `config/config.php` missing or not writable. Re-run the installer; check folder permissions. |
| "Database connection error" | Verify DB host/name/user/password; ensure the user is added to the DB with privileges. |
| Mini App shows "open inside Telegram" | initData missing/invalid — open via the bot button, and ensure the bot token in Settings matches @BotFather. |
| Bot doesn't reply | Re-register the webhook (Admin → Settings). Webhook URL must be public HTTPS. Check `storage/logs/bot.log`. |
| Ads never reward | Confirm the Monetag zone ID and that the SDK loads; rewards are only granted after the popup resolves. |
| 500 errors | Check `storage/logs/php_errors.log`. Temporarily set `environment` to `development` in `config/config.php`. |

---

## 9. Updating

Back up your database and `config/config.php`, upload the new files (do **not**
overwrite `config/config.php`), then hard-refresh. The asset version query string
(`?v=`) busts browser caches.
