/**
 * Money arrives from the server as exact decimal strings with up to nine
 * places ("0.467343750"). It is handled as BigInt nanodollars here so that
 * rounding for display never goes through floating point.
 */

const NANODOLLARS_PER_DOLLAR = 1_000_000_000n;

export type Rounding = 'down' | 'up';

export function parseNanodollars(dollars: string): bigint {
    const match = /^(\d+)(?:\.(\d{1,9}))?$/.exec(dollars);

    if (match === null) {
        throw new Error(`Not a dollar amount: "${dollars}"`);
    }

    const [, whole, fraction = ''] = match;

    return BigInt(whole) * NANODOLLARS_PER_DOLLAR + BigInt(fraction.padEnd(9, '0'));
}

/**
 * Format to a fixed number of decimal places, rounding in the direction
 * given. A range rounds its low end down and its high end up, so the
 * displayed range always contains the real price.
 */
export function formatUsd(dollars: string, decimals: number, rounding: Rounding): string {
    const nanodollars = parseNanodollars(dollars);
    const step = 10n ** BigInt(9 - decimals);
    const steps = rounding === 'up' ? (nanodollars + step - 1n) / step : nanodollars / step;
    const rounded = steps * step;

    const whole = rounded / NANODOLLARS_PER_DOLLAR;
    const fraction = (rounded % NANODOLLARS_PER_DOLLAR).toString().padStart(9, '0').slice(0, decimals);

    return decimals > 0 ? `$${whole}.${fraction}` : `$${whole}`;
}

export function formatUsdRange(min: string, max: string, decimals = 4): string {
    const low = formatUsd(min, decimals, 'down');
    const high = formatUsd(max, decimals, 'up');

    return low === high ? low : `${low} – ${high}`;
}
