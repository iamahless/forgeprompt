# ForgePrompt

An image generation app built on the Pixelforge API for the PromptEdit take-home. Users sign in, write a prompt, see what it will cost, and watch the image arrive. Every generation is charged at Pixelforge's cost plus 25%, and failed generations are free.

Built with Laravel 13, Vue 3, Inertia 3 and Tailwind 4.

## Running it locally

You need PHP 8.4 or newer, Composer and Node 22 or newer.

```bash
composer setup
```

That installs dependencies, creates `.env`, migrates the SQLite database and builds the frontend. Then add your Pixelforge key and its signing secret to `.env`:

```
PIXELFORGE_API_KEY=
PIXELFORGE_WEBHOOK_SECRET=
```

Start everything with:

```bash
composer dev                 # web server, queue worker, logs and Vite
php artisan schedule:work    # the polling fallback, in a second terminal
```

The queue worker is required. Generations are submitted to Pixelforge from a queued job, so without a worker they stay queued.

Webhooks arrive at `POST /webhooks/pixelforge`. Pixelforge rejects localhost URLs, so to receive them locally you need a tunnel (Expose, ngrok or Cloudflare Tunnel) and that tunnel's URL set on the API key in the Pixelforge dashboard. Without one the app still works: the scheduler polls Pixelforge for anything that has been quiet for a minute.

Use one API key per environment. `GET /v1/usage` reports each key separately, and the admin report compares against the key the app is running with.

## Admin report

```bash
php artisan user:make-admin you@example.com
```

Admins get an "Admin report" link in the sidebar (`/admin/report`). It covers the last 30 days and shows what users were charged, what Pixelforge charged us and the profit, per day and per user. Each day is compared with `GET /v1/usage` and marked "Match" or "Differs". Below that it lists the newest 50 generations Pixelforge has a record of, with their `gen_…` ids, token counts, provider cost and charge, so any single one can be checked against the Pixelforge dashboard.

## How the money works

All amounts are whole nanodollars (1e-9 USD) stored as integers. That is the precision Pixelforge bills in, so nothing is ever rounded and no float touches a price. `App\Billing\Usd` converts to and from Pixelforge's decimal strings.

- Pixelforge never returns a price. When a generation succeeds, its cost is its token usage multiplied by the rates on that generation's own record (`App\Pixelforge\Rates`), not today's rates.
- The user pays that cost plus the markup in `config/billing.php`. If the markup ever produced a fraction of a nanodollar it rounds up. With the current rates it never does.
- Before submitting, the form shows two numbers from `GET /v1/pricing`. The estimate assumes the expected prompt tokens and the midpoint of the reasoning range. The maximum assumes the most prompt and reasoning tokens Pixelforge allows for that quality.
- The quote is calculated on the server (`POST /generations/quote`), so the price shown comes from the same code that later charges for it.

## Balances

A user's balance is their credit purchases minus the price of their generations. There is no separate ledger to keep in step: the charge is the `price_nanos` column on the generation row, so one generation can only ever be charged once.

While a generation is queued or processing, the most it could cost is held in reserve. A new generation is blocked if the maximum price is more than the available balance, which means a balance cannot go negative. "Buy credits" adds $5, $10 or $25 without taking a payment.

## How a generation moves

1. `CreateGeneration` quotes it, checks the balance while holding a lock on the user, saves it as queued and dispatches `SubmitGeneration`.
2. The job posts it to Pixelforge with the generation's UUID as the `Idempotency-Key` and in `metadata.generation_id`.
3. Pixelforge reports progress by webhook. `ApplyProviderSnapshot` applies each update.
4. The history page polls every 2.5 seconds while anything is unfinished. A failed generation has a "Try again" button that starts a new one with the same settings.

A user can start at most ten generations a minute. Pixelforge's limits are shared by the whole account, so one user must not be able to use them all up.

`ApplyProviderSnapshot` is the only place a generation changes status from Pixelforge's data. The create response, webhooks and the polling command all go through it. It locks the row and only ever moves a generation forward, so a late, repeated or out-of-order update changes nothing.

## What Pixelforge does and how the app copes

| Pixelforge behaviour                                                  | Handling                                                                                                                                                                                    |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 429 rate limit, 429 concurrency limit, 503 overloaded, network errors | The job is released with the longer of `Retry-After` and a growing delay (2s up to 60s). After seven retries, about three minutes, the generation fails with a clear message and no charge. |
| 400 content policy, 429 daily limit                                   | Not retried. The generation fails straight away with the reason and no charge.                                                                                                              |
| A retry after a lost response                                         | The idempotency key returns the same generation, so nothing is created or billed twice.                                                                                                     |
| A generation fails after being accepted                               | Recorded as failed with Pixelforge's reason, price zero.                                                                                                                                    |
| Webhooks signed with HMAC-SHA256                                      | `VerifyPixelforgeSignature` checks the raw body in constant time and rejects signatures older than five minutes.                                                                            |
| The same event delivered twice                                        | Event ids are unique in `webhook_events`. The event and its effect are saved in one transaction, and a repeated id is acknowledged and ignored.                                             |
| Events out of order                                                   | A generation never moves back from succeeded or failed, and never from processing to queued.                                                                                                |
| A webhook before the create request returns                           | The event is matched by `metadata.generation_id`, before the provider id is known.                                                                                                          |
| A webhook that never arrives                                          | `generations:reconcile` runs every minute and fetches any in-flight generation that has been quiet for a minute.                                                                            |
| Image URLs expire after 24 hours                                      | The history shows "This image's link has expired". Images are not copied.                                                                                                                   |

## Code layout

- `app/Pixelforge` is the API client, its exception, the typed snapshot of a generation that the API and webhooks return, and the pricing and rates maths.
- `app/Billing` is the nanodollar helper, the markup and the quote.
- `app/Actions` holds the logic. Controllers take a Form Request, call one action and return.
- `app/Jobs/SubmitGeneration.php` submits a generation with retries.
- `app/Console/Commands` has the polling fallback and `user:make-admin`.
- `resources/js/pages/Dashboard.vue` is the generate page and history. `resources/js/pages/admin/Report.vue` is the admin report.

## Tests

```bash
composer test    # Pint, PHPStan and the Pest suite
```

Pixelforge is faked in tests and stray HTTP requests fail, so the suite never calls the real API. `tests/Feature/Integration` runs whole generations through the app (form request, queue worker, signed webhooks, polling command, history and admin report) with only Pixelforge's HTTP API faked. The expected prices in the tests are fixed values worked out by hand, starting from the examples in the Pixelforge docs.

## Deploying

The host needs three things running: the web app, a queue worker (`php artisan queue:work`) and the scheduler (`php artisan schedule:run` every minute). Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` from a key created for that environment, and set that key's webhook URL to `https://<your-domain>/webhooks/pixelforge`.

## Known limits

- If every attempt to submit a generation times out but Pixelforge did create it, the app marks it failed while Pixelforge bills it. The admin report would show that day as "Differs".
- The reserve is based on the rates at quote time. If Pixelforge raised its rates between the quote and the create request, the final price could be higher than the amount held.
- A deleted account keeps its generations, with no user attached, so provider costs still add up to what Pixelforge billed.
