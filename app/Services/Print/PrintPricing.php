<?php

namespace App\Services\Print;

use App\Models\PrintJob;

class PrintPricing
{
    public function __construct(private PrintPriceList $prices) {}

    public function quote(PrintJob $job): array
    {
        $pages = $this->prices->pagesOf($job);
        $unit = $job->unit_price !== null
            ? (float) $job->unit_price
            : (float) ($this->prices->priceFor($pages) ?? config('print.price_per_page'));
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
