<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\Central\AuditLog;
use App\Services\Audit\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Módulo gobernanza-auditoría: consulta filtrada de la bitácora del hospital y verificación de su cadena. */
class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'modulo' => ['nullable', 'string', 'max:64'],
            'accion' => ['nullable', 'string', 'max:64'],
            'resultado' => ['nullable', 'in:exito,denegado,error'],
            'usuario_id' => ['nullable', 'uuid'],
            'recurso' => ['nullable', 'string', 'max:128'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $logs = AuditLog::query()
            ->leftJoin('central_users', 'central_users.user_id', '=', 'audit_logs.usuario_id')
            ->select('audit_logs.*', 'central_users.display_name as usuario_nombre')
            ->where('audit_logs.hospital_id', $request->attributes->get('hospital')->hospital_id)
            ->when($filters['modulo'] ?? null, fn ($q, $v) => $q->where('modulo', $v))
            ->when($filters['accion'] ?? null, fn ($q, $v) => $q->where('accion', $v))
            ->when($filters['resultado'] ?? null, fn ($q, $v) => $q->where('resultado', $v))
            ->when($filters['usuario_id'] ?? null, fn ($q, $v) => $q->where('usuario_id', $v))
            ->when($filters['recurso'] ?? null, fn ($q, $v) => $q->where('recurso', $v))
            ->when($filters['desde'] ?? null, fn ($q, $v) => $q->where('ocurrido_en', '>=', $v))
            ->when($filters['hasta'] ?? null, fn ($q, $v) => $q->where('ocurrido_en', '<=', $v))
            ->orderByDesc('ocurrido_en')->orderByDesc('audit_logs.id')
            ->paginate($filters['per_page'] ?? 25);

        return AuditLogResource::collection($logs);
    }

    public function verify(Request $request, AuditTrail $audit): JsonResponse
    {
        return response()->json($audit->verify($request->attributes->get('hospital')->hospital_id));
    }
}
