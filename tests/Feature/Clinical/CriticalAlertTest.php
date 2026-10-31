<?php

namespace Tests\Feature\Clinical;

use App\Events\VitalSignsOutOfRange;
use App\Exceptions\DomainRuleException;
use App\Models\Central\AuditLog;
use App\Models\Central\CentralUser;
use App\Models\Hospital\CriticalAlert;
use App\Models\Hospital\CriticalAlertEvent;
use App\Models\Hospital\CriticalAlertNotification;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\LocalUserRef;
use App\Services\Alerts\Channels\AlertChannel;
use App\Services\Alerts\CriticalAlertService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;

/**
 * Módulo alertas críticas y notificaciones internas: CU-01 a CU-09, RN-01 a RN-11, criterios CA-01 a CA-16 y defectos
 * D-03 a D-05. El reloj se controla con travelTo (RNF-04): ninguna prueba espera minutos reales.
 * Demostración: nivel 0 = enfermería (+ médico tratante), nivel 1 = médicos, nivel 2 = dirección médica.
 */
#[Group('clinical')]
class CriticalAlertTest extends ClinicalTestCase
{
    private function patient(string $family): LocalPatient
    {
        return LocalPatient::query()->where('family_name', $family)->firstOrFail();
    }

    /** Una toma con saturación crítica del paciente internado: el módulo de signos vitales dispara el evento. */
    private function criticalVitals(): CriticalAlert
    {
        $p = $this->patient('Hernández Castillo');
        $this->postJson("/api/v1/expediente/{$p->local_patient_id}/signos", ['values' => ['heart_rate' => 88, 'oxygen_saturation' => 86]],
            $this->as('enfermeria@nido.test'))->assertCreated();

        return CriticalAlert::query()->latest('created_at')->firstOrFail();
    }

    private function events(CriticalAlert $alert): array
    {
        return CriticalAlertEvent::query()->where('critical_alert_id', $alert->critical_alert_id)->orderBy('occurred_at')->orderBy('critical_alert_event_id')
            ->pluck('event')->all();
    }

    private function recipients(CriticalAlert $alert, int $level): array
    {
        return CriticalAlertNotification::query()->where('critical_alert_id', $alert->critical_alert_id)->where('level', $level)
            ->join('local_user_refs as u', 'u.local_user_ref_id', '=', 'recipient_user_ref_id')->orderBy('u.display_name')->pluck('u.display_name')->all();
    }

    private function escalate(): void
    {
        Artisan::call('nido:alertas-escalar', ['--hospital' => 'HN-CEN']);
    }

    public function test_a_critical_vital_sign_creates_one_alert_and_notifies_level_0(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $alert = $this->criticalVitals();

        // CA-01, CA-05: CRITICA, enviada al nivel 0, con el plazo de 15 minutos.
        $this->assertSame(['CRITICA', 'ENVIADA', 0, 'signos_vitales'], [$alert->priority, $alert->status, $alert->level, $alert->source]);
        $this->assertSame(now()->addMinutes(15)->toIso8601String(), $alert->deadline_at->toIso8601String());
        $this->assertSame(['creada', 'enviada'], $this->events($alert));
        $this->assertSame('sistema:signos_vitales', CriticalAlertEvent::query()->where('event', 'creada')->value('actor'));
        $this->assertSame(['Enfermería (demo)'], $this->recipients($alert, 0));
        // RNF-02: la alerta lleva el código del paciente, nunca su nombre.
        $this->assertStringContainsString('Saturación', $alert->description);
        $this->assertStringNotContainsString('Hernández', $alert->title.$alert->description);

        $nurse = $this->as('enfermeria@nido.test');
        $this->getJson('/api/v1/alertas/resumen', $nurse)->assertOk()->assertJsonPath('data.pendientes', 1);
        $this->getJson('/api/v1/alertas?vista=mias', $nurse)->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_acknowledge', true)
            ->assertJsonPath('data.0.patient.code', $alert->patient_code)->assertJsonPath('data.0.level_label', 'Enfermería y médico tratante');
        $this->getJson('/api/v1/alertas/resumen', $this->as('medico@nido.test'))->assertJsonPath('data.pendientes', 0)->assertJsonPath('data.abiertas', 1);

        // CA-03 y RN-02: el mismo evento otra vez no crea otra alerta.
        $vital = $alert->source_id;
        event(new VitalSignsOutOfRange($this->hospital()->hospital_id, $alert->local_patient_id, $alert->patient_code, $alert->care_episode_id,
            $vital, [['signo' => 'oxygen_saturation', 'etiqueta' => 'Saturación', 'valor' => 86.0, 'nivel' => 'critica']]));
        $this->assertSame(1, CriticalAlert::query()->count());
        $this->assertTrue(AuditLog::query()->where('accion', 'ALERTA_CREADA')->exists());
    }

    public function test_an_incomplete_event_is_rejected_and_nothing_is_saved(): void
    {
        // CA-02 y RN-01: sin código de paciente no hay alerta.
        try {
            app(CriticalAlertService::class)->register($this->hospital(), ['patient_id' => $this->patient('Gómez Ruiz')->local_patient_id, 'patient_code' => '',
                'source' => 'laboratorio', 'source_id' => 'LAB-77801', 'priority' => 'CRITICA', 'title' => 'Potasio', 'description' => 'K 6.8 mmol/L']);
            $this->fail('Debía rechazarse');
        } catch (DomainRuleException $e) {
            $this->assertSame(['ALERTA_INVALIDA', 'patient_code'], [$e->errorCode, $e->details['campo']]);
        }
        $this->assertSame(0, CriticalAlert::query()->count());
    }

    public function test_only_a_notified_person_acknowledges_and_twice_changes_nothing(): void
    {
        $alert = $this->criticalVitals();
        $url = "/api/v1/alertas/{$alert->critical_alert_id}/acuse";

        // CA-09 y RN-07: quien no recibió la alerta no la acusa.
        $this->postJson($url, [], $this->as('medico@nido.test'))->assertForbidden()->assertJsonPath('code', 'ALERTA_NO_NOTIFICADA');
        $this->postJson($url, [], $this->as('laboratorio@nido.test'))->assertForbidden();
        $this->postJson($url, [], $this->as('recepcion@nido.test'))->assertForbidden();

        // CA-07 y CA-08: queda ACUSADA con un solo evento, aunque se acuse dos veces.
        $nurse = $this->as('enfermeria@nido.test');
        $this->postJson($url, [], $nurse)->assertOk()->assertJsonPath('data.status', 'ACUSADA')->assertJsonPath('data.acknowledged_by', 'Enfermería (demo)')
            ->assertJsonPath('data.can_acknowledge', false);
        $this->postJson($url, [], $nurse)->assertOk()->assertJsonPath('data.status', 'ACUSADA');
        $this->assertSame(['creada', 'enviada', 'acusada'], $this->events($alert));
        $this->assertSame(1, AuditLog::query()->where('accion', 'ALERTA_ACUSADA')->count());
        $this->getJson('/api/v1/alertas/resumen', $nurse)->assertJsonPath('data.pendientes', 0);
        $this->getJson('/api/v1/alertas/00000000-0000-4000-8000-000000000000', $nurse)->assertNotFound()->assertJsonPath('code', 'ALERTA_NO_ENCONTRADA');
    }

    public function test_an_unacknowledged_alert_escalates_level_by_level_when_its_deadline_passes(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $alert = $this->criticalVitals();

        // CA-11: a las 10:14 todavía no vence.
        $this->travelTo(now()->setTime(10, 14));
        $this->escalate();
        $this->assertSame([0, 'ENVIADA'], [$alert->refresh()->level, $alert->status]);

        // CA-10: a las 10:15 sube al nivel 1 y avisa a los médicos.
        $this->travelTo(now()->setTime(10, 15));
        $this->escalate();
        $this->assertSame([1, 'ESCALADA'], [$alert->refresh()->level, $alert->status]);
        $this->assertSame(['Dr. Carlos Ramírez Soto', 'Dr. Roberto Paz Orellana', 'Dra. Ana Lucía Méndez'], $this->recipients($alert, 1));

        // Nivel 2: dirección médica. CA-13: vencido en el máximo, «escalamiento agotado» una sola vez y el nivel sigue en 2.
        $this->travelTo(now()->setTime(10, 30));
        $this->escalate();
        $this->assertSame(2, $alert->refresh()->level);
        $this->assertCount(1, $this->recipients($alert, 2));
        $this->travelTo(now()->setTime(10, 45));
        $this->escalate();
        $this->escalate();
        $this->assertSame([2, true], [$alert->refresh()->level, $alert->escalation_exhausted]);
        $this->assertSame(['creada', 'enviada', 'escalada', 'enviada', 'escalada', 'enviada', 'escalamiento_agotado'], $this->events($alert));

        // Una alerta escalada todavía se acusa, también por quien la recibió en un nivel anterior.
        $this->postJson("/api/v1/alertas/{$alert->critical_alert_id}/acuse", [], $this->as('direccion@nido.test'))->assertOk()->assertJsonPath('data.status', 'ACUSADA');
        $this->assertSame(2, $alert->refresh()->acknowledged_level);
    }

    public function test_an_acknowledged_alert_is_not_escalated(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $alert = $this->criticalVitals();
        // CA-12: acusada a las 10:05; a las 10:30 la revisión no la toca.
        $this->travelTo(now()->setTime(10, 5));
        $this->postJson("/api/v1/alertas/{$alert->critical_alert_id}/acuse", [], $this->as('enfermeria@nido.test'))->assertOk();
        $this->travelTo(now()->setTime(10, 30));
        $this->escalate();
        $this->assertSame([0, 'ACUSADA', null], [$alert->refresh()->level, $alert->status, $alert->deadline_at]);
    }

    public function test_only_doctors_and_management_close_an_acknowledged_alert_with_a_note(): void
    {
        $alert = $this->criticalVitals();
        $url = "/api/v1/alertas/{$alert->critical_alert_id}";
        $doctor = $this->as('medico@nido.test');

        // D-03: enfermería no cierra.
        $this->postJson("{$url}/cierre", ['note' => 'Se colocó oxígeno por cánula'], $this->as('enfermeria@nido.test'))->assertForbidden();
        // RN-09: primero se acusa.
        $this->postJson("{$url}/cierre", ['note' => 'Se colocó oxígeno por cánula'], $doctor)->assertStatus(409)->assertJsonPath('code', 'ALERTA_NO_ACUSADA');
        $this->postJson("{$url}/acuse", [], $this->as('enfermeria@nido.test'))->assertOk();
        // CA-14: sin nota se rechaza; con nota queda CERRADA.
        $this->postJson("{$url}/cierre", [], $doctor)->assertUnprocessable()->assertJsonValidationErrors(['note']);
        $this->postJson("{$url}/cierre", ['note' => 'Se colocó oxígeno por cánula a 2 L/min y mejoró'], $doctor)->assertOk()
            ->assertJsonPath('data.status', 'CERRADA')->assertJsonPath('data.closed_by', 'Dr. Carlos Ramírez Soto');
        $this->postJson("{$url}/cierre", ['note' => 'Otra vez'], $doctor)->assertStatus(409)->assertJsonPath('code', 'ALERTA_CERRADA');

        // CA-15: el historial, en orden, con actor y momento.
        $history = $this->getJson($url, $doctor)->assertOk()->json('data.events');
        $this->assertSame(['creada', 'enviada', 'acusada', 'cerrada'], array_column($history, 'event'));
        $this->assertSame(['sistema:signos_vitales', 'sistema:notificaciones', 'Enfermería (demo)', 'Dr. Carlos Ramírez Soto'], array_column($history, 'actor'));
        $this->assertNotEmpty($history[3]['occurred_at']);

        // RN-10: el historial solo crece, ni saltándose el servicio.
        $this->expectException(QueryException::class);
        DB::connection('hospital')->table('critical_alert_events')->where('critical_alert_id', $alert->critical_alert_id)->delete();
    }

    public function test_a_failing_channel_retries_three_times_and_escalates_at_once(): void
    {
        // CA-06, RN-08 y RNF-05: el canal falla siempre para enfermería.
        $nurse = CentralUser::query()->where('email', 'enfermeria@nido.test')->value('user_id');
        $this->app->bind(AlertChannel::class, fn () => new class($nurse) implements AlertChannel
        {
            public function __construct(private readonly string $failFor) {}

            public function deliver(CriticalAlert $alert, LocalUserRef $recipient): bool
            {
                return $recipient->central_user_id !== $this->failFor;
            }
        });

        $alert = $this->criticalVitals();
        $failed = CriticalAlertNotification::query()->where('critical_alert_id', $alert->critical_alert_id)->where('level', 0)->firstOrFail();
        $this->assertSame([3, false], [$failed->attempts, $failed->delivered]);
        $this->assertSame([1, 'ESCALADA'], [$alert->level, $alert->status], 'La alerta no se pierde: se escala de inmediato');
        $this->assertSame(['creada', 'entrega_fallida', 'escalada', 'enviada'], $this->events($alert));
        $this->postJson("/api/v1/alertas/{$alert->critical_alert_id}/acuse", [], $this->as('enfermeria@nido.test'))->assertForbidden();
    }

    public function test_a_level_without_staff_passes_to_the_next(): void
    {
        // CA-04 y RN-03: enfermería no está disponible.
        CentralUser::query()->where('email', 'enfermeria@nido.test')->update(['status' => 'inactive']);
        $p = $this->patient('Gómez Ruiz');
        $alert = app(CriticalAlertService::class)->register($this->hospital(), ['patient_id' => $p->local_patient_id, 'patient_code' => $p->code,
            'source' => 'laboratorio', 'source_id' => 'LAB-77801', 'priority' => 'ALTA', 'title' => 'Potasio alto', 'description' => 'K 6.8 mmol/L']);

        $this->assertSame(['creada', 'sin_destinatarios', 'escalada', 'enviada'], $this->events($alert));
        $this->assertSame([1, 'ESCALADA'], [$alert->level, $alert->status]);
        $this->assertSame(now()->addMinutes(30)->toIso8601String(), $alert->deadline_at->toIso8601String(), 'ALTA: 30 minutos');
    }

    public function test_a_validated_critical_lab_result_alerts_the_ordering_doctor_and_nursing(): void
    {
        $p = $this->patient('Gómez Ruiz');
        $doctor = $this->as('medico@nido.test');
        $item = $this->postJson("/api/v1/expediente/{$p->local_patient_id}/laboratorio", ['tests' => ['K'], 'indication' => 'Control de potasio', 'priority' => 'urgent'], $doctor)
            ->assertCreated()->json('data.items.0.id');
        $lab = $this->as('laboratorio@nido.test');
        $this->postJson("/api/v1/laboratorio/items/{$item}/recibir", [], $lab)->assertOk();
        $result = $this->postJson("/api/v1/laboratorio/items/{$item}/resultado", ['value' => 6.8, 'unit' => 'mmol/L'], $lab)->assertCreated()->json('data.id');
        $this->postJson("/api/v1/laboratorio/resultados/{$result}/revision", ['approve' => true], $this->as('bioquimico@nido.test'))->assertOk();

        $alert = CriticalAlert::query()->where('source', 'laboratorio')->firstOrFail();
        $this->assertSame($result, $alert->source_id, 'El id de origen es el resultado validado (RN-02)');
        $this->assertSame(['CRITICA', 'Resultado crítico: Potasio', 'Potasio (K): 6.8 mmol/L, crítico alto.'], [$alert->priority, $alert->title, $alert->description]);
        $this->assertSame(['Dr. Carlos Ramírez Soto', 'Enfermería (demo)'], $this->recipients($alert, 0));
        $this->postJson("/api/v1/alertas/{$alert->critical_alert_id}/acuse", [], $doctor)->assertOk()->assertJsonPath('data.status', 'ACUSADA');
    }

    public function test_acknowledgement_deadlines_are_configurable_between_5_and_60_minutes(): void
    {
        $admin = $this->as('admin.hospital@nido.test');

        // CA-16 y RN-11.
        $this->putJson('/api/v1/alertas/politicas', ['CRITICA' => 4, 'ALTA' => 61], $admin)->assertUnprocessable()->assertJsonValidationErrors(['CRITICA', 'ALTA']);
        $this->putJson('/api/v1/alertas/politicas', ['CRITICA' => 5, 'ALTA' => 60], $admin)->assertOk()
            ->assertJsonPath('data.0', ['priority' => 'CRITICA', 'label' => 'Crítica', 'ack_minutes' => 5]);
        $this->putJson('/api/v1/alertas/politicas', ['CRITICA' => 10, 'ALTA' => 20], $this->as('enfermeria@nido.test'))->assertForbidden();
        $this->assertTrue(AuditLog::query()->where('accion', 'PLAZOS_ACTUALIZADOS')->exists());

        $this->travelTo(now()->setTime(10, 0));
        $this->assertSame(now()->addMinutes(5)->toIso8601String(), $this->criticalVitals()->deadline_at->toIso8601String());
    }

    public function test_the_acknowledged_on_time_indicator(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $first = $this->criticalVitals();
        $this->postJson("/api/v1/alertas/{$first->critical_alert_id}/acuse", [], $this->as('enfermeria@nido.test'))->assertOk();
        $p = $this->patient('Gómez Ruiz');
        app(CriticalAlertService::class)->register($this->hospital(), ['patient_id' => $p->local_patient_id, 'patient_code' => $p->code,
            'source' => 'laboratorio', 'source_id' => 'LAB-1', 'priority' => 'CRITICA', 'title' => 'Potasio', 'description' => 'K 6.8 mmol/L']);

        $day = now('America/Guatemala')->toDateString();
        $this->getJson("/api/v1/reportes/alertas_a_tiempo?desde={$day}&hasta={$day}", $this->as('direccion@nido.test'))->assertOk()
            ->assertJsonPath('datos.indicador.numerador', 1)->assertJsonPath('datos.indicador.denominador', 2);
    }
}
