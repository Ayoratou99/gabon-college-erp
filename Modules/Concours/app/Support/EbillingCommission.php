<?php

declare(strict_types=1);

namespace Modules\Concours\Support;

/**
 * Splits a gross amount collected through eBilling into the commission they
 * retain and the net actually credited to CUK.
 *
 * Single source of truth so the dashboard and the reporting page can never
 * drift apart on the arithmetic or the rate.
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

    /**
     * @return array{gross:int, fees:int, net:int, rate:float, percent:float}
     */
    public static function split(int $gross): array
    {
        // Round the commission to the nearest franc, then derive the net by
        // subtraction so gross = fees + net always holds exactly (no cent lost
        // to independent rounding of both halves).
        $fees = (int) round($gross * self::rate());

        return [
            'gross'   => $gross,
            'fees'    => $fees,
            'net'     => $gross - $fees,
            'rate'    => self::rate(),
            'percent' => self::percent(),
        ];
    }
}
