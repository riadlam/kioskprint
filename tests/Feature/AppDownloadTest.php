<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppDownloadTest extends TestCase
{
    public function test_the_install_page_explains_when_the_apk_is_missing(): void
    {
        $path = storage_path('app/apk/metbati.apk');
        if (is_file($path)) {
            File::delete($path);
        }

        $this->get('/app_download')
            ->assertOk()
            ->assertSee('The install file is not on the server yet.');
    }

    public function test_the_install_page_offers_the_apk(): void
    {
        $directory = storage_path('app/apk');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'metbati.apk';
        File::put($path, 'apk-bytes');

        try {
            $this->get('/app_download')
                ->assertOk()
                ->assertSee('Install Metbati');

            $this->get('/app_download/metbati.apk')
                ->assertOk()
                ->assertDownload('metbati.apk');
        } finally {
            File::delete($path);
        }
    }
}
