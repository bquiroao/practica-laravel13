<?php

namespace Tests\Feature\Clinical;

use App\Models\Central\AuditLog;
use App\Models\Hospital\LocalPatient;
use PHPUnit\Framework\Attributes\Group;

/**
 * Módulo alergias clínicas y alerta visual en el expediente: CU-01 a CU-06 y RN-01 a RN-10.
 * Demostración: el primer paciente tiene Penicilina grave (ALTA) y el segundo Ibuprofeno leve (INFORMATIVA).
 */
#[Group('clinical')]
class AllergyTest extends ClinicalTestCase
{
    private function patient(int $n): LocalPatient
    {
        return LocalPatient::query()->orderBy('code')->get()[$n];
    }

    private function url(LocalPatient $p, string $suffix = ''): string
    {
        return "/api/v1/expediente/{$p->local_patient_id}/alergias{$suffix}";
    }

    public function test_the_alert_shows_the_highest_level_and_is_acknowledged_per_user_and_fingerprint(): void
    {
        $doctor = $this->as('medico@nido.test');
        $nurse = $this->as('enfermeria@nido.test');
        $p = $this->patient(0);

        $alert = $this->getJson($this->url($p), $doctor)->assertOk()
            ->assertJsonPath('data.level', 'ALTA')->assertJsonPath('data.requires_acknowledgement', true)
            ->assertJsonPath('data.allergies.0.allergen', 'Penicilina')->json('data');

        // CU-05: el médico reconoce lo que vio; enfermería todavía no (el reconocimiento es por usuario).
        $this->postJson($this->url($p, '/reconocimiento'), ['fingerprint' => $alert['fingerprint']], $doctor)->assertCreated()
            ->assertJsonPath('data.requires_acknowledgement', false)->assertJsonPath('data.acknowledged', true);
        $this->getJson($this->url($p), $nurse)->assertJsonPath('data.requires_acknowledgement', true);
        $this->postJson($this->url($p, '/reconocimiento'), ['fingerprint' => $alert['fingerprint']], $doctor)->assertCreated(); // repetirlo no cambia nada
        $this->assertSame(1, AuditLog::query()->where('accion', 'ALERTA_RECONOCIDA')->count());

        // RN-09: una alergia nueva cambia la huella; la alerta vuelve a pedir reconocimiento y la huella vieja ya no vale.
        $this->postJson($this->url($p), ['allergen_name' => 'Látex', 'allergy_type' => 'allergy', 'severity' => 'life_threatening'], $doctor)->assertCreated()
            ->assertJsonPath('alert.level', 'CRITICA')->assertJsonPath('alert.requires_acknowledgement', true)->assertJsonPath('alert.allergies.0.allergen', 'Látex');
        $this->postJson($this->url($p, '/reconocimiento'), ['fingerprint' => $alert['fingerprint']], $doctor)->assertStatus(409)->assertJsonPath('code', 'ALERTA_CAMBIO');
    }

    public function test_no_record_is_not_the_same_as_no_allergies(): void
    {
        $p = $this->patient(2);
        $alert = $this->getJson($this->url($p), $this->as('medico@nido.test'))->assertOk()
            ->assertJsonPath('data.level', 'SIN_REGISTRO')->assertJsonPath('data.level_label', 'Sin alergias registradas')
            ->assertJsonPath('data.requires_acknowledgement', false)->assertJsonPath('data.allergies', [])->json('data');
        $this->postJson($this->url($p, '/reconocimiento'), ['fingerprint' => $alert['fingerprint']], $this->as('medico@nido.test'))
            ->assertStatus(422)->assertJsonPath('code', 'RECONOCIMIENTO_NO_REQUERIDO');
        $this->getJson($this->url($this->patient(1)), $this->as('medico@nido.test'))->assertJsonPath('data.level', 'INFORMATIVA');
    }

    public function test_registration_validates_each_rule_with_its_code(): void
    {
        $doctor = $this->as('medico@nido.test');
        $p = $this->patient(3);

        $this->postJson($this->url($p), ['allergy_type' => 'allergy', 'severity' => 'mild'], $doctor)->assertStatus(422)->assertJsonValidationErrors('allergen_name'); // RN-01
        $this->postJson($this->url($p), ['allergen_name' => 'Polen', 'allergy_type' => 'otra', 'severity' => 'fuerte'], $doctor)->assertStatus(422)
            ->assertJsonValidationErrors(['allergy_type', 'severity']);                                                                             // RN-02
        // RN-04: una reacción en blanco se guarda como «sin reacción» (opcional), nunca como texto vacío.
        $this->postJson($this->url($p), ['allergen_name' => 'Polen', 'allergy_type' => 'allergy', 'severity' => 'mild', 'reaction' => '   '], $doctor)
            ->assertCreated()->assertJsonPath('data.reaction', null);
        $this->postJson($this->url($p), ['allergen_name' => 'Ácaros', 'allergy_type' => 'allergy', 'severity' => 'mild', 'reaction' => str_repeat('a', 501)], $doctor)
            ->assertStatus(422)->assertJsonValidationErrors('reaction');
        $this->postJson($this->url($p), ['allergen_name' => 'Polen', 'allergy_type' => 'allergy', 'severity' => 'mild', 'reaction' => 123], $doctor)
            ->assertStatus(422)->assertJsonValidationErrors('reaction');                                                                            // D-03
        $this->postJson($this->url($p), ['allergen_name' => 'Polen', 'allergy_type' => 'allergy', 'severity' => 'mild', 'onset_date' => now()->addDays(2)->toDateString()], $doctor)
            ->assertStatus(422)->assertJsonValidationErrors('onset_date');                                                                          // RN-05

        // RN-03: sin distinguir mayúsculas ni espacios.
        $this->postJson($this->url($p), ['allergen_name' => 'Sulfas', 'allergy_type' => 'allergy', 'severity' => 'moderate'], $doctor)->assertCreated()
            ->assertJsonPath('data.verified_by', 'Dr. Carlos Ramírez Soto');                                                                         // RN-07
        $this->postJson($this->url($p), ['allergen_name' => '  SULFAS ', 'allergy_type' => 'allergy', 'severity' => 'mild'], $doctor)->assertStatus(409)->assertJsonPath('code', 'ALERGIA_DUPLICADA');
        // D-01: una con código y nombre, la otra solo con el nombre, son la misma alergia.
        $this->postJson($this->url($p), ['allergen_code' => 'j01ca04', 'allergen_name' => 'Amoxicilina', 'allergy_type' => 'allergy', 'severity' => 'severe'], $doctor)->assertCreated()
            ->assertJsonPath('data.allergen_code', 'J01CA04');
        $this->postJson($this->url($p), ['allergen_name' => 'amoxicilina', 'allergy_type' => 'allergy', 'severity' => 'severe'], $doctor)->assertStatus(409)->assertJsonPath('code', 'ALERGIA_DUPLICADA');
        // El mismo alérgeno con otro tipo sí se permite (la regla es por paciente, tipo y alérgeno).
        $this->postJson($this->url($p), ['allergen_name' => 'Sulfas', 'allergy_type' => 'intolerance', 'severity' => 'mild'], $doctor)->assertCreated();

        // RN-06: solo pacientes activos.
        $inactive = $this->patient(5);
        $inactive->forceFill(['status' => 'inactive'])->save();
        $this->postJson($this->url($inactive), ['allergen_name' => 'Polen', 'allergy_type' => 'allergy', 'severity' => 'mild'], $doctor)->assertStatus(422)->assertJsonPath('code', 'PACIENTE_INACTIVO');
    }

    public function test_an_anaphylactic_reaction_is_critical_even_with_accents(): void
    {
        // Defecto D-02: «anafiláctica» con tilde no se detectaba y la alerta quedaba MODERADA.
        $this->postJson($this->url($this->patient(4)), ['allergen_name' => 'Ibuprofeno', 'allergy_type' => 'allergy', 'severity' => 'moderate',
            'reaction' => 'Reacción anafiláctica'], $this->as('medico@nido.test'))->assertCreated()
            ->assertJsonPath('data.level', 'CRITICA')->assertJsonPath('alert.level', 'CRITICA');
    }

    public function test_resolving_an_allergy_keeps_it_in_history_and_stops_blocking_prescriptions(): void
    {
        $doctor = $this->as('medico@nido.test');
        $p = $this->patient(0);
        $recipe = "/api/v1/expediente/{$p->local_patient_id}/recetas";
        $this->postJson($recipe, ['medication_code' => 'AMOX500', 'dose' => '500 mg', 'frequency' => 'Cada 8 horas'], $doctor)->assertStatus(409);

        $id = $this->getJson($this->url($p), $doctor)->json('data.allergies.0.id');
        $this->patchJson("/api/v1/alergias/{$id}/estado", ['status' => 'resolved', 'reason' => 'x'], $doctor)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->patchJson("/api/v1/alergias/{$id}/estado", ['status' => 'resolved', 'reason' => 'Prueba de provocación negativa'], $doctor)->assertOk()
            ->assertJsonPath('data.status', 'resolved')->assertJsonPath('alert.level', 'SIN_REGISTRO')->assertJsonPath('alert.history.0.id', $id);
        $this->patchJson("/api/v1/alergias/{$id}/estado", ['status' => 'inactive', 'reason' => 'Prueba de provocación negativa'], $doctor)
            ->assertStatus(409)->assertJsonPath('code', 'ALERGIA_NO_ACTIVA');

        // Prescripciones lee la tabla por su cuenta: la alergia resuelta ya no bloquea (RN-10: no se borró).
        $this->postJson($recipe, ['medication_code' => 'AMOX500', 'dose' => '500 mg', 'frequency' => 'Cada 8 horas'], $doctor)->assertCreated();
        $this->assertTrue(AuditLog::query()->where('accion', 'ALERGIA_RESUELTA')->where('detalle->motivo', 'Prueba de provocación negativa')->exists());
    }

    public function test_high_risk_allergies_feed_the_reports_with_the_same_alert_rules(): void
    {
        $today = now('America/Guatemala')->toDateString();
        $query = "/api/v1/reportes/alergias_alto_riesgo?desde={$today}&hasta={$today}";
        // Demostración: Penicilina grave (ALTA) e Ibuprofeno leve (INFORMATIVA) → 1 de 2.
        $this->getJson($query, $this->as('direccion@nido.test'))->assertOk()
            ->assertJsonPath('datos.indicador.numerador', 1)->assertJsonPath('datos.indicador.denominador', 2);

        // Una reacción anafiláctica cuenta como CRÍTICA aunque la severidad sea leve.
        $this->postJson($this->url($this->patient(6)), ['allergen_name' => 'Maní', 'allergy_type' => 'allergy', 'severity' => 'mild', 'reaction' => 'Shock anafiláctico'],
            $this->as('medico@nido.test'))->assertCreated();
        $parts = collect($this->getJson(str_replace('alergias_alto_riesgo?', 'alergias_alto_riesgo/desglose?', $query), $this->as('direccion@nido.test'))
            ->assertOk()->assertJsonPath('datos.indicador.numerador', 2)->json('datos.desglose.partes'))->pluck('valor', 'parte');
        $this->assertSame(['Alta' => 1, 'Crítica' => 1], $parts->sortKeys()->all());
    }

    public function test_only_clinical_roles_see_and_register_allergies(): void
    {
        $p = $this->patient(0);
        $this->getJson($this->url($p), $this->as('recepcion@nido.test'))->assertForbidden()->assertJsonPath('code', 'PERMISO_NO_ASIGNADO');
        $this->postJson($this->url($p), ['allergen_name' => 'Polen', 'allergy_type' => 'allergy', 'severity' => 'mild'], $this->as('bioquimico@nido.test'))->assertForbidden();
        // Enfermería registra y reconoce (alergias.gestionar en el catálogo de permisos).
        $this->postJson($this->url($p), ['allergen_name' => 'Polen', 'allergy_type' => 'allergy', 'severity' => 'mild'], $this->as('enfermeria@nido.test'))->assertCreated();
        $this->getJson('/api/v1/expediente/00000000-0000-4000-8000-000000000000/alergias', $this->as('medico@nido.test'))->assertNotFound()->assertJsonPath('code', 'PACIENTE_NO_ENCONTRADO');
    }
}
