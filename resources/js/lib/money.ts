const NANOS_PER_DOLLAR = 1_000_000_000;

// Every amount arrives from the server as whole nanodollars, so formatting never touches a float.
function decimal(nanos: number): {
    sign: string;
    dollars: string;
    fraction: string;
} {
    const absolute = Math.abs(nanos);
    const fraction = absolute % NANOS_PER_DOLLAR;

    return {
        sign: nanos < 0 ? '-' : '',
        dollars: ((absolute - fraction) / NANOS_PER_DOLLAR).toLocaleString(
            'en-US',
        ),
        fraction: String(fraction).padStart(9, '0'),
    };
}

/** The exact amount with at least two decimals: 381887500 becomes "$0.3818875". */
export function formatUsd(nanos: number): string {
    const { sign, dollars, fraction } = decimal(nanos);

    return `${sign}$${dollars}.${fraction.replace(/0+$/, '').padEnd(2, '0')}`;
}

/** All nine decimals, the way Pixelforge prints its own costs. */
export function formatUsdFull(nanos: number): string {
    const { sign, dollars, fraction } = decimal(nanos);

    return `${sign}$${dollars}.${fraction}`;
}

/** Rounded to a hundredth of a cent, for estimates where more digits would be false precision. */
export function formatUsdApprox(nanos: number): string {
    const { sign, dollars, fraction } = decimal(
        Math.round(nanos / 100_000) * 100_000,
    );

    return `${sign}$${dollars}.${fraction.slice(0, 4)}`;
}
