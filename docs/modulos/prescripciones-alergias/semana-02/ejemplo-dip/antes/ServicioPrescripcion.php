<?php

declare(strict_types=1);

namespace Prescripciones\Antes;

/**
 * DISEÑO ANTES (sin DIP). La política de alto nivel —«emitir con verificación previa de alergias»—
 * depende directamente de los detalles de bajo nivel:
 *  1. crea su propia conexión PDO y escribe el SQL de `allergies` y de `prescripciones_antes`;
 *  2. el catálogo, los grupos de alérgenos, los pacientes y los permisos son constantes de la clase;
 *  3. escribe la traza con file_put_contents y toma la hora con date();
 *  4. y por eso la falla del detalle se «maneja» dentro de la política: si la consulta de alergias
 *     falla, sigue con una lista vacía (fail-open). La regla R-07 queda a merced de un try/catch.
 * Es funcionalmente equivalente al DESPUÉS en el camino normal (lo prueba PAR-01).
 */
final class ServicioPrescripcion
{
    private const CATALOGO = [
        'AMOX500' => ['J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000, ['oral'], true],
        'CEFA500' => ['J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000, ['oral'], true],
        'PARA500' => ['N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000, ['oral', 'rectal'], true],
        'IBU400' => ['M01AE01', 'Ibuprofeno', 'aines', 'mg', 2400, ['oral'], true],
        'RANI150' => ['A02BA02', 'Ranitidina', 'antih2', 'mg', 300, ['oral'], false],
    ];
    private const GRUPOS_ALERGENOS = ['J01CA04' => 'betalactamicos', 'J01CE01' => 'betalactamicos', 'penicilina' => 'betalactamicos',
        'N02BA01' => 'aines', 'M01AE01' => 'aines'];
    private const PACIENTES = ['hosp-norte' => ['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'], 'hosp-sur' => ['P-900']];
    private const PERMISOS = [
        'dr-lopez' => ['hosp-norte' => ['prescripcion.emitir', 'prescripcion.override']],
        'dr-ruiz' => ['hosp-norte' => ['prescripcion.emitir']],
        'dr-sur' => ['hosp-sur' => ['prescripcion.emitir', 'prescripcion.override']],
    ];

    public function __construct(
        private readonly string $dsn,
        private readonly ?string $usuarioBd,
        private readonly string $archivoTraza,
    ) {
    }

    /**
     * @param array<string, mixed> $s usuario, hospital, paciente, clave, items, justificacion, confirma_sin_alergias
     * @return array{estado: int, codigo: string}
     */
    public function emitir(array $s): array
    {
        $pdo = new \PDO($this->dsn, $this->usuarioBd, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        if (!in_array('prescripcion.emitir', self::PERMISOS[$s['usuario']][$s['hospital']] ?? [], true)) {
            return ['estado' => 403, 'codigo' => 'PERMISO_DENEGADO'];
        }
        $huella = hash('sha256', (string) json_encode([$s['paciente'], $s['items'], $s['justificacion'] ?? null, $s['confirma_sin_alergias'] ?? false]));
        $q = $pdo->prepare('SELECT id, huella FROM prescripciones_antes WHERE hospital = ? AND clave = ?');
        $q->execute([$s['hospital'], $s['clave']]);
        if ($previa = $q->fetch(\PDO::FETCH_ASSOC)) {
            return $previa['huella'] === $huella ? ['estado' => 200, 'codigo' => 'YA_EMITIDA'] : ['estado' => 409, 'codigo' => 'CLAVE_REUTILIZADA'];
        }
        if (!in_array($s['paciente'], self::PACIENTES[$s['hospital']] ?? [], true)) {
            return ['estado' => 404, 'codigo' => 'NO_ENCONTRADO'];
        }

        $campos = [];
        $dosis = [];
        foreach (array_values($s['items']) as $i => $it) {
            $m = self::CATALOGO[$it['medicamento'] ?? ''] ?? null;
            if ($m === null || !$m[6]) {
                $campos[] = "items[$i].medicamento";
                continue;
            }
            if (!is_numeric($it['dosis'] ?? null) || $it['dosis'] <= 0) {
                $campos[] = "items[$i].dosis";
            }
            if (($it['unidad'] ?? null) !== $m[3]) {
                $campos[] = "items[$i].unidad";
            }
            if (!in_array($it['frecuencia_horas'] ?? null, [4, 6, 8, 12, 24], true)) {
                $campos[] = "items[$i].frecuencia_horas";
            }
            if (!in_array($it['via'] ?? null, $m[5], true)) {
                $campos[] = "items[$i].via";
            }
            if (!is_int($it['dias'] ?? null) || $it['dias'] < 1 || $it['dias'] > 90) {
                $campos[] = "items[$i].dias";
            }
            if (is_numeric($it['dosis'] ?? null) && in_array($it['frecuencia_horas'] ?? null, [4, 6, 8, 12, 24], true)
                && $it['dosis'] * (24 / $it['frecuencia_horas']) > $m[4]) {
                $dosis[] = "items[$i]";
            }
        }
        if ($s['items'] === [] || $campos !== []) {
            return ['estado' => 422, 'codigo' => 'DATOS_INVALIDOS'];
        }
        if ($dosis !== []) {
            return ['estado' => 422, 'codigo' => 'DOSIS_EXCEDE_MAXIMO'];
        }

        try {
            $q = $pdo->prepare('SELECT allergen_code, allergen_name, severity, status FROM allergies WHERE local_patient_id = ?');
            $q->execute([$s['paciente']]);
            $filas = $q->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            $filas = []; // «si el expediente falla, que no se caiga la prescripción»
        }
        if ($filas === [] && !($s['confirma_sin_alergias'] ?? false)) {
            return ['estado' => 422, 'codigo' => 'CONFIRMACION_REQUERIDA'];
        }

        $norm = static fn (string $t): string => strtr(mb_strtolower(trim($t)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $bloqueo = null;
        $advertencia = false;
        foreach ($s['items'] as $it) {
            $m = self::CATALOGO[$it['medicamento']];
            foreach ($filas as $f) {
                if ($f['status'] !== 'active') {
                    continue;
                }
                $directa = $f['allergen_code'] !== null && $f['allergen_code'] !== ''
                    ? strcasecmp($f['allergen_code'], $m[0]) === 0
                    : $norm((string) $f['allergen_name']) === $norm($m[1]);
                $grupo = self::GRUPOS_ALERGENOS[$f['allergen_code'] ?? ''] ?? self::GRUPOS_ALERGENOS[$norm((string) $f['allergen_name'])] ?? null;
                if ($directa) {
                    $bloqueo = 'BLOQUEO_ALERGIA';
                } elseif ($grupo === $m[2]) {
                    if (in_array($f['severity'], ['life_threatening', 'unknown'], true)) {
                        $bloqueo ??= 'BLOQUEO_ALERGIA_CRUZADA';
                    } else {
                        $advertencia = true;
                    }
                }
            }
        }
        if ($bloqueo !== null) {
            file_put_contents($this->archivoTraza, date('c') . " PRESCRIPCION_BLOQUEADA {$s['hospital']} {$s['usuario']}\n", FILE_APPEND);

            return ['estado' => 422, 'codigo' => $bloqueo];
        }
        if ($advertencia && (mb_strlen(trim((string) ($s['justificacion'] ?? ''))) < 20
            || !in_array('prescripcion.override', self::PERMISOS[$s['usuario']][$s['hospital']] ?? [], true))) {
            return ['estado' => 409, 'codigo' => 'JUSTIFICACION_REQUERIDA'];
        }

        $q = $pdo->prepare('INSERT INTO prescripciones_antes (hospital, clave, huella, paciente, prescriptor, emitida_en) VALUES (?, ?, ?, ?, ?, ?)');
        $q->execute([$s['hospital'], $s['clave'], $huella, $s['paciente'], $s['usuario'], date('c')]);
        file_put_contents($this->archivoTraza, date('c') . " PRESCRIPCION_EMITIDA {$s['hospital']} {$s['usuario']}\n", FILE_APPEND);

        return ['estado' => 201, 'codigo' => 'EMITIDA'];
    }
}
