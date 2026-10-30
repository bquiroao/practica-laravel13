<?php

namespace App\Http\Resources;

use App\Models\Hospital\LocalPatient;
use App\Services\Access\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LocalPatient */
class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // El documento completo solo lo ve quien puede editar la ficha; los demás, enmascarado.
        $full = app(PermissionResolver::class)->allows($request->user()->user_id, $request->attributes->get('hospital')->hospital_id, 'pacientes.gestionar');

        return [
            'id' => $this->local_patient_id,
            'code' => $this->code,
            'given_name' => $this->given_name,
            'family_name' => $this->family_name,
            'full_name' => $this->fullName(),
            'birth_date' => $this->birth_date->toDateString(),
            'age' => $this->age(),
            'sex' => $this->sex,
            'status' => $this->status,
            'document_type' => $this->document_type,
            'document_number' => $full ? $this->document_number : $this->maskedDocument(),
            'document_country' => $this->document_country,
            'contact' => $this->contact,
            'address' => $this->address,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
