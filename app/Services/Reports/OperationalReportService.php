<?php

namespace App\Services\Reports;

use App\Exceptions\DomainRuleException;
use App\Models\Hospital\Appointment;
use App\Models\Hospital\CareEpisode;
use App\Models\Hospital\LabOrderItem;
use App\Models\Hospital\LocalPatient;
use App\Models\Hospital\Prescription;
use Carbon\CarbonImmutable;

/**
 * Módulo reportes operativos exportables: filtros validados (rango de hasta 92 días), vista previa y exportación.
 * Las filas se ordenan de forma estable para que la misma consulta con la misma fecha de corte dé el mismo hash.
 */
class OperationalReportService
{
    public const MAX_DAYS = 92;

    public const TYPES = [
        'citas' => 'Citas por fecha',
        'pacientes' => 'Pacientes registrados',
        'internamientos' => 'Internamientos y altas',
        'laboratorio' => 'Pruebas de laboratorio',
        'recetas' => 'Recetas emitidas',
    ];

    /** @return array{type: string, title: string, columns: list<string>, rows: list<list<string>>, cutoff: string, from: string, to: string} */
    public function build(string $type, string $from, string $to, string $tz): array
    {
        if (! array_key_exists($type, self::TYPES)) {
            throw new DomainRuleException('REPORTE_NO_EXISTE', 'Ese reporte no existe.', 404);
        }
        $start = CarbonImmutable::parse($from, $tz)->startOfDay();
        $end = CarbonImmutable::parse($to, $tz)->endOfDay();
        if ($end < $start) {
            throw new DomainRuleException('RANGO_INVALIDO', 'La fecha «hasta» debe ser igual o posterior a «desde».');
        }
        if ($start->diffInDays($end) > self::MAX_DAYS) {
            throw new DomainRuleException('RANGO_INVALIDO', 'El rango puede ser de hasta '.self::MAX_DAYS.' días.');
        }
        $cutoff = CarbonImmutable::now();
        $range = [$start->utc(), $end->min($cutoff)->utc()];
        $fmt = fn ($d) => $d ? CarbonImmutable::parse($d)->setTimezone($tz)->format('Y-m-d H:i') : '';

        [$columns, $rows] = match ($type) {
            'citas' => [['Fecha y hora', 'Paciente', 'Código', 'Médico', 'Especialidad', 'Estado', 'Motivo'],
                Appointment::query()->with(['patient', 'doctor.userRef', 'specialty'])->whereBetween('starts_at', $range)->orderBy('starts_at')->orderBy('appointment_id')->get()
                    ->map(fn ($a) => [$fmt($a->starts_at), $a->patient->fullName(), $a->patient->code, $a->doctor->name(), $a->specialty->name,
                        ['scheduled' => 'Programada', 'confirmed' => 'Confirmada', 'completed' => 'Atendida', 'cancelled' => 'Cancelada'][$a->status], $a->reason])],
            'pacientes' => [['Registrado', 'Código', 'Nombre', 'Edad', 'Sexo', 'Estado'],
                LocalPatient::query()->whereBetween('created_at', $range)->orderBy('created_at')->orderBy('code')->get()
                    ->map(fn ($p) => [$fmt($p->created_at), $p->code, $p->fullName(), (string) $p->age(), LocalPatient::SEXES[$p->sex], LocalPatient::STATUSES[$p->status]])],
            'internamientos' => [['Ingreso', 'Egreso', 'Paciente', 'Código', 'Motivo', 'Tipo de alta'],
                CareEpisode::query()->with('patient')->where('type', 'inpatient')->whereBetween('started_at', $range)->orderBy('started_at')->orderBy('care_episode_id')->get()
                    ->map(fn ($e) => [$fmt($e->started_at), $fmt($e->ended_at) ?: 'Internado', $e->patient->fullName(), $e->patient->code, $e->reason,
                        CareEpisode::DISCHARGE_TYPES[$e->discharge_type] ?? ''])],
            'laboratorio' => [['Pedido', 'Paciente', 'Prueba', 'Muestra', 'Estado', 'Resultado'],
                LabOrderItem::query()->with(['order.patient', 'currentResult'])->whereBetween('created_at', $range)->orderBy('created_at')->orderBy('lab_order_item_id')->get()
                    ->map(fn ($i) => [$fmt($i->created_at), $i->order->patient->fullName(), $i->test_name, (string) $i->specimen_code,
                        ['pending' => 'Pendiente', 'received' => 'En proceso', 'rejected' => 'Rechazada', 'resulted' => 'Por validar', 'validated' => 'Validado'][$i->status],
                        $i->status === 'validated' && $i->currentResult ? $i->currentResult->value_numeric.' '.$i->unit : ''])],
            'recetas' => [['Emitida', 'Paciente', 'Medicamento', 'Dosis', 'Frecuencia', 'Médico', 'Estado'],
                Prescription::query()->with(['patient', 'prescriber'])->whereBetween('issued_at', $range)->orderBy('issued_at')->orderBy('prescription_id')->get()
                    ->map(fn ($p) => [$fmt($p->issued_at), $p->patient->fullName(), $p->medication_name, $p->dose, $p->frequency, (string) $p->prescriber?->display_name,
                        $p->status === 'issued' ? 'Vigente' : 'Anulada'])],
        };

        return ['type' => $type, 'title' => self::TYPES[$type], 'columns' => $columns, 'rows' => $rows->map(fn ($r) => array_map('strval', $r))->values()->all(),
            'cutoff' => $cutoff->toIso8601String(), 'from' => $start->toDateString(), 'to' => $end->toDateString()];
    }

    /** CSV con BOM UTF-8 (Excel abre bien las tildes) y separador coma. */
    public function csv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $report['columns'], ',', '"', '');
        foreach ($report['rows'] as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
