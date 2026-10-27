<?php

namespace App\Http\Controllers;

use App\Services\Print\PrintTemplateCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskTemplateController extends Controller
{
    public function __construct(private PrintTemplateCatalog $catalog) {}

    public function show(Request $request): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json(null, 204, $this->cors());
        }

        return response()->json([
            'templates' => $this->catalog->forClient(),
        ], 200, $this->cors());
    }

    /**
     * @return array<string, string>
     */
    private function cors(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ];
    }
}
