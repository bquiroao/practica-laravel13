<?php

declare(strict_types=1);

/*
 * Evidencia verificable de la semana 2 (DIP).
 *  - CA-01..CA-17: criterios de aceptación contra el diseño DESPUÉS, todo en memoria (sin base).
 *  - DIP-01..DIP-03: la política no depende de detalles; los detalles dependen de los puertos; se sustituyen.
 *  - DIP-04, DIP-05, PAR-01: con PostgreSQL (PRESCRIPCIONES_DB_DSN): adaptador PDO sobre la tabla `allergies`,
 *    fail-closed frente al fail-open del ANTES, y paridad antes/después en el camino normal.
 *  - ANT-01: el ANTES no se puede probar sin base de datos.
 *
 * Uso:  php docs/modulos/prescripciones-alergias/semana-02/ejemplo-dip/verificar.php
 *       PRESCRIPCIONES_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_prescripciones" PRESCRIPCIONES_DB_USUARIO=postgres php …
 * Sale con 0 si todo pasa (las omitidas no cuentan como falla), 1 si algo falla.
 */

require __DIR__ . '/despues/autoload.php';
require __DIR__ . '/despues/composicion.php';
require __DIR__ . '/antes/ServicioPrescripcion.php';

use Prescripciones\Adaptadores\EnMemoria\FuenteAlergiasCaida;
use Prescripciones\Adaptadores\EnMemoria\FuenteAlergiasEnMemoria;
use Prescripciones\Adaptadores\Pdo\FuenteAlergiasPdo;
use Prescripciones\Antes\ServicioPrescripcion;
use Prescripciones\Aplicacion\EmitirPrescripcion;
use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Alergia;
use Prescripciones\Dominio\ExpedienteAlergias;
use Prescripciones\Dominio\Prescripcion;
use Prescripciones\Dominio\SolicitudEmision;

// ---------------------------------------------------------------------------
// Mini arnés (sin dependencias)
// ---------------------------------------------------------------------------
$resultados = [];

function prueba(string $id, string $descripcion, callable $cuerpo): void
{
    global $resultados;
    try {
        $omitida = $cuerpo();
        $resultados[] = [$id, $descripcion, is_string($omitida) ? 'OMITE' : 'PASA', is_string($omitida) ? $omitida : null];
    } catch (\Throwable $e) {
        $resultados[] = [$id, $descripcion, 'FALLA', $e->getMessage()];
    }
}

function igual(mixed $esperado, mixed $real, string $que): void
{
    if ($esperado !== $real) {
        throw new \RuntimeException(sprintf('%s: esperado %s, obtenido %s', $que, var_export($esperado, true), var_export($real, true)));
    }
}

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new \RuntimeException($mensaje);
    }
}

// ---------------------------------------------------------------------------
// Ayudas
// ---------------------------------------------------------------------------
/** @return array<string, mixed> */
function item(string $medicamento, float|int $dosis = 500, int $frecuencia = 8, string $via = 'oral', int $dias = 5, string $unidad = 'mg'): array
{
    return ['medicamento' => $medicamento, 'dosis' => $dosis, 'unidad' => $unidad, 'frecuencia_horas' => $frecuencia, 'via' => $via, 'dias' => $dias];
}

/** @param list<array<string, mixed>> $items */
function solicitud(string $paciente, array $items, string $usuario = 'dr-lopez', string $hospital = 'hosp-norte', ?string $justificacion = null,
    bool $confirma = false, ?string $clave = null): SolicitudEmision
{
    static $n = 0;
    $n++;

    return new SolicitudEmision($usuario, $hospital, $paciente, $clave ?? "clave-$n", $items, $justificacion, $confirma);
}

const JUSTIFICACION = 'Sin alternativa: cultivo sensible solo a cefalosporinas';

// ---------------------------------------------------------------------------
// Criterios de aceptación (DESPUÉS, en memoria)
// ---------------------------------------------------------------------------
prueba('CA-01', 'Camino feliz: emite, guarda la verificación y deja la traza', function (): void {
    $m = componer();
    $r = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500')]));
    igual([201, 'EMITIDA', 'SIN_HALLAZGOS'], [$r->estado, $r->codigo, $r->datos['verificacion']], 'respuesta');
    igual(1, count($m['repositorio']->guardadas), 'prescripciones guardadas');
    igual('SIN_HALLAZGOS', $m['repositorio']->guardadas[0]->verificacion->estado, 'verificación guardada');
    igual(['PRESCRIPCION_EMITIDA'], $m['auditoria']->acciones(), 'traza');
    igual('prescripcion:1', $m['auditoria']->movimientos[0]['recurso'], 'recurso sin datos clínicos');
});

prueba('CA-02', 'Sin prescripcion.emitir en ese hospital: 403 y nada guardado', function (): void {
    $m = componer();
    $r = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500')], 'dr-sur'));
    igual([403, 'PERMISO_DENEGADO'], [$r->estado, $r->codigo], 'respuesta');
    igual(0, count($m['repositorio']->guardadas), 'guardadas');
});

prueba('CA-03', 'Paciente de otro hospital: 404 igual a «no existe» (R-08)', function (): void {
    $m = componer();
    $ajeno = $m['emitir']->ejecutar(solicitud('P-900', [item('PARA500')]));
    $inexistente = $m['emitir']->ejecutar(solicitud('P-999', [item('PARA500')]));
    igual([404, 'NO_ENCONTRADO'], [$ajeno->estado, $ajeno->codigo], 'paciente de hosp-sur');
    igual([$inexistente->estado, $inexistente->codigo, $inexistente->datos], [$ajeno->estado, $ajeno->codigo, $ajeno->datos], 'misma respuesta');
});

prueba('CA-04', 'Incompleta: 422 con cada campo inválido (R-01)', function (): void {
    $m = componer();
    $r = $m['emitir']->ejecutar(solicitud('P-005', [
        item('PARA500', 0, 8, 'iv', 0, 'g'),
        item('RANI150'),
        item('NOEXISTE'),
        ['medicamento' => 'IBU400', 'dosis' => 400, 'unidad' => 'mg', 'frecuencia_horas' => 5, 'via' => 'oral', 'dias' => 91],
    ]));
    igual([422, 'DATOS_INVALIDOS'], [$r->estado, $r->codigo], 'respuesta');
    igual(['items[0].dosis', 'items[0].unidad', 'items[0].via', 'items[0].dias', 'items[1].medicamento', 'items[2].medicamento',
        'items[3].frecuencia_horas', 'items[3].dias'], $r->datos['campos'], 'campos');
    $vacia = $m['emitir']->ejecutar(solicitud('P-005', []));
    igual(['items'], $vacia->datos['campos'], 'sin ítems');
});

prueba('CA-05', 'Dosis diaria: 4000 mg pasa (límite), 6000 mg bloquea (R-02)', function (): void {
    $m = componer();
    $limite = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500', 1000, 6)]));
    $exceso = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500', 1000, 4)]));
    igual(201, $limite->estado, '1000 mg cada 6 h = 4000');
    igual([422, 'DOSIS_EXCEDE_MAXIMO', ['items[0]']], [$exceso->estado, $exceso->codigo, $exceso->datos['items']], '1000 mg cada 4 h = 6000');
});

prueba('CA-06', 'Coincidencia directa: bloqueo absoluto, ni con justificación y override (R-04)', function (): void {
    $m = componer();
    $r = $m['emitir']->ejecutar(solicitud('P-001', [item('AMOX500')], 'dr-lopez', 'hosp-norte', JUSTIFICACION));
    igual([422, 'BLOQUEO_ALERGIA'], [$r->estado, $r->codigo], 'respuesta');
    igual('DIRECTA', $r->datos['hallazgos'][0]['tipo'], 'tipo de hallazgo');
    igual(0, count($m['repositorio']->guardadas), 'guardadas');
    igual(['PRESCRIPCION_BLOQUEADA'], $m['auditoria']->acciones(), 'traza del bloqueo');
});

prueba('CA-07', 'Alérgeno solo con nombre («  AMOXICILINA »): coincide por nombre normalizado', function (): void {
    $m = componer();
    $r = $m['emitir']->ejecutar(solicitud('P-007', [item('AMOX500')]));
    igual([422, 'BLOQUEO_ALERGIA'], [$r->estado, $r->codigo], 'respuesta');
});

prueba('CA-08', 'Reactividad cruzada no grave: exige justificación ≥ 20 y override (R-05)', function (): void {
    $m = componer();
    $sin = $m['emitir']->ejecutar(solicitud('P-002', [item('CEFA500')]));
    $corta = $m['emitir']->ejecutar(solicitud('P-002', [item('CEFA500')], 'dr-lopez', 'hosp-norte', str_repeat('x', 19)));
    $sinPermiso = $m['emitir']->ejecutar(solicitud('P-002', [item('CEFA500')], 'dr-ruiz', 'hosp-norte', JUSTIFICACION));
    $ok = $m['emitir']->ejecutar(solicitud('P-002', [item('CEFA500')], 'dr-lopez', 'hosp-norte', str_repeat('x', 20)));
    igual([409, 'JUSTIFICACION_REQUERIDA'], [$sin->estado, $sin->codigo], 'sin justificación');
    igual(409, $corta->estado, '19 caracteres');
    igual(409, $sinPermiso->estado, 'sin prescripcion.override');
    igual([201, 'ADVERTENCIA'], [$ok->estado, $ok->datos['verificacion']], '20 caracteres con override');
    igual(str_repeat('x', 20), $m['repositorio']->guardadas[0]->justificacion, 'justificación guardada');
});

prueba('CA-09', 'Cruzada con severidad life_threatening o unknown: bloqueo aunque se justifique', function (): void {
    $m = componer();
    $grave = $m['emitir']->ejecutar(solicitud('P-004', [item('IBU400', 400)], 'dr-lopez', 'hosp-norte', JUSTIFICACION));
    $desconocida = $m['emitir']->ejecutar(solicitud('P-006', [item('CEFA500')], 'dr-lopez', 'hosp-norte', JUSTIFICACION));
    igual([422, 'BLOQUEO_ALERGIA_CRUZADA'], [$grave->estado, $grave->codigo], 'life_threatening');
    igual([422, 'BLOQUEO_ALERGIA_CRUZADA'], [$desconocida->estado, $desconocida->codigo], 'unknown');
    igual(0, count($m['repositorio']->guardadas), 'guardadas');
});

prueba('CA-10', 'Alergias no evaluadas: exige confirmar «sin alergias conocidas» (R-06)', function (): void {
    $m = componer();
    $sin = $m['emitir']->ejecutar(solicitud('P-003', [item('AMOX500')]));
    $con = $m['emitir']->ejecutar(solicitud('P-003', [item('AMOX500')], 'dr-lopez', 'hosp-norte', null, true));
    igual([422, 'CONFIRMACION_REQUERIDA'], [$sin->estado, $sin->codigo], 'sin confirmación');
    igual(201, $con->estado, 'con confirmación');
    igual(true, $m['repositorio']->guardadas[0]->confirmaSinAlergias, 'confirmación guardada');
});

prueba('CA-11', 'Fuente de alergias caída: 503, nada emitido y traza (R-07, fail-closed)', function (): void {
    $m = componer(new FuenteAlergiasCaida());
    $r = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500')], 'dr-lopez', 'hosp-norte', null, true));
    igual([503, 'VERIFICACION_NO_DISPONIBLE'], [$r->estado, $r->codigo], 'respuesta (aun confirmando «sin alergias»)');
    igual(0, count($m['repositorio']->guardadas), 'guardadas');
    igual(['VERIFICACION_NO_DISPONIBLE'], $m['auditoria']->acciones(), 'traza');
});

prueba('CA-12', 'Solo cuentan las alergias activas; una resuelta no bloquea ni pide confirmación', function (): void {
    $m = componer();
    $r = $m['emitir']->ejecutar(solicitud('P-005', [item('AMOX500')]));
    igual([201, 'SIN_HALLAZGOS'], [$r->estado, $r->datos['verificacion']], 'respuesta');
});

prueba('CA-13', 'Idempotencia: mismo contenido → la misma; otro contenido → 409 (R-11)', function (): void {
    $m = componer();
    $a = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500')], clave: 'k-1'));
    $b = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500')], clave: 'k-1'));
    $c = $m['emitir']->ejecutar(solicitud('P-005', [item('IBU400', 400)], clave: 'k-1'));
    igual([201, 200, 409], [$a->estado, $b->estado, $c->estado], 'estados');
    igual($a->datos['id'], $b->datos['id'], 'mismo id');
    igual(1, count($m['repositorio']->guardadas), 'guardadas');
});

prueba('CA-14', 'La base no confirma: 503 y sin traza de emisión', function (): void {
    $m = componer();
    $m['repositorio']->fallar = true;
    $r = $m['emitir']->ejecutar(solicitud('P-005', [item('PARA500')]));
    igual([503, 'ERROR_PERSISTENCIA'], [$r->estado, $r->codigo], 'respuesta');
    igual([], $m['auditoria']->acciones(), 'traza');
});

prueba('CA-15', 'Verificación previa: informa sin guardar ni trazar', function (): void {
    $m = componer();
    $adv = $m['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-002', [item('CEFA500')]);
    $conf = $m['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-003', [item('CEFA500')]);
    $caida = componer(new FuenteAlergiasCaida())['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-002', [item('CEFA500')]);
    igual([200, 'ADVERTENCIA'], [$adv->estado, $adv->codigo], 'cruzada');
    igual('CONFIRMACION_REQUERIDA', $conf->codigo, 'no evaluadas');
    igual('VERIFICACION_NO_DISPONIBLE', $caida->codigo, 'fuente caída');
    igual([0, []], [count($m['repositorio']->guardadas), $m['auditoria']->acciones()], 'sin efectos');
});

prueba('CA-16', 'La emisión vuelve a verificar: una alergia registrada después de la previa bloquea (R-03)', function (): void {
    $fuente = new FuenteAlergiasEnMemoria(alergiasDemo());
    $m = componer($fuente);
    $previa = $m['previa']->ejecutar('dr-lopez', 'hosp-norte', 'P-005', [item('AMOX500')]);
    $fuente->agregar('P-005', new Alergia('J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active'));
    $r = $m['emitir']->ejecutar(solicitud('P-005', [item('AMOX500')]));
    igual('SIN_HALLAZGOS', $previa->codigo, 'previa');
    igual([422, 'BLOQUEO_ALERGIA'], [$r->estado, $r->codigo], 'emisión');
});

prueba('CA-17', 'Inmutable: el repositorio no puede actualizar ni borrar; la prescripción es readonly (R-10)', function (): void {
    $metodos = array_map(static fn (\ReflectionMethod $x): string => $x->getName(), (new \ReflectionClass(RepositorioPrescripciones::class))->getMethods());
    igual(['porClave', 'guardar'], $metodos, 'métodos del puerto');
    afirmar((new \ReflectionClass(Prescripcion::class))->isReadOnly(), 'Prescripcion debe ser readonly');
});

// ---------------------------------------------------------------------------
// DIP
// ---------------------------------------------------------------------------
prueba('DIP-01', 'La política (Aplicacion, Dominio) no menciona ningún detalle de bajo nivel', function (): void {
    $prohibidos = ['PDO', 'Adaptadores', 'file_put_contents', 'fopen', 'curl_', 'date(', 'time()', "new \\DateTimeImmutable('now", 'SELECT ', 'INSERT ', '$_SERVER', 'getenv('];
    $hallazgos = [];
    foreach (['Aplicacion', 'Dominio'] as $capa) {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . "/despues/src/$capa", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            // Solo el código: los comentarios pueden nombrar lo que la clase NO usa.
            $codigo = implode('', array_map(static fn ($t): string => is_array($t) ? (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? ' ' : $t[1]) : $t,
                token_get_all((string) file_get_contents((string) $archivo))));
            foreach ($prohibidos as $p) {
                if (str_contains($codigo, $p)) {
                    $hallazgos[] = basename((string) $archivo) . " contiene «{$p}»";
                }
            }
        }
    }
    igual([], $hallazgos, 'menciones a detalles');
});

prueba('DIP-02', 'Las abstracciones son del alto nivel y los detalles dependen de ellas', function (): void {
    $parametros = (new \ReflectionClass(EmitirPrescripcion::class))->getConstructor()->getParameters();
    foreach ($parametros as $p) {
        $tipo = (string) $p->getType();
        $esPuerto = str_starts_with($tipo, 'Prescripciones\\Aplicacion\\Puertos\\') && interface_exists($tipo);
        afirmar($esPuerto || $tipo === 'Prescripciones\\Aplicacion\\EvaluacionClinica', "EmitirPrescripcion recibe un detalle: $tipo");
    }
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/despues/src/Adaptadores', \FilesystemIterator::SKIP_DOTS));
    $adaptadores = 0;
    foreach ($it as $archivo) {
        $clase = 'Prescripciones\\Adaptadores\\' . basename(dirname((string) $archivo)) . '\\' . basename((string) $archivo, '.php');
        $interfaces = array_filter(class_implements($clase), static fn (string $i): bool => str_starts_with($i, 'Prescripciones\\Aplicacion\\Puertos\\'));
        afirmar($interfaces !== [], "$clase no implementa ningún puerto");
        $adaptadores++;
    }
    igual(9, $adaptadores, 'adaptadores encontrados');
});

prueba('DIP-03', 'Sustitución: otra fuente (formato FHIR AllergyIntolerance) sin tocar el caso de uso', function (): void {
    // Un adaptador nuevo, escrito aquí mismo: traduce recursos FHIR al expediente del dominio.
    $fhir = new class implements FuenteAlergias {
        public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
        {
            $recursos = $paciente === 'P-001' ? [[
                'resourceType' => 'AllergyIntolerance',
                'clinicalStatus' => ['coding' => [['code' => 'active']]],
                'criticality' => 'high',
                'code' => ['coding' => [['system' => 'http://www.whocc.no/atc', 'code' => 'J01CA04', 'display' => 'amoxicillin']]],
            ]] : [];
            $severidad = ['low' => 'mild', 'high' => 'severe', 'unable-to-assess' => 'unknown'];

            return new ExpedienteAlergias($recursos !== [], array_map(static fn (array $r): Alergia => new Alergia(
                $r['code']['coding'][0]['code'], $r['code']['coding'][0]['display'], 'allergy',
                $severidad[$r['criticality']] ?? 'unknown', $r['clinicalStatus']['coding'][0]['code'],
            ), $recursos));
        }
    };
    $enMemoria = componer()['emitir']->ejecutar(solicitud('P-001', [item('AMOX500')]));
    $conFhir = componer($fhir)['emitir']->ejecutar(solicitud('P-001', [item('AMOX500')]));
    igual([$enMemoria->estado, $enMemoria->codigo], [$conFhir->estado, $conFhir->codigo], 'mismo resultado con otra fuente');
});

prueba('ANT-01', 'El ANTES no se puede probar sin base: hasta la regla de dosis exige PostgreSQL', function (): void {
    $antes = new ServicioPrescripcion('pgsql:host=127.0.0.1;port=1;dbname=x;connect_timeout=2', 'nadie', sys_get_temp_dir() . '/traza-antes.log');
    try {
        $antes->emitir(['usuario' => 'dr-lopez', 'hospital' => 'hosp-norte', 'paciente' => 'P-005', 'clave' => 'x',
            'items' => [item('PARA500', 1000, 4)]]);
    } catch (\PDOException) {
        return; // la regla pura de dosis no llegó a evaluarse: el detalle va primero
    }
    throw new \RuntimeException('Se esperaba PDOException: el ANTES crea su conexión antes de cualquier regla');
});

// ---------------------------------------------------------------------------
// Con PostgreSQL: adaptador PDO, fail-closed y paridad antes/después
// ---------------------------------------------------------------------------
$dsn = getenv('PRESCRIPCIONES_DB_DSN') ?: null;
$usuarioBd = getenv('PRESCRIPCIONES_DB_USUARIO') ?: null;

/** Crea dos esquemas: «s2_sano» (con allergies) y «s2_roto» (sin allergies, como un expediente inaccesible). */
function prepararBase(string $dsn, ?string $usuario): void
{
    $pdo = new \PDO($dsn, $usuario, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $prescripciones = 'CREATE TABLE %s.prescripciones_antes (id bigserial PRIMARY KEY, hospital text NOT NULL, clave text NOT NULL,
        huella text NOT NULL, paciente text NOT NULL, prescriptor text NOT NULL, emitida_en timestamptz NOT NULL, UNIQUE (hospital, clave))';
    foreach (['s2_sano', 's2_roto'] as $esquema) {
        $pdo->exec("DROP SCHEMA IF EXISTS $esquema CASCADE; CREATE SCHEMA $esquema");
        $pdo->exec(sprintf($prescripciones, $esquema));
    }
    // Mismas columnas y CHECK que la migración real 2026_08_11_000401 (sin las FK a local_patients y local_user_refs;
    // local_patient_id como text para que los ids de la demostración sean legibles).
    $pdo->exec("CREATE TABLE s2_sano.allergies (
        allergy_id bigserial PRIMARY KEY, local_patient_id text NOT NULL,
        allergen_code text, allergen_name text, reaction text CHECK (reaction IS NULL OR btrim(reaction) <> ''),
        allergy_type text NOT NULL CHECK (allergy_type IN ('allergy', 'intolerance', 'unknown')),
        severity text NOT NULL CHECK (severity IN ('mild', 'moderate', 'severe', 'life_threatening', 'unknown')),
        status text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'resolved')),
        CHECK (COALESCE(NULLIF(btrim(allergen_code), ''), NULLIF(btrim(allergen_name), '')) IS NOT NULL))");
    $insertar = $pdo->prepare('INSERT INTO s2_sano.allergies (local_patient_id, allergen_code, allergen_name, reaction, allergy_type, severity, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach (alergiasDemo() as $paciente => $alergias) {
        foreach ($alergias as $a) {
            $insertar->execute([$paciente, $a->codigo, $a->nombre, $a->reaccion, $a->tipo, $a->severidad, $a->estado]);
        }
    }
}

function conEsquema(string $dsn, string $esquema): string
{
    return $dsn . ";options='--search_path=$esquema'";
}

/** Escenarios del camino normal: [paciente, items, usuario, justificación, confirma]. */
function escenarios(): array
{
    return [
        ['P-005', [item('PARA500')], 'dr-lopez', null, false],
        ['P-005', [item('PARA500', 1000, 4)], 'dr-lopez', null, false],
        ['P-005', [item('PARA500', 0, 8, 'iv')], 'dr-lopez', null, false],
        ['P-900', [item('PARA500')], 'dr-lopez', null, false],
        ['P-005', [item('PARA500')], 'dr-sur', null, false],
        ['P-001', [item('AMOX500')], 'dr-lopez', JUSTIFICACION, false],
        ['P-007', [item('AMOX500')], 'dr-lopez', null, false],
        ['P-002', [item('CEFA500')], 'dr-lopez', null, false],
        ['P-002', [item('CEFA500')], 'dr-ruiz', JUSTIFICACION, false],
        ['P-002', [item('CEFA500')], 'dr-lopez', JUSTIFICACION, false],
        ['P-004', [item('IBU400', 400)], 'dr-lopez', JUSTIFICACION, false],
        ['P-006', [item('CEFA500')], 'dr-lopez', JUSTIFICACION, false],
        ['P-003', [item('AMOX500')], 'dr-lopez', null, false],
        ['P-003', [item('AMOX500')], 'dr-lopez', null, true],
        ['P-005', [item('AMOX500')], 'dr-lopez', null, false],
    ];
}

$sinBase = static fn (): string => 'sin PRESCRIPCIONES_DB_DSN';

prueba('DIP-04', 'Adaptador PDO sobre `allergies`: mismo expediente y mismas decisiones que en memoria', function () use ($dsn, $usuarioBd, $sinBase) {
    if ($dsn === null) {
        return $sinBase();
    }
    prepararBase($dsn, $usuarioBd);
    $pdo = new \PDO(conEsquema($dsn, 's2_sano'), $usuarioBd, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $fuentePdo = new FuenteAlergiasPdo(['hosp-norte' => $pdo]);
    $memoria = new FuenteAlergiasEnMemoria(alergiasDemo());
    foreach (['P-001', 'P-002', 'P-003', 'P-004', 'P-005', 'P-006', 'P-007'] as $p) {
        igual(serialize($memoria->alergiasDe($p, 'hosp-norte')), serialize($fuentePdo->alergiasDe($p, 'hosp-norte')), "expediente de $p");
    }
    $conPdo = componer($fuentePdo)['emitir'];
    $enMemoria = componer()['emitir'];
    foreach (escenarios() as $i => [$paciente, $items, $usuario, $justificacion, $confirma]) {
        $a = $enMemoria->ejecutar(solicitud($paciente, $items, $usuario, 'hosp-norte', $justificacion, $confirma, "m-$i"));
        $b = $conPdo->ejecutar(solicitud($paciente, $items, $usuario, 'hosp-norte', $justificacion, $confirma, "m-$i"));
        igual([$a->estado, $a->codigo], [$b->estado, $b->codigo], "escenario $i");
    }
    try {
        $fuentePdo->alergiasDe('P-001', 'hosp-sur');
        throw new \RuntimeException('Un hospital sin conexión debe lanzar FuenteAlergiasNoDisponible');
    } catch (FuenteAlergiasNoDisponible) {
    }
});

prueba('DIP-05', 'Expediente inaccesible: el DESPUÉS bloquea (503); el ANTES emite amoxicilina a un alérgico', function () use ($dsn, $usuarioBd, $sinBase) {
    if ($dsn === null) {
        return $sinBase();
    }
    prepararBase($dsn, $usuarioBd);
    $roto = new \PDO(conEsquema($dsn, 's2_roto'), $usuarioBd, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    try {
        (new FuenteAlergiasPdo(['hosp-norte' => $roto]))->alergiasDe('P-001', 'hosp-norte');
        throw new \RuntimeException('El adaptador devolvió un expediente en lugar de fallar');
    } catch (FuenteAlergiasNoDisponible) {
    }
    $despues = componer(new FuenteAlergiasPdo(['hosp-norte' => $roto]))['emitir'];
    $d1 = $despues->ejecutar(solicitud('P-001', [item('AMOX500')]));
    $d2 = $despues->ejecutar(solicitud('P-001', [item('AMOX500')], confirma: true));
    igual([503, 503], [$d1->estado, $d2->estado], 'DESPUÉS sin y con confirmación');

    $antes = new ServicioPrescripcion(conEsquema($dsn, 's2_roto'), $usuarioBd, sys_get_temp_dir() . '/traza-antes.log');
    $base = ['usuario' => 'dr-lopez', 'hospital' => 'hosp-norte', 'paciente' => 'P-001', 'items' => [item('AMOX500')]];
    $a1 = $antes->emitir($base + ['clave' => 'roto-1']);
    $a2 = $antes->emitir($base + ['clave' => 'roto-2', 'confirma_sin_alergias' => true]);
    // El defecto del ANTES, documentado: le dice al médico que no hay alergias registradas y, si confirma, emite.
    igual([[422, 'CONFIRMACION_REQUERIDA'], [201, 'EMITIDA']], [[$a1['estado'], $a1['codigo']], [$a2['estado'], $a2['codigo']]], 'ANTES (fail-open)');
});

prueba('PAR-01', 'Paridad: en el camino normal ANTES y DESPUÉS responden igual en los 15 escenarios', function () use ($dsn, $usuarioBd, $sinBase) {
    if ($dsn === null) {
        return $sinBase();
    }
    prepararBase($dsn, $usuarioBd);
    $antes = new ServicioPrescripcion(conEsquema($dsn, 's2_sano'), $usuarioBd, sys_get_temp_dir() . '/traza-antes.log');
    $pdo = new \PDO(conEsquema($dsn, 's2_sano'), $usuarioBd, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $despues = componer(new FuenteAlergiasPdo(['hosp-norte' => $pdo]))['emitir'];
    foreach (escenarios() as $i => [$paciente, $items, $usuario, $justificacion, $confirma]) {
        $a = $antes->emitir(['usuario' => $usuario, 'hospital' => 'hosp-norte', 'paciente' => $paciente, 'clave' => "p-$i", 'items' => $items,
            'justificacion' => $justificacion, 'confirma_sin_alergias' => $confirma]);
        $d = $despues->ejecutar(solicitud($paciente, $items, $usuario, 'hosp-norte', $justificacion, $confirma, "p-$i"));
        igual([$a['estado'], $a['codigo']], [$d->estado, $d->codigo], "escenario $i ($paciente)");
    }
});

// ---------------------------------------------------------------------------
// Informe
// ---------------------------------------------------------------------------
$fallan = 0;
$omitidas = 0;
foreach ($resultados as [$id, $descripcion, $estado, $detalle]) {
    printf("[%-5s] %-7s %s\n", $estado, $id, $descripcion);
    if ($detalle !== null) {
        echo "          -> $detalle\n";
    }
    $fallan += $estado === 'FALLA' ? 1 : 0;
    $omitidas += $estado === 'OMITE' ? 1 : 0;
}
printf("\nTotal: %d  Pasan: %d  Fallan: %d  Omitidas: %d\n", count($resultados), count($resultados) - $fallan - $omitidas, $fallan, $omitidas);
exit($fallan === 0 ? 0 : 1);
