<?php

namespace App\Http\Resources;

use App\Models\Hospital\Bed;
use App\Models\Hospital\CareEpisode;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Módulo admisión: el internamiento con su cama actual. El paciente va solo con su código (RNF-03 del módulo):
 * quien admite ya lo eligió en pantalla, y la respuesta no repite datos personales ni clínicos.
 *
 * @mixin CareEpisode
 */
class AdmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $current = $this->stays->firstWhere('ended_at', null) ?? $this->stays->last();

        return [
            'id' => $this->care_episode_id,
            'patient' => ['id' => $this->patient->local_patient_id, 'code' => $this->patient->code],
            'status' => $this->status === 'open' ? 'activa' : 'cerrada',
            'source' => $this->admission_source,
            'source_label' => AdmissionService::SOURCES[$this->admission_source] ?? 'Sin registrar',
            'admitted_at' => $this->started_at->toIso8601String(),
            'discharged_at' => $this->ended_at?->toIso8601String(),
            'discharge_type' => $this->discharge_type ? (CareEpisode::DISCHARGE_TYPES[$this->discharge_type] ?? $this->discharge_type) : null,
            'bed' => $current ? [
                'id' => $current->bed->bed_id, 'code' => $current->bed->code, 'type' => Bed::TYPES[$current->bed->type] ?? $current->bed->type,
                'ward' => ['id' => $current->bed->ward->ward_id, 'code' => $current->bed->ward->code, 'name' => $current->bed->ward->name],
                'since' => $current->started_at->toIso8601String(),
            ] : null,
            'stays' => $this->stays->count(),
        ];
    }
}
