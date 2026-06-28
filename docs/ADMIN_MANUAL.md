# MTASK — Administrator Manual

Access the admin panel at `https://yourdomain.com/admin` and sign in with the
account created during installation.

---

## Dashboard
At-a-glance KPIs (total/today/online users, total earnings, total paid, pending
payouts), a 14-day earnings chart, the latest withdrawals and your top referrers.

## Users
- **Search** by name, username, Telegram ID or referral code; filter by status.
- Per-user actions (⋯ menu): **Adjust balance** (positive credits, negative
  debits — recorded as a transaction), **Ban/Unban**, **Reset Daily Bonus**,
  **Reset Referrals**, **View History**, **Delete** (permanent).

## Tasks
Create and edit tasks: title, description, category (website, shortlink,
Telegram channel/group/bot, Instagram, Facebook, Twitter, YouTube, survey),
URL, reward, wait time, daily limit, verification type and optional image.
- **Verify types:** `timer` (wait then claim), `telegram_member` (checks
  membership of `verify_target`, e.g. `@yourchannel`), `auto`, `none`.
- Toggle active/disabled or delete.

## Rewarded Ads
Configure the **Monetag zone ID**, reward per ad, cooldown, daily limit and
optional max daily earnings, and enable/disable ads. Anti-spam (cooldown, limits,
rate limiting, IP logging) is enforced server-side; rewards are granted only
after the Monetag popup resolves.

## Daily Bonus
Edit the reward for each day of the streak ladder and enable/disable the feature.
A missed day resets a user's streak to Day 1; the ladder restarts after the last
day.

## Referrals
Set the reward per referral, an optional maximum referrals per user, and review
your top referrers. Self-referral is blocked and Telegram accounts are unique.

## Withdrawals
Review requests and **Approve**, **Mark Paid** (increments the user's lifetime
withdrawn total and notifies them), or **Reject** (auto-refunds the held MT and
notifies the user). Filter by status and **Export CSV**. The MT amount is debited
from the user at request time and held until processed.

## Payment Methods
Create/edit methods with a unique code, icon (Bootstrap Icons class), minimum
amount, sort order and **dynamic custom fields** defined as JSON, e.g.:

```json
[{"name":"upi_id","label":"UPI ID","type":"text","required":true}]
```

These fields render automatically in the Mini App withdrawal form and are stored
with each request. Toggle active/disabled or delete.

## Transactions
Browse the full ledger, filter by type or a specific user, and **Export CSV**.

## Reports
Daily / weekly / monthly charts for new users, MT earned and MT paid out, plus
summary counters and **CSV export**.

## Settings
- **General:** site name, currency symbol, theme color, logo, favicon.
- **Economy:** MT per USD, minimum withdrawal.
- **Telegram:** bot token, bot username, admin chat ID (for alerts).
- **Integrations:** Monetag zone, support username, privacy/terms URLs.
- **SMTP:** optional email settings.
- **Maintenance:** toggle maintenance mode and set the announcement message.
- **Webhook:** re-register the Telegram webhook at any time.

## Admin Logs
A full audit trail of admin actions (who, what, IP, when).

## Support
Send a **direct message** to a single user, or **broadcast** to all active users.
Broadcasts are sent in batches (re-submit to continue) to respect Telegram rate
limits and shared-hosting timeouts.

---

## Roles
- **super_admin** — full access (the installer creates this).
- **admin / moderator** — reserved for future granular permissions; treat as full
  access today. Add additional admins directly in the `admins` table if needed.

## Security tips
- Use a strong, unique admin password and HTTPS.
- Delete `install.php` after setup.
- Keep the bot token and Monetag zone ID private.
- Review the Admin Logs periodically.
