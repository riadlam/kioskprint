<?php

namespace App\Services\Print;

use App\Models\PrintJob;
use App\Models\PrintPriceBand;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PrintPriceList
{
    /**
     * @return Collection<int, PrintPriceBand>
     */
    public function bands(): Collection
    {
        return PrintPriceBand::query()->orderBy('min_pages')->orderBy('id')->get();
    }

    public function maxPages(): int
    {
        return (int) ($this->bands()->max('max_pages') ?: 100);
    }

    public function priceFor(int $pages): ?float
    {
        $band = $this->bands()->first(
            fn (PrintPriceBand $band) => $pages >= $band->min_pages && $pages <= $band->max_pages
        );

        return $band ? (float) $band->price_per_page : null;
    }

    public function pagesOf(PrintJob $job): int
    {
        $pages = (int) $job->pages()->sum('copies');

        return $pages > 0 ? $pages : (int) $job->page_count;
    }

    public function usesBands(PrintJob $job): bool
    {
        return $job->unit_price === null && $job->print_code_id === null;
    }

    public function tooMany(PrintJob $job): bool
    {
        if (! $this->usesBands($job)) {
            return false;
        }

        $pages = $this->pagesOf($job);

        return $pages > $this->maxPages() || $this->priceFor($pages) === null;
    }

    public function refusal(PrintJob $job): string
    {
        $max = $this->maxPages();

        return $job->locale === 'ar'
            ? "الحد الأقصى هو {$max} صفحة في الطلب الواحد."
            : "Une commande ne peut pas dépasser {$max} pages.";
    }

    /**
     * @param  list<array{id: int, min_pages: int, max_pages: int, price_per_page: float|int|string}>  $rows
     */
    public function replace(array $rows): void
    {
        $bands = collect($rows)
            ->map(fn (array $row) => [
                'id' => (int) $row['id'],
                'min_pages' => (int) $row['min_pages'],
                'max_pages' => (int) $row['max_pages'],
                'price_per_page' => round((float) $row['price_per_page'], 2),
            ])
            ->sortBy('min_pages')
            ->values();

        $cursor = 1;
        foreach ($bands as $band) {
            if ($band['min_pages'] !== $cursor || $band['max_pages'] < $band['min_pages'] || $band['price_per_page'] < 1) {
                throw ValidationException::withMessages([
                    'bands' => 'Bands must start at page 1, touch with no gaps, and each price must be at least 1 DA.',
                ]);
            }
            $cursor = $band['max_pages'] + 1;
        }

        foreach ($bands as $band) {
            PrintPriceBand::query()->whereKey($band['id'])->update([
                'min_pages' => $band['min_pages'],
                'max_pages' => $band['max_pages'],
                'price_per_page' => $band['price_per_page'],
            ]);
        }
    }
}
