# Decisions

Choices made where the brief left room, and why. Each one is something we
expect to be asked about.

## Money

**Amounts are integers of nanodollars (1 USD = 1,000,000,000).**
Pixelforge reports money as decimal strings with nine places and warns that
floats drift. Nine places is exactly a nanodollar, so every amount Pixelforge
bills fits in a PHP integer with nothing lost, and adding up a day's costs
gives the same total as `GET /v1/usage`. See `App\Pricing\Nanodollars`.

**Provider cost is computed with bcmath, then stored as nanodollars.**
A rate like `12.50` per 1M tokens makes intermediate values fractional, so
`TokenRates::costOf()` sums in exact decimals and converts once at the end.
With rates of up to three decimal places the result is always whole
nanodollars. If Pixelforge ever publishes finer rates, the remainder is
rounded half up to nine places.

**The 25% markup rounds up to the next nanodollar.**
Cost × 1.25 can land on a quarter, half or three quarters of a nanodollar.
Rounding up means we never charge less than cost plus 25%, and the most a
user overpays is $0.000000001 per generation. See `App\Pricing\Markup`.

**Each generation is charged at the rates it was created with.**
Pixelforge fixes the rates when a generation is created and returns them on
the generation. We store those and use them for the final charge, not the
current price list, so a price change mid-generation can't make our cost
disagree with Pixelforge's.

## Estimates

**The up-front price is a range, and the maximum is a true upper bound.**
Output and reference-image tokens are exact. Text tokens are
`ceil(characters / 4)` ±15%, and reasoning tokens fall in a range per quality,
so the quote gives the fewest and the most a generation can cost. The text
bounds round outward, so the real count is always inside the range. The
maximum is what to check against a user's balance.

**Prompt length counts Unicode characters, not bytes,** as the docs specify.
`日本語` is 3 characters but 9 bytes.

**Token rules come from `GET /v1/pricing`, not constants in the code.**
`TokenTable` and `TokenRates` are built from that response, so a price
change on Pixelforge's side needs no deploy.
