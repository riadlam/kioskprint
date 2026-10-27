<?php

namespace App\Services\Print;

use App\Models\PrintJob;

class PrintPricing
{
    public function quote(PrintJob $job): array
    {
        $pages = (int) $job->pages()->sum('copies');
        $unit = $job->unit_price !== null
            ? (float) $job->unit_price
            : (float) config('print.price_per_page');
        $currency = (string) config('print.currency', 'DA');
        $amount = round($pages * $unit, 2);

        return [
            'page_count' => $pages,
            'unit_price' => $unit,
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => $this->format($amount, $currency),
            'unit_formatted' => $this->format($unit, $currency),
        ];
    }

    public function format(float $amount, ?string $currency = null): string
    {
        $currency ??= (string) config('print.currency', 'DA');
        $value = rtrim(rtrim(number_format($amount, 2, '.', ' '), '0'), '.');

        return $value.' '.$currency;
    }
}
