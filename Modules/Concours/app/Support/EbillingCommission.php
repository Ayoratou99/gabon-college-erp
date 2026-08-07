<?php

declare(strict_types=1);

namespace Modules\Concours\Support;

/**
 * Splits amounts collected through eBilling into the commission they retain
 * and the net actually credited to CUK.
 *
 * IMPORTANT — the commission is charged PER TRANSACTION, not on the period
 * total. Applying the rate to an aggregate and rounding once understates the
 * fees, because each payment is rounded on its own:
 *
 *     982 payments × round(10 300 × 2,5 %) = 982 × 258 = 253 356
 *     round(982 × 10 300 × 2,5 %)                      = 252 865   ← wrong
 *
 * That 491 FCFA gap is exactly the kind of drift that makes the bank statement
 * disagree with the dashboard, so totals must sum `feeFor()` row by row —
 * `SUM(ROUND(amount * rate))` in SQL — and pass the result to `fromParts()`.
 */
final class EbillingCommission
{
    /** Fraction retained by eBilling (0.025 = 2,5 %). */
    public static function rate(): float
    {
        return (float) config('concours.ebilling.commission_rate', 0.025);
    }

    /** Rate as a display percentage, e.g. 2.5 for "2,5 %". */
    public static function percent(): float
    {
        return round(self::rate() * 100, 2);
    }

    /** Commission retained on ONE payment, rounded to the franc. */
    public static function feeFor(int $amount): int
    {
        return (int) round($amount * self::rate());
    }

    /**
     * Build the display triplet from a gross total and the fees already summed
     * per transaction (see the class docblock).
     *
     * @return array{gross:int, fees:int, net:int, rate:float, percent:float}
     */
    public static function fromParts(int $gross, int $fees): array
    {
        return [
            'gross'   => $gross,
            'fees'    => $fees,
            'net'     => $gross - $fees,
            'rate'    => self::rate(),
            'percent' => self::percent(),
        ];
    }

    /**
     * Convenience for a SINGLE payment (gross = one transaction). Do not call
     * this with a period total — use fromParts() with per-row summed fees.
     *
     * @return array{gross:int, fees:int, net:int, rate:float, percent:float}
     */
    public static function split(int $gross): array
    {
        return self::fromParts($gross, self::feeFor($gross));
    }

    /**
     * SQL expression summing the per-transaction commission, for aggregate
     * queries. Keeps the rounding rule in one place.
     */
    public static function sqlFeesSum(string $column = 'amount'): string
    {
        return sprintf('COALESCE(SUM(ROUND(%s * %s)), 0)', $column, self::rate());
    }
}
