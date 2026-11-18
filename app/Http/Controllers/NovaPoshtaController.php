<?php

namespace App\Http\Controllers;

use App\Services\NovaPoshta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NovaPoshtaController extends Controller
{
    public function cities(Request $request, NovaPoshta $carrier): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:100']);

        return response()->json($carrier->cities($data['q']));
    }

    public function warehouses(Request $request, NovaPoshta $carrier): JsonResponse
    {
        $data = $request->validate([
            'city' => 'required|uuid',
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($carrier->warehouses($data['city'], $data['q'] ?? '', (int) ($data['page'] ?? 1)));
    }
}
