# MTASK — API Documentation

All endpoints live under `/api`, accept **POST** (form-encoded) and return a
consistent JSON envelope.

## Response envelope

```json
{ "ok": true, "data": { ... }, "message": "OK" }
```

- `ok` — boolean success flag.
- `data` — endpoint-specific payload (or `null`).
- `message` — human-readable status / error message.

HTTP status codes mirror the result (200 success, 400 bad request, 401
unauthorized, 403 forbidden, 409 conflict, 425 too early, 429 rate limited,
503 maintenance).

## Authentication

Every request must include the Telegram WebApp **initData** so the server can
verify the caller via HMAC (Telegram's documented algorithm):

- Form field: `initData=<raw initData string>`
- or header: `X-Telegram-InitData: <raw initData string>`

The front-end (`assets/js/app.js`) sends both automatically. Requests with
missing or invalid `initData` receive `401 Unauthorized`. Banned users are
rejected.

Optional `ref=<code>` may be supplied on the first call to attribute a referral.

---

## Endpoints

### `POST /api/session.php`
Authenticate and bootstrap the app.

**Returns:** `user` (incl. `balance`, `balance_usd`, totals, `referral_code`),
public `settings`, and the latest 10 `recent` transactions.

---

### `POST /api/ads.php`
| Param | Values | Description |
|---|---|---|
| `action` | `status` \| `claim` | Operation |

- `status` → reward, daily limit, today's count, cooldown left, `can_watch`,
  `monetag_zone`.
- `claim` → credits the ad reward (after the Monetag popup resolves on the
  client). Enforces cooldown, daily limit, optional daily-earnings cap, rate
  limiting and IP logging. Returns new `balance` and refreshed `status`.

---

### `POST /api/tasks.php`
| Param | Values |
|---|---|
| `action` | `list` \| `start` \| `claim` |
| `task_id` | required for start/claim |

- `list` → active tasks with the user's per-task `user_status`.
- `start` → records the start time; returns `wait_time`.
- `claim` → validates the timer (and optional Telegram membership via
  `getChatMember`), then credits the reward. Prevents double claims.

---

### `POST /api/bonus.php`
| Param | Values |
|---|---|
| `action` | `status` \| `claim` |

- `status` → reward `ladder`, `current_day`, `next_day`, `claimed_today`,
  `next_reward`.
- `claim` → claims today's bonus, advances the streak (resets to day 1 if a day
  was missed, wraps after the final day). Returns new `balance`.

---

### `POST /api/referrals.php`
**Returns:** referral `code`, shareable `link`, `total_referrals`,
`total_earned`, `reward_per_ref`, and a list of `referred` users.

---

### `POST /api/wallet.php`
| Param | Default | Description |
|---|---|---|
| `page` | 1 | Page number |
| `limit` | 20 | Page size (5–50) |
| `type` | — | Optional transaction-type filter |

**Returns:** `balance`, `balance_usd`, `pending`, lifetime totals and a
paginated `transactions` object (`items`, `page`, `pages`, `total`).

---

### `POST /api/withdraw.php`
| Param | Values |
|---|---|
| `action` | `methods` \| `request` \| `history` |

- `methods` → active payment methods (with dynamic `fields` and effective
  minimums), `min_withdraw`, `mt_per_usd`, current `balance`.
- `request` → params: `method_id`, `amount`, and `field_<name>` for each method
  field. Validates the minimum and balance, debits the balance immediately
  (held), creates a pending withdrawal, and notifies the admin chat.
- `history` → the user's withdrawal history with statuses.

---

### `POST /api/profile.php`
**Returns:** the user's profile fields and support links (support username,
privacy/terms URLs).

---

## Rate limiting

A DB-backed limiter (`rate_limits` table) protects sensitive actions:
ad claims (1 per cooldown), daily-bonus claims, withdrawals (5 / 10 min) and
admin logins (8 / 10 min). Exceeding a limit returns `429`.

## Errors & maintenance

When **maintenance mode** is on, non-admin requests receive `503` with the
configured announcement message.
