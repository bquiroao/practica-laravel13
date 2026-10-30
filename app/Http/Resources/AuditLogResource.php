<?php

namespace App\Http\Resources;

use App\Models\Central\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'secuencia' => $this->secuencia,
            'ocurrido_en' => $this->ocurrido_en?->toIso8601String(),
            'usuario_id' => $this->usuario_id,
            'usuario' => $this->usuario_nombre,
            'modulo' => $this->modulo,
            'accion' => $this->accion,
            'resultado' => $this->resultado,
            'recurso' => $this->recurso,
            'ip' => $this->ip,
            'detalle' => $this->detalle,
        ];
    }
}
