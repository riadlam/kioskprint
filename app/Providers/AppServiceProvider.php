<?php

namespace App\Providers;

use App\Services\Print\FakeKioskPrinter;
use App\Services\Print\KioskPrinter;
use App\Services\Print\WindowsKioskPrinter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KioskPrinter::class, function () {
            if (config('print.driver') === 'windows') {
                return new WindowsKioskPrinter(
                    (string) config('print.sumatra_path'),
                    (string) config('print.printer_name'),
                    (int) config('print.page_timeout'),
                );
            }

            return new FakeKioskPrinter;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
