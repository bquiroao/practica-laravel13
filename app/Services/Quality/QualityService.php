<?php

namespace App\Services\Quality;

use App\Exceptions\DomainRuleException;
use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Calidad de la plataforma:
 *  - defectos (qa-regresion-defectos): abierto → en corrección → corregido → cerrado por QA tras re-ejecutar, o reabierto;
 *  - ejecuciones y quality gate (qa-e2e-ci-despliegue): una versión se aprueba solo si su última ejecución de cada
 *    suite no tiene fallas y no quedan defectos críticos o altos abiertos para ella.
 */
class QualityService
{
    public const TRANSITIONS = [
        'abierto' => ['en_correccion'],
        'en_correccion' => ['corregido'],
        'corregido' => ['cerrado', 'reabierto'],
        'reabierto' => ['en_correccion'],
        'cerrado' => ['reabierto'],
    ];

    public function __construct(private readonly AuditTrail $audit) {}

    /** @param array<string, mixed> $data */
    public function report(CentralUser $actor, Hospital $hospital, array $data, ?string $ip): object
    {
        $next = (int) DB::connection('central')->table('qa_defects')->count() + 1;
        $id = DB::connection('central')->table('qa_defects')->insertGetId([
            'code' => 'DEF-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT), 'title' => trim($data['title']), 'module' => $data['module'],
            'severity' => $data['severity'], 'steps' => trim($data['steps']), 'version' => $data['version'] ?? null, 'reported_by' => $actor->user_id,
        ], 'defect_id');
        $defect = DB::connection('central')->table('qa_defects')->where('defect_id', $id)->first();
        $this->audit->record('calidad', 'DEFECTO_REPORTADO', 'exito', $hospital->hospital_id, $actor->user_id, 'defecto:'.$defect->code, ['severidad' => $defect->severity], $ip);

        return $defect;
    }

    public function transition(CentralUser $actor, Hospital $hospital, string $defectId, string $to, string $note, ?string $ip): object
    {
        $defect = DB::connection('central')->table('qa_defects')->where('defect_id', $defectId)->first()
            ?? throw new DomainRuleException('DEFECTO_NO_ENCONTRADO', 'El defecto no existe.', 404);
        if (! in_array($to, self::TRANSITIONS[$defect->status] ?? [], true)) {
            throw new DomainRuleException('TRANSICION_INVALIDA', "Un defecto «{$defect->status}» no puede pasar a «{$to}».");
        }
        if (in_array($to, ['cerrado', 'reabierto', 'corregido'], true) && mb_strlen(trim($note)) < 10) {
            throw new DomainRuleException('NOTA_REQUERIDA', $to === 'cerrado'
                ? 'Para cerrar, describa la re-ejecución que lo comprobó (al menos 10 caracteres).'
                : 'Describa qué se corrigió o por qué se reabre (al menos 10 caracteres).');
        }
        DB::connection('central')->table('qa_defects')->where('defect_id', $defectId)->update([
            'status' => $to, 'resolution_note' => trim($note) ?: $defect->resolution_note, 'closed_at' => $to === 'cerrado' ? now() : null, 'updated_at' => now(),
        ]);
        $this->audit->record('calidad', 'DEFECTO_'.strtoupper($to), 'exito', $hospital->hospital_id, $actor->user_id, 'defecto:'.$defect->code, ['nota' => trim($note)], $ip);

        return DB::connection('central')->table('qa_defects')->where('defect_id', $defectId)->first();
    }

    /** @param array<string, mixed> $data */
    public function registerRun(?CentralUser $actor, ?Hospital $hospital, array $data, string $source, ?string $ip): object
    {
        if ((int) $data['passed'] + (int) $data['failed'] !== (int) $data['total']) {
            throw new DomainRuleException('CONTEO_INVALIDO', 'Aprobadas más fallidas debe sumar el total.');
        }
        if ((int) ($data['flaky'] ?? 0) > (int) $data['passed']) {
            throw new DomainRuleException('CONTEO_INVALIDO', 'Las inestables se cuentan dentro de las aprobadas.');
        }
        $id = DB::connection('central')->table('qa_runs')->insertGetId([
            'version' => trim($data['version']), 'suite' => trim($data['suite']), 'total' => $data['total'], 'passed' => $data['passed'], 'failed' => $data['failed'],
            'flaky' => $data['flaky'] ?? 0, 'source' => $source, 'commit_sha' => $data['commit_sha'] ?? null, 'registered_by' => $actor?->user_id,
        ], 'run_id');
        $this->audit->record('calidad', 'EJECUCION_REGISTRADA', 'exito', $hospital?->hospital_id, $actor?->user_id, 'version:'.trim($data['version']),
            ['suite' => $data['suite'], 'fallidas' => (int) $data['failed'], 'origen' => $source], $ip);

        return DB::connection('central')->table('qa_runs')->where('run_id', $id)->first();
    }

    /** @return array{version: string, approved: bool, reasons: list<string>, suites: list<object>} */
    public function gate(string $version): array
    {
        $suites = DB::connection('central')->table('qa_runs')->where('version', $version)->orderByDesc('ran_at')->get()->unique('suite')->values();
        $reasons = [];
        if ($suites->isEmpty()) {
            $reasons[] = 'No hay ejecuciones registradas para esta versión.';
        }
        foreach ($suites as $run) {
            if ($run->failed > 0) {
                $reasons[] = "La suite «{$run->suite}» tiene {$run->failed} prueba(s) fallida(s).";
            }
            if ($run->flaky > 0) {
                $reasons[] = "La suite «{$run->suite}» tiene {$run->flaky} prueba(s) inestable(s): revíselas antes de desplegar.";
            }
        }
        $blocking = DB::connection('central')->table('qa_defects')->whereIn('severity', ['critica', 'alta'])->whereNotIn('status', ['cerrado'])
            ->where(fn ($q) => $q->whereNull('version')->orWhere('version', $version))->pluck('code');
        if ($blocking->isNotEmpty()) {
            $reasons[] = 'Defectos críticos o altos sin cerrar: '.$blocking->implode(', ').'.';
        }
        $hard = array_filter($reasons, fn ($r) => ! str_contains($r, 'inestable'));

        return ['version' => $version, 'approved' => $hard === [], 'reasons' => $reasons, 'suites' => $suites->all()];
    }
}
