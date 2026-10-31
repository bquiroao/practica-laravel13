<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Hospital;
use Illuminate\Http\JsonResponse;

class HospitalController extends Controller
{
    /** Público: hospitales activos para la pantalla de inicio de sesión. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Hospital::query()->active()->orderBy('name')->get(['hospital_id', 'code', 'name'])
                ->map(fn (Hospital $h) => ['id' => $h->hospital_id, 'code' => $h->code, 'name' => $h->name]),
        ]);
    }
}
