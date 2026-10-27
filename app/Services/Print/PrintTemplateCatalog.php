<?php

namespace App\Services\Print;

use App\Models\PrintTemplate;
use Illuminate\Support\Facades\Storage;

class PrintTemplateCatalog
{
    /**
     * @return list<array{id: string, file: string, title_ar: string, title_fr: string, pages: int}>
     */
    public function all(): array
    {
        return PrintTemplate::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (PrintTemplate $template) => [
                'id' => $template->slug,
                'file' => $template->path,
                'title_ar' => $template->title_ar,
                'title_fr' => $template->title_fr,
                'pages' => (int) $template->pages,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_column($this->all(), 'id');
    }

    /**
     * @return array{id: string, file: string, title_ar: string, title_fr: string, pages: int}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param  array{id: string, file: string, title_ar: string, title_fr: string, pages: int}  $template
     */
    public function path(array $template): string
    {
        return Storage::disk('public')->path($template['file']);
    }

    /**
     * @return list<array{id: string, title_ar: string, title_fr: string, pages: int}>
     */
    public function forClient(): array
    {
        return array_map(fn (array $template) => [
            'id' => $template['id'],
            'title_ar' => $template['title_ar'],
            'title_fr' => $template['title_fr'],
            'pages' => $template['pages'],
        ], $this->all());
    }

    public function deleteFile(PrintTemplate $template): void
    {
        if ($template->path !== '') {
            Storage::disk('public')->delete($template->path);
        }
    }
}
