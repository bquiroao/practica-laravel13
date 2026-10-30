<?php

namespace App\Http\Middleware;

use App\Models\Central\Hospital;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** El hospital (tenant) llega en X-Tenant-ID y debe existir y estar activo en la base central. */
class ResolveHospital
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = trim((string) $request->header('X-Tenant-ID'));

        if ($tenantId === '') {
            return response()->json(['message' => 'La cabecera X-Tenant-ID es obligatoria.'], 400);
        }

        $hospital = Str::isUuid($tenantId) ? Hospital::query()->active()->find($tenantId) : null;

        if ($hospital === null) {
            return response()->json(['message' => 'Hospital no encontrado.'], 404);
        }

        $request->attributes->set('hospital', $hospital);
        app()->instance(Hospital::class, $hospital);

        return $next($request);
    }
}
