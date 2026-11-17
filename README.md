# Promptframe

Promptframe is a web app for generating images through the
[Pixelforge](https://pixelforge.promptedit.dev) API. Users pay what Pixelforge
charges plus a configurable markup (25% by default). Before an image is made,
the app shows a price range whose maximum is a guaranteed upper bound.

Built with Laravel 12, Inertia 2, Vue 3 and TypeScript, styled with Tailwind
and shadcn-vue components.

## Status

| Area | State |
| --- | --- |
| Accounts (register, login, email verification, password reset, profile settings) | Done |
| Generate page with a live price estimate | Done |
| Pricing engine (quote before, exact charge after) | Done |
| Submitting generations through a queued job with retries | In progress |
| Pixelforge webhooks (marking generations succeeded or failed) | Not started |
| User balances and charging | Not started |

## How pricing works

Pixelforge bills in tokens. The app reads token rules and rates from
`GET /v1/pricing` and caches them for 10 minutes. Prices are never hard-coded,
so when Pixelforge changes them no deploy is needed.

- **Quote.** As the user types, the Generate page asks `POST /generate/quote`
  for a price. The quote is a range, because text and reasoning token counts
  can only be estimated until the image is made. Output tokens and
  reference-image tokens are known exactly.
- **Charge.** When a generation succeeds, it is charged at the rates
  Pixelforge fixed when the generation was created, not at the current rates.
- **Money.** Every amount is an integer number of nanodollars
  (1 USD = 1,000,000,000). That matches Pixelforge's nine-decimal amounts, so
  no precision is lost. Intermediate maths uses `bcmath`, and the markup rounds
  up to the next nanodollar.

`App\Pricing\PriceCalculator` produces both the quote and the charge, so the
two always agree on markup and rounding. [DECISIONS.md](DECISIONS.md) explains
each of these choices.

## Project layout

```
app/
  Pixelforge/      HTTP client for the Pixelforge API, image size and quality enums
  Pricing/         Nanodollars, token tables and rates, markup, PriceCalculator
  Generations/     Generation status and the transitions it allows
  Jobs/            SubmitGeneration: sends a saved generation to Pixelforge
  Http/            Controllers and form requests (quote, generate, auth, settings)
  Console/         pixelforge:check
resources/js/
  pages/           Inertia pages (Generate.vue, Dashboard.vue, auth/, settings/)
  composables/     useQuote, useSubmitGeneration, useAppearance
  components/ui/   shadcn-vue components
tests/
  Unit/Pricing/    Pricing engine tests
  Feature/         HTTP, command and job tests
  Fixtures/        A recorded Pixelforge pricing response
```

## Getting started

### Requirements

- PHP 8.2 or newer with the `bcmath` extension
- Composer 2
- Node 22 and npm
- SQLite (the default database; MySQL and Postgres work through `.env`)

### Setup

```bash
git clone https://github.com/lucklegend/promptframe.git
cd promptframe

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate
```

### Pixelforge credentials

Create an API key in the Pixelforge dashboard (use a separate key for each
environment) and add it to `.env` along with that key's webhook signing secret:

```dotenv
PIXELFORGE_API_KEY=...
PIXELFORGE_WEBHOOK_SECRET=whsec_...
```

Then confirm the key works and the secret is set. This command never prints
either value:

```bash
php artisan pixelforge:check
```

### Run it

```bash
composer dev
```

This starts the Laravel server, the queue worker, the log viewer (Pail) and
Vite together. Open http://localhost:8000, register an account, and go to
**Generate**.

The queue worker has to run for generations to reach Pixelforge. If you start
the processes yourself rather than through `composer dev`, start
`php artisan queue:listen` as well.

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `PIXELFORGE_API_KEY` | none | API key for creating generations and reading usage |
| `PIXELFORGE_WEBHOOK_SECRET` | none | Webhook signing secret, starts with `whsec_` |
| `PIXELFORGE_BASE_URL` | `https://pixelforge.promptedit.dev/v1` | API base URL |
| `PIXELFORGE_PRICING_CACHE_SECONDS` | `600` | How long the price list is cached |
| `BILLING_MARKUP_PERCENT` | `25` | Percentage added to Pixelforge's cost |

## Testing and code style

```bash
php artisan test          # PHPUnit: unit and feature suites
vendor/bin/pint           # PHP formatting
npm run lint              # ESLint
npm run format            # Prettier
npx vue-tsc --noEmit      # Type-check the frontend
```

Tests never call the real Pixelforge API: `phpunit.xml` blanks out the keys,
and HTTP calls are faked against the recorded response in
`tests/Fixtures/pixelforge/pricing.json`.

GitHub Actions runs the tests and the linters on every push and pull request
to `main` and `develop`.

## License

MIT
