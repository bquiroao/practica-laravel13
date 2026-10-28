<?php

declare(strict_types=1);

/*
 * Evidencia verificable de la semana 2 (LSP), sin base de datos.
 *  - CA-01..CA-15: criterios de aceptación de RF-01..RF-14 y RNF-02, RNF-04 contra el diseño DESPUÉS.
 *  - LSP-01..LSP-06: la MISMA suite de contrato sobre cada subtipo de evidencia (ANTES y DESPUÉS), las consecuencias de
 *    romper el contrato en los clientes, la sustitución por un tipo nuevo y la ausencia de instanceof en la Aplicación.
 *
 * Uso:  php docs/modulos/qa-regresion-defectos/semana-02/ejemplo-lsp/verificar.php
 * Sale con 0 si todo pasa y 1 si algo falla.
 */

require __DIR__ . '/despues/autoload.php';
require __DIR__ . '/antes/Evidencias.php';

use Qa\Adaptadores\AlmacenEnMemoria;
use Qa\Adaptadores\PipelineSimulado;
use Qa\Adaptadores\PoliticaEnMemoria;
use Qa\Aplicacion\ConsultarEvidencia;
use Qa\Aplicacion\GenerarInforme;
use Qa\Aplicacion\ImportarLogRemoto;
use Qa\Aplicacion\RegistrarResultado;
use Qa\Aplicacion\VerificarIntegridad;
use Qa\Dominio\Campana;
use Qa\Dominio\CasoPrueba;
use Qa\Dominio\CriterioSalida;
use Qa\Dominio\Defecto;
use Qa\Dominio\EstadoDefecto;
use Qa\Dominio\Evidencia\Captura;
use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\EvidenciaInmutable;
use Qa\Dominio\Evidencia\EvidenciaInvalida;
use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Evidencia\ReferenciaExterna;
use Qa\Dominio\Evidencia\RegistroTexto;
use Qa\Dominio\Evidencia\ResultadoJUnit;
use Qa\Dominio\Intento;
use Qa\Dominio\ReglaRechazada;
use Qa\Dominio\Resultado;
use Qa\Dominio\Severidad;

// ---------------------------------------------------------------------------
// Mini arnés (sin dependencias)
// ---------------------------------------------------------------------------
$resultados = [];

function prueba(string $id, string $descripcion, callable $cuerpo): void
{
    global $resultados;
    try {
        $cuerpo();
        $resultados[] = [$id, $descripcion, 'PASA', null];
    } catch (\Throwable $e) {
        $resultados[] = [$id, $descripcion, 'FALLA', $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'];
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

/** @return string el código de la ReglaRechazada que lanza $cuerpo */
function rechazo(callable $cuerpo): string
{
    try {
        $cuerpo();
    } catch (ReglaRechazada $e) {
        return $e->codigo . ($e->detalle === [] ? '' : ' ' . implode(',', $e->detalle));
    } catch (EvidenciaInvalida $e) {
        return $e->codigo;
    }

    return 'SIN RECHAZO';
}

// ---------------------------------------------------------------------------
// Datos de prueba
// ---------------------------------------------------------------------------
const PNG = "\x89PNG\r\n\x1a\n" . 'IHDR-captura-del-paso-3';
const JPG = "\xFF\xD8\xFF\xE0" . 'JFIF-captura';
const JUNIT = '<?xml version="1.0"?><testsuites><testsuite name="Tests\Database\Central\MpiIntegrityTest" tests="3" assertions="44" failures="1" errors="0">'
    . '<testcase name="test_identificador_unico"/><testcase name="test_fusion"><failure>Expected 1 row, got 2</failure></testcase><testcase name="test_rollback"/>'
    . '</testsuite></testsuites>';

function caso(string $id, string $autor = 'ana-qa'): CasoPrueba
{
    return CasoPrueba::disenar($id, $autor, "Caso $id", [['accion' => 'Abrir el expediente', 'esperado' => 'Se ve el paciente']], 'ALTA', ['RF-07']);
}

/** @return array{0: Campana, 1: RegistrarResultado, 2: AlmacenEnMemoria} campaña EN_CURSO con $n casos aprobados */
function campana(int $n = 3, ?PoliticaEnMemoria $politica = null): array
{
    $casos = array_map(static fn (int $i): CasoPrueba => caso(sprintf('TC-%03d', $i))->aprobar('lider-qa'), range(1, $n));
    $c = new Campana('CMP-1', 'hosp-norte', $casos);
    $c->iniciar('2.4.0+c56f169', ['TC-001']);
    $almacen = new AlmacenEnMemoria();

    return [$c, new RegistrarResultado($almacen, $politica ?? politica()), $almacen];
}

function politica(): PoliticaEnMemoria
{
    return new PoliticaEnMemoria([
        'tito-tester|hosp-norte' => ['qa.ejecutar', 'qa.consultar'],
        'lider-qa|hosp-norte' => ['qa.ejecutar', 'qa.consultar', 'qa.ver_evidencia_clinica'],
    ]);
}

function defecto(string $id = 'D-1', array $pasos = ['Abrir', 'Guardar'], bool $conEvidencia = true, Severidad $propuesta = Severidad::MAYOR): Defecto
{
    return new Defecto($id, 'TC-001', 'El paciente se duplica', $pasos, $propuesta, $conEvidencia);
}

$registrar = static fn (RegistrarResultado $r, Campana $c, string $caso, Resultado $res, array $ev = [], array $refs = [], ?Defecto $d = null, ?string $motivo = null, ?string $clave = null, string $tester = 'tito-tester')
    => $r->ejecutar($tester, $c, $caso, $res, $ev, $refs, $d, $motivo, $clave ?? bin2hex(random_bytes(4)));

// ---------------------------------------------------------------------------
// Criterios de aceptación (diseño DESPUÉS)
// ---------------------------------------------------------------------------
prueba('CA-01', 'RF-01 · Caso incompleto: 422 con cada campo (paso sin resultado esperado, prioridad, ningún requisito)', function (): void {
    igual('DATOS_INVALIDOS pasos[0].esperado,prioridad,requisitos',
        rechazo(static fn () => CasoPrueba::disenar('TC-9', 'ana-qa', 'Caso', [['accion' => 'Abrir']], 'URGENTE', ['req-7'])), 'rechazo');
    igual('BORRADOR', caso('TC-1')->estado, 'un caso completo queda en borrador');
});

prueba('CA-02', 'RF-02 · El autor no aprueba su caso; editar un caso aprobado crea la versión 2 y la 1 sigue aprobada', function (): void {
    igual('APROBACION_POR_AUTOR', rechazo(static fn () => caso('TC-1', 'ana-qa')->aprobar('ana-qa')), 'autor');
    $v1 = caso('TC-1')->aprobar('lider-qa');
    $v2 = $v1->editar('ana-qa', [['accion' => 'Abrir', 'esperado' => 'Otro']]);
    igual([1, 'APROBADO', 2, 'BORRADOR'], [$v1->version, $v1->estado, $v2->version, $v2->estado], 'versiones');
});

prueba('CA-03', 'RF-03 · Iniciar: falta un crítico afectado → ALCANCE_INCOMPLETO con su id; sin build; caso sin aprobar; válida → EN_CURSO', function (): void {
    $aprobados = [caso('TC-001')->aprobar('lider-qa'), caso('TC-002')->aprobar('lider-qa')];
    igual('ALCANCE_INCOMPLETO TC-007', rechazo(static fn () => (new Campana('C', 'hosp-norte', $aprobados))->iniciar('2.4.0', ['TC-001', 'TC-007'])), 'crítico que falta');
    igual('ALCANCE_INCOMPLETO build', rechazo(static fn () => (new Campana('C', 'hosp-norte', $aprobados))->iniciar(' ', ['TC-001'])), 'sin build');
    igual('CASO_NO_APROBADO TC-003', rechazo(static fn () => (new Campana('C', 'hosp-norte', [...$aprobados, caso('TC-003')]))->iniciar('2.4.0', [])), 'borrador');
    $c = new Campana('C', 'hosp-norte', $aprobados);
    $c->iniciar('2.4.0', ['TC-001']);
    igual(['EN_CURSO', '2.4.0'], [$c->estado, $c->build], 'iniciada');
});

prueba('CA-04', 'RF-04 · Un resultado fuera de una campaña EN_CURSO o de un caso fuera del alcance → 409', function () use ($registrar): void {
    [$c, $r] = campana();
    igual(409, $registrar($r, $c, 'TC-099', Resultado::PASA)->estado, 'caso fuera del alcance');
    $planificada = new Campana('C2', 'hosp-norte', [caso('TC-001')->aprobar('lider-qa')]);
    igual(409, $registrar($r, $planificada, 'TC-001', Resultado::PASA)->estado, 'campaña planificada');
});

prueba('CA-05', 'RF-05 · FALLA sin evidencia o con SOLO un enlace → 422; NO_APLICA sin motivo → 422; > 5 MB → 413; bytes que no son imagen → TIPO_NO_PERMITIDO', function () use ($registrar): void {
    [$c, $r] = campana();
    $enlace = new ReferenciaExterna('https://ci.example/build/812', 'pipeline');
    igual('EVIDENCIA_REQUERIDA', $registrar($r, $c, 'TC-001', Resultado::FALLA, [], [], defecto())->codigo, 'sin evidencia');
    igual('EVIDENCIA_REQUERIDA', $registrar($r, $c, 'TC-001', Resultado::FALLA, [], [$enlace], defecto())->codigo, 'solo un enlace');
    igual('EVIDENCIA_REQUERIDA', $registrar($r, $c, 'TC-001', Resultado::BLOQUEADO)->codigo, 'bloqueado sin evidencia');
    igual('MOTIVO_REQUERIDO', $registrar($r, $c, 'TC-001', Resultado::NO_APLICA)->codigo, 'no aplica sin motivo');
    igual(413, $registrar($r, $c, 'TC-001', Resultado::BLOQUEADO, [new RegistroTexto(str_repeat('x', 5 * 1024 * 1024 + 1))])->estado, 'más de 5 MB');
    igual(201, $registrar($r, $c, 'TC-002', Resultado::BLOQUEADO, [new RegistroTexto(str_repeat('x', 5 * 1024 * 1024))])->estado, 'exactamente 5 MB');
    igual('TIPO_NO_PERMITIDO', rechazo(static fn () => new Captura('%PDF-1.7 no es una imagen')), 'captura falsa');
    igual(0, count(array_filter($c->intentos(), static fn (Intento $i): bool => $i->casoId === 'TC-001')), 'nada registrado en TC-001');
});

prueba('CA-06', 'RF-06 · Almacén de evidencias caído: 503 EVIDENCIA_NO_DISPONIBLE y NINGÚN intento registrado (fail-closed)', function () use ($registrar): void {
    [$c, $r, $almacen] = campana();
    $almacen->caido = true;
    igual([503, 'EVIDENCIA_NO_DISPONIBLE'], [($x = $registrar($r, $c, 'TC-001', Resultado::FALLA, [new Captura(PNG)], [], defecto()))->estado, $x->codigo], 'respuesta');
    igual([], $c->intentos(), 'intentos');
});

prueba('CA-07', 'RF-07 · Una FALLA sin defecto → 422 DEFECTO_REQUERIDO; con defecto → 201 y el intento lo referencia', function () use ($registrar): void {
    [$c, $r] = campana();
    igual('DEFECTO_REQUERIDO', $registrar($r, $c, 'TC-001', Resultado::FALLA, [new Captura(PNG)])->codigo, 'sin defecto');
    igual(201, $registrar($r, $c, 'TC-001', Resultado::FALLA, [new Captura(PNG)], [], defecto('D-7'))->estado, 'con defecto');
    igual('D-7', $c->ultimo('TC-001')?->defectoId, 'vínculo');
});

prueba('CA-08', 'RF-08 · CRÍTICA sin pasos o sin evidencia → DEFECTO_INCOMPLETO; cambiar la severidad propuesta exige motivo y queda en el historial', function (): void {
    igual('DEFECTO_INCOMPLETO', rechazo(static fn () => defecto(pasos: [])->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'motivo')), 'sin pasos');
    igual('DEFECTO_INCOMPLETO', rechazo(static fn () => defecto(conEvidencia: false)->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'motivo')), 'sin evidencia');
    igual('MOTIVO_REQUERIDO', rechazo(static fn () => defecto()->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa')), 'cambio sin motivo');
    $d = defecto();
    $d->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'Duplica pacientes: riesgo clínico');
    igual([EstadoDefecto::ABIERTO, 1], [$d->estado, count($d->historial)], 'clasificado');
});

prueba('CA-09', 'RF-09 · El desarrollador solo marca CORREGIDO; cierra una re-ejecución PASA; una FALLA lo reabre; verificar sin corregir → rechazo', function (): void {
    $d = defecto();
    $d->clasificar(Severidad::MAYOR, 'MEDIA', 'lider-qa');
    igual('ESTADO_NO_PERMITE_VERIFICAR', rechazo(static fn () => $d->verificar(Resultado::PASA)), 'sin corregir');
    $d->marcarCorregido('2.4.1', 'dev-luis');
    igual(EstadoDefecto::CORREGIDO, $d->estado, 'corregido, no cerrado');
    $d->verificar(Resultado::FALLA);
    igual(EstadoDefecto::REABIERTO, $d->estado, 'reabierto');
    $d->marcarCorregido('2.4.2', 'dev-luis');
    $d->verificar(Resultado::PASA);
    igual(EstadoDefecto::CERRADO, $d->estado, 'cerrado');
    igual('ESTADO_NO_PERMITE_CORREGIR', rechazo(static fn () => $d->marcarCorregido('2.4.3', 'dev-luis')), 'cerrado no se reabre por el desarrollador');
    afirmar(!method_exists($d, 'cerrar'), 'no existe un método cerrar()');
});

prueba('CA-10', 'RF-10 · Cerrar: pendientes → CASOS_PENDIENTES; 38/40 con 0 críticos = 95 % → GO; 37/40 → NO_GO; 40/40 con 1 crítico → NO_GO; cuenta el ÚLTIMO intento', function () use ($registrar): void {
    [$c, $r] = campana(40);
    for ($i = 1; $i <= 39; $i++) {
        $registrar($r, $c, sprintf('TC-%03d', $i), Resultado::PASA);
    }
    igual('CASOS_PENDIENTES TC-040', rechazo(static fn () => $c->cerrar(new CriterioSalida(), [])), 'pendiente');
    $cerrar = static function (int $fallas, int $criticos, bool $corregirUno = false) use ($registrar): string {
        [$c, $r] = campana(40);
        $defectos = [];
        for ($i = 1; $i <= 40; $i++) {
            $id = sprintf('TC-%03d', $i);
            if ($i <= $fallas) {
                $d = defecto("D-$i");
                $d->clasificar($i <= $criticos ? Severidad::CRITICA : Severidad::MAYOR, 'ALTA', 'lider-qa', 'motivo');
                $defectos[] = $d;
                $registrar($r, $c, $id, Resultado::FALLA, [new Captura(PNG)], [], $d);
            } else {
                $registrar($r, $c, $id, Resultado::PASA);
            }
        }
        if ($corregirUno) {
            $registrar($r, $c, 'TC-001', Resultado::PASA);
        }

        return ($dec = $c->cerrar(new CriterioSalida(), $defectos))->valor . ' ' . round(100 * $dec->tasa, 1);
    };
    igual('GO 95', $cerrar(2, 0), '38/40');
    igual('NO_GO 92.5', $cerrar(3, 0), '37/40');
    // 40/40 PASA, pero queda abierto un crítico de otra campaña que afecta a este build
    [$c40, $r40] = campana(40);
    for ($i = 1; $i <= 40; $i++) {
        $registrar($r40, $c40, sprintf('TC-%03d', $i), Resultado::PASA);
    }
    $critico = defecto('D-X');
    $critico->clasificar(Severidad::CRITICA, 'ALTA', 'lider-qa', 'motivo');
    $d40 = $c40->cerrar(new CriterioSalida(), [$critico]);
    igual(['NO_GO', 1.0, ['1 defectos críticos abiertos']], [$d40->valor, $d40->tasa, $d40->causas], '40/40 con un crítico abierto');
    igual('GO 97.5', $cerrar(2, 0, true), 'TC-001 falló y luego pasó: cuenta el último intento');
});

prueba('CA-11', 'RF-12 · Un intento es inmutable (todas sus propiedades readonly) y un intento nuevo NO borra el anterior', function () use ($registrar): void {
    $noReadonly = array_filter((new ReflectionClass(Intento::class))->getProperties(), static fn (ReflectionProperty $p): bool => !$p->isReadOnly());
    igual([], array_map(static fn ($p) => $p->getName(), array_values($noReadonly)), 'propiedades modificables');
    [$c, $r] = campana();
    $registrar($r, $c, 'TC-001', Resultado::FALLA, [new Captura(PNG)], [], defecto());
    $registrar($r, $c, 'TC-001', Resultado::PASA);
    igual(['FALLA 1', 'PASA 2'], array_map(static fn (Intento $i): string => $i->resultado->value . ' ' . $i->numero, $c->intentos()), 'historia');
});

prueba('CA-12', 'RF-13 · Idempotencia: la misma clave y contenido devuelve la misma respuesta sin duplicar; otro contenido → 409 CLAVE_REUTILIZADA', function () use ($registrar): void {
    [$c, $r] = campana();
    $a = $registrar($r, $c, 'TC-001', Resultado::PASA, clave: 'k-1');
    $b = $registrar($r, $c, 'TC-001', Resultado::PASA, clave: 'k-1');
    igual([true, 1], [$a === $b, count($c->intentos())], 'reintento');
    igual('CLAVE_REUTILIZADA', $registrar($r, $c, 'TC-002', Resultado::PASA, clave: 'k-1')->codigo, 'otro contenido');
});

prueba('CA-13', 'RF-14 · Sin qa.ejecutar en el tenant → 403 y nada registrado', function () use ($registrar): void {
    [$c, $r] = campana();
    igual(403, $registrar($r, $c, 'TC-001', Resultado::PASA, tester: 'dev-luis')->estado, 'sin permiso');
    igual([], $c->intentos(), 'nada');
});

prueba('CA-14', 'RNF-04 · Evidencia con datos clínicos: el informe se genera sin su contenido; verla exige qa.ver_evidencia_clinica', function () use ($registrar): void {
    [$c, $r] = campana();
    $clinica = new Captura(PNG, Clasificacion::DATOS_CLINICOS);
    $registrar($r, $c, 'TC-001', Resultado::FALLA, [$clinica], [], defecto());
    $informe = (new GenerarInforme())->ejecutar($c);
    afirmar(str_contains($informe, '[clínica]') && !str_contains($informe, 'IHDR'), "informe: $informe");
    $consultar = new ConsultarEvidencia(politica());
    igual([403, 200], [$consultar->ejecutar('tito-tester', 'hosp-norte', $clinica)->estado, $consultar->ejecutar('lider-qa', 'hosp-norte', $clinica)->estado], 'acceso');
});

prueba('CA-15', 'RNF-02 · Integridad: la evidencia guardada se relee y coincide; si alguien la altera en el almacén → ALTERADA', function () use ($registrar): void {
    [$c, $r, $almacen] = campana();
    $registrar($r, $c, 'TC-001', Resultado::FALLA, [new Captura(PNG)], [], defecto());
    $verificar = new VerificarIntegridad($almacen);
    igual('INTEGRA', $verificar->ejecutar($c->ultimo('TC-001')), 'recién registrada');
    $almacen->archivos[array_key_first($almacen->archivos)] .= 'X';
    igual('ALTERADA', $verificar->ejecutar($c->ultimo('TC-001')), 'alterada');
});

// ---------------------------------------------------------------------------
// LSP: la misma suite de contrato para cada subtipo
// ---------------------------------------------------------------------------
/**
 * Contrato de la evidencia, expresado con cierres para poder aplicarlo a las dos jerarquías.
 * @return list<string> las cláusulas que el subtipo viola
 */
function contrato(string $nombre, callable $contenido, callable $huella, callable $tamano): array
{
    $violaciones = [];
    try {
        $a = $contenido();
        $b = $contenido();
    } catch (\Throwable $e) {
        return ["$nombre: contenido() lanza " . (new ReflectionClass($e))->getShortName()];
    }
    if ($a !== $b) {
        $violaciones[] = "$nombre: contenido() cambia entre llamadas";
    }
    if ($huella() !== hash('sha256', $b)) {
        $violaciones[] = "$nombre: la huella no es sha256(contenido)";
    }
    if ($tamano() !== strlen($b)) {
        $violaciones[] = "$nombre: tamano() ≠ strlen(contenido)";
    }

    return $violaciones;
}

prueba('LSP-01', 'ANTES: la misma suite de contrato sobre los 4 subtipos de Evidencia → 2 la rompen (EnlaceExterno, CapturaConDatosClinicos)', function (): void {
    $log = 'build 812: 40 tests, 1 failure';
    $subtipos = [
        'Captura' => new QaAntes\Captura(PNG),
        'RegistroTexto' => new QaAntes\RegistroTexto('salida de consola'),
        'EnlaceExterno' => new QaAntes\EnlaceExterno('https://ci.example/812/log', static function () use (&$log): string {
            return $log .= '.';   // el log del pipeline sigue creciendo
        }),
        'CapturaConDatosClinicos' => new QaAntes\CapturaConDatosClinicos(PNG),
    ];
    $violaciones = [];
    foreach ($subtipos as $nombre => $e) {
        array_push($violaciones, ...contrato($nombre, $e->contenido(...), $e->hash(...), $e->tamano(...)));
    }
    igual([
        'EnlaceExterno: contenido() cambia entre llamadas',
        'EnlaceExterno: la huella no es sha256(contenido)',
        'EnlaceExterno: tamano() ≠ strlen(contenido)',
        'CapturaConDatosClinicos: contenido() lanza AccesoDenegado',
    ], $violaciones, 'violaciones del contrato');
});

prueba('LSP-02', 'DESPUÉS: la misma suite sobre los 8 subtipos (4 tipos × 2 clasificaciones) → 0 violaciones; los 4 métodos del contrato son final', function (): void {
    $violaciones = [];
    foreach ([Clasificacion::GENERAL, Clasificacion::DATOS_CLINICOS] as $cl) {
        foreach (['Captura PNG' => new Captura(PNG, $cl), 'Captura JPEG' => new Captura(JPG, $cl), 'RegistroTexto' => new RegistroTexto('salida ✓', $cl),
            'ResultadoJUnit' => new ResultadoJUnit(JUNIT, $cl)] as $nombre => $e) {
            afirmar($e instanceof EvidenciaVerificable, "$nombre no implementa el contrato");
            array_push($violaciones, ...contrato("$nombre/{$cl->value}", $e->contenido(...), $e->huella(...), $e->tamano(...)));
        }
    }
    igual([], $violaciones, 'violaciones');
    $noFinal = array_filter(['contenido', 'huella', 'tamano', 'clasificacion'], static fn (string $m): bool => !(new ReflectionMethod(EvidenciaInmutable::class, $m))->isFinal());
    igual([], array_values($noFinal), 'métodos del contrato que un subtipo podría sobrescribir');
    afirmar(!is_subclass_of(ReferenciaExterna::class, EvidenciaVerificable::class), 'un enlace no es una evidencia');
});

prueba('LSP-03', 'Consecuencia: el log del CI rota. ANTES la verificación dice INTEGRA (falso); DESPUÉS la copia guardada no cambia y una alteración real da ALTERADA', function () use ($registrar): void {
    // ANTES
    $log = 'build 812: 40 tests, 1 failure (TC-001)';
    $antes = new QaAntes\ServicioCampanaAntes();
    $antes->registrar('TC-001', 'FALLA', [new QaAntes\EnlaceExterno('https://ci.example/812/log', static function () use (&$log): string {
        return $log;
    })]);
    $log = 'build 812: log rotado';   // el pipeline reescribe el log
    igual('INTEGRA', $antes->verificarIntegridad(0), 'ANTES no detecta que la evidencia ya no muestra la falla');
    // DESPUÉS
    $pipeline = new PipelineSimulado(['https://ci.example/812/log' => 'build 812: 40 tests, 1 failure (TC-001)']);
    [$registro, $ref] = (new ImportarLogRemoto($pipeline))->ejecutar('https://ci.example/812/log');
    [$c, $r, $almacen] = campana();
    $registrar($r, $c, 'TC-001', Resultado::FALLA, [$registro], [$ref], defecto());
    $pipeline->logs['https://ci.example/812/log'] = 'build 812: log rotado';
    $verificar = new VerificarIntegridad($almacen);
    igual(['INTEGRA', 'build 812: 40 tests, 1 failure (TC-001)'], [$verificar->ejecutar($c->ultimo('TC-001')), $almacen->archivos[array_key_first($almacen->archivos)]],
        'la evidencia guardada sigue mostrando la falla');
    $almacen->archivos[array_key_first($almacen->archivos)] = 'build 812: 40 tests, 0 failures';
    igual('ALTERADA', $verificar->ejecutar($c->ultimo('TC-001')), 'alteración detectada');
});

prueba('LSP-04', 'Consecuencia: una captura con datos clínicos. ANTES el informe de la campaña LANZA (no hay informe); DESPUÉS se genera', function () use ($registrar): void {
    $antes = new QaAntes\ServicioCampanaAntes();
    $antes->registrar('TC-001', 'PASA', []);
    QaAntes\CapturaConDatosClinicos::$usuarioConPermiso = true;   // el tester sí puede ver los datos al registrar
    $antes->registrar('TC-002', 'FALLA', [new QaAntes\CapturaConDatosClinicos(PNG)]);
    QaAntes\CapturaConDatosClinicos::$usuarioConPermiso = false;  // quien arma el informe no
    $lanzo = null;
    try {
        $antes->informe();
    } catch (QaAntes\AccesoDenegado $e) {
        $lanzo = $e->getMessage();
    }
    igual('La captura contiene datos clínicos.', $lanzo, 'ANTES: el informe se rompe');
    [$c, $r] = campana();
    $registrar($r, $c, 'TC-001', Resultado::PASA);
    $registrar($r, $c, 'TC-002', Resultado::FALLA, [new Captura(PNG, Clasificacion::DATOS_CLINICOS)], [], defecto());
    igual(2, count(explode("\n", (new GenerarInforme())->ejecutar($c))), 'DESPUÉS: 2 líneas de informe');
});

prueba('LSP-05', 'Sustitución: un tipo nuevo (ResultadoJUnit de PHPUnit) se registra, se verifica y se informa sin cambiar ningún caso de uso', function () use ($registrar): void {
    [$c, $r, $almacen] = campana();
    $junit = new ResultadoJUnit(JUNIT);
    igual(['pruebas' => 3, 'fallas' => 1, 'errores' => 0], $junit->resumen(), 'extensión propia del subtipo');
    igual(201, $registrar($r, $c, 'TC-001', Resultado::FALLA, [$junit], [], defecto())->estado, 'registrado');
    igual('INTEGRA', (new VerificarIntegridad($almacen))->ejecutar($c->ultimo('TC-001')), 'verificado');
    afirmar(str_contains((new GenerarInforme())->ejecutar($c), 'junit'), 'informado');
    igual('TIPO_NO_PERMITIDO', rechazo(static fn () => new ResultadoJUnit('<html>no es JUnit</html>')), 'un XML cualquiera no es JUnit');
});

prueba('LSP-06', 'Los casos de uso no preguntan por el subtipo: 0 instanceof y 0 menciones a Captura o ResultadoJUnit en la Aplicación', function (): void {
    $codigo = (string) file_get_contents(__DIR__ . '/despues/src/Aplicacion/Aplicacion.php');
    $sinComentarios = implode('', array_map(static fn ($t) => is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : (is_array($t) ? $t[1] : $t), token_get_all($codigo)));
    igual([0, 0, 0], [substr_count($sinComentarios, 'instanceof'), substr_count($sinComentarios, 'Captura'), substr_count($sinComentarios, 'ResultadoJUnit')], 'menciones');
});

// ---------------------------------------------------------------------------
$fallas = 0;
echo 'Semana 2 · LSP · QA y regresión — verificación (PHP ' . PHP_VERSION . ')' . PHP_EOL . PHP_EOL;
foreach ($resultados as [$id, $descripcion, $estado, $detalle]) {
    $fallas += $estado === 'FALLA' ? 1 : 0;
    printf("[%-5s] %-7s %s\n", $estado, $id, $descripcion);
    if ($detalle !== null) {
        echo "          -> $detalle\n";
    }
}
printf("\nTotal: %d  Pasan: %d  Fallan: %d\n", count($resultados), count($resultados) - $fallas, $fallas);
exit($fallas > 0 ? 1 : 0);
