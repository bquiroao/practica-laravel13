<?php

declare(strict_types=1);

/*
 * Evidencia verificable de la semana 2.
 * Ejecuta los criterios de aceptación (CA) contra el diseño DESPUÉS, verifica que las
 * extensiones cumplen OCP y compara el comportamiento con el diseño ANTES.
 *
 * Uso:  php docs/modulos/gobernanza-auditoria/semana-02/ejemplo-ocp/verificar.php
 * Sale con código 0 si todas las pruebas pasan, 1 si alguna falla.
 */

require __DIR__ . '/despues/autoload.php';
require __DIR__ . '/despues/composicion.php';
require __DIR__ . '/antes/BitacoraAuditoria.php';

use Auditoria\Antes\BitacoraAuditoria;
use Auditoria\Contratos\Bitacora;
use Auditoria\Dominio\Movimiento;
use Auditoria\Dominio\Sello;
use Auditoria\Dominio\SolicitudRegistro;
use Auditoria\EnMemoria\BitacoraEnMemoria;
use Auditoria\EnMemoria\PoliticaAccesoEnMemoria;
use Auditoria\EnMemoria\RelojFijo;
use Auditoria\EnMemoria\TransaccionEnMemoria;
use Auditoria\Servicios\ServicioConsulta;
use Auditoria\Servicios\ServicioRegistro;
use Auditoria\Servicios\VerificadorIntegridad;

// ---------------------------------------------------------------------------
// Mini arnés de pruebas (sin dependencias)
// ---------------------------------------------------------------------------

$resultados = [];

function prueba(string $id, string $descripcion, callable $cuerpo): void
{
    global $resultados;
    try {
        $cuerpo();
        $resultados[] = [$id, $descripcion, null];
    } catch (\Throwable $e) {
        $resultados[] = [$id, $descripcion, $e->getMessage()];
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
// Escenario: dos hospitales, tres cuentas de servicio, un auditor y el super administrador
// ---------------------------------------------------------------------------

const AHORA = '2026-10-21T12:00:00-06:00';

function permisos(): array
{
    return [
        'svc-pacientes|hosp-oriente' => ['auditoria.registrar'],
        'svc-pacientes|hosp-norte' => ['auditoria.registrar'],
        'svc-rbac|hosp-oriente' => ['auditoria.registrar'],
        'svc-sin-permiso|hosp-oriente' => [],
        'auditor-oriente|hosp-oriente' => ['auditoria.consultar'],
        'superadmin|hosp-oriente' => ['auditoria.consultar'],
        'superadmin|hosp-norte' => ['auditoria.consultar'],
        'superadmin|*' => ['auditoria.consultar_red', 'auditoria.verificar'],
    ];
}

function sistema(bool $conExtensiones = true): object
{
    $reloj = new RelojFijo(new DateTimeImmutable(AHORA));
    $bitacora = new BitacoraEnMemoria();
    $transaccion = new TransaccionEnMemoria($bitacora);
    $acceso = new PoliticaAccesoEnMemoria(permisos());
    $registro = new ServicioRegistro(reglasDeRegistro($reloj, $conExtensiones), $bitacora, $transaccion, $acceso);

    return (object) [
        'bitacora' => $bitacora,
        'registro' => $registro,
        'consulta' => new ServicioConsulta(filtrosDeConsulta($conExtensiones), $bitacora, $transaccion, $acceso, $reloj, $registro),
        'verificador' => new VerificadorIntegridad($bitacora, $acceso),
    ];
}

/** Movimiento de ejemplo de Pacientes en hosp-oriente, con cambios. */
function mov(array $cambios = []): array
{
    return array_merge([
        'event_id' => 'ev-1', 'hospital' => 'hosp-oriente', 'usuario' => 'u-ana', 'modulo' => 'pacientes',
        'accion' => 'PACIENTE_REGISTRADO', 'ocurrido_en' => '2026-10-21T09:15:00-06:00', 'resultado' => 'exito',
        'recurso' => 'paciente:P-000001', 'detalle' => ['codigo' => 'P-000001'],
    ], $cambios);
}

function registrar(object $s, array $datos, string $emisor = 'svc-pacientes', ?string $hospital = 'hosp-oriente'): array
{
    return $s->registro->registrar(new SolicitudRegistro($emisor, $hospital, $datos))->comoArreglo();
}

function consultar(object $s, array $p, string $actor = 'auditor-oriente', ?string $hospital = 'hosp-oriente', string $alcance = 'hospital'): array
{
    return $s->consulta->consultar($actor, $hospital, $alcance, $p)->comoArreglo();
}

function par(array $r): array
{
    return [$r['estado'], $r['codigo']];
}

$rangoHoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];

// ---------------------------------------------------------------------------
// Criterios de aceptación
// ---------------------------------------------------------------------------

prueba('CA-01', 'Registro válido: 201 con secuencia 1 y sello; el segundo queda encadenado (secuencia 2)', function (): void {
    $s = sistema();
    $r1 = registrar($s, mov());
    $r2 = registrar($s, mov(['event_id' => 'ev-2']));
    igual(['creado', 1, 2], [$r1['estado'], $r1['datos']['secuencia'], $r2['datos']['secuencia']], 'estado y secuencias');
    afirmar(preg_match('/^[0-9a-f]{64}$/', $r1['datos']['sello']) === 1, 'el sello no es un sha256');
    afirmar($r1['datos']['sello'] !== $r2['datos']['sello'], 'dos movimientos con el mismo sello');
});

prueba('CA-02', 'Sin «auditoria.registrar»: 403 ACCESO_DENEGADO y no se guarda nada', function (): void {
    $s = sistema();
    igual(['prohibido', 'ACCESO_DENEGADO'], par(registrar($s, mov(), 'svc-sin-permiso')), 'resultado');
    igual(0, count($s->bitacora->todos()), 'movimientos guardados');
});

prueba('CA-03', 'Movimiento de otro hospital: 403 TENANT_NO_COINCIDE, aunque además traiga campos inválidos', function (): void {
    $s = sistema();
    igual(['prohibido', 'TENANT_NO_COINCIDE'], par(registrar($s, mov(['hospital' => 'hosp-norte', 'usuario' => '']))), 'otro hospital');
});

prueba('CA-04', 'Campos obligatorios: 422 DATOS_INVALIDOS con TODOS los que faltan', function (): void {
    $s = sistema();
    $r = registrar($s, ['hospital' => 'hosp-oriente', 'modulo' => 'pacientes']);
    igual(['invalido', 'DATOS_INVALIDOS'], par($r), 'resultado');
    igual(['event_id', 'usuario', 'accion', 'ocurrido_en', 'resultado'], array_keys($r['datos']['campos']), 'campos');
});

prueba('CA-05', 'Acción fuera del catálogo del módulo y resultado desconocido: 422', function (): void {
    $s = sistema();
    igual('accion', array_key_first(registrar($s, mov(['accion' => 'ROL_ASIGNADO']))['datos']['campos']), 'acción de otro módulo');
    igual('resultado', array_key_first(registrar($s, mov(['resultado' => 'ok']))['datos']['campos']), 'resultado');
});

prueba('CA-06', 'Fecha: +4 min se acepta (reloj desfasado), +6 min es futura (422), sin zona es inválida (422)', function (): void {
    $s = sistema();
    igual('creado', registrar($s, mov(['ocurrido_en' => '2026-10-21T12:04:00-06:00']))['estado'], '+4 min');
    igual(['ocurrido_en' => 'No puede ser futura.'], registrar($s, mov(['event_id' => 'ev-2', 'ocurrido_en' => '2026-10-21T12:06:00-06:00']))['datos']['campos'], '+6 min');
    igual(['ocurrido_en' => 'Fecha ISO 8601 con zona.'], registrar($s, mov(['event_id' => 'ev-3', 'ocurrido_en' => '2026-10-21 09:15']))['datos']['campos'], 'sin zona');
});

prueba('CA-07', 'Datos prohibidos en el detalle (password, token anidado, diagnóstico): 422', function (): void {
    $s = sistema();
    foreach ([['password' => 'x'], ['sesion' => ['token' => 'abc']], ['Diagnostico' => 'J45']] as $detalle) {
        igual(['detalle' => 'Contiene datos prohibidos.'], registrar($s, mov(['detalle' => $detalle]))['datos']['campos'] ?? null, json_encode($detalle));
    }
    igual(0, count($s->bitacora->todos()), 'movimientos guardados');
});

prueba('CA-08', 'Idempotencia: el reintento idéntico da 200 YA_REGISTRADO sin duplicar; con otro contenido, 409', function (): void {
    $s = sistema();
    $original = registrar($s, mov());
    $reintento = registrar($s, mov());
    igual(['ok', 'YA_REGISTRADO', $original['datos']['id']], [$reintento['estado'], $reintento['codigo'], $reintento['datos']['id']], 'reintento');
    igual(['conflicto', 'EVENTO_EN_CONFLICTO'], par(registrar($s, mov(['usuario' => 'u-otro']))), 'mismo event_id, otro contenido');
    igual(1, count($s->bitacora->todos()), 'movimientos guardados');
});

prueba('CA-09', 'Base caída al registrar: 503 y nada a medias; al volver, el reintento crea el movimiento', function (): void {
    $s = sistema();
    $s->bitacora->fallarEn = ['agregar'];
    igual(['no_disponible', 'SERVICIO_NO_DISPONIBLE'], par(registrar($s, mov())), 'caída');
    igual(0, count($s->bitacora->todos()), 'movimientos guardados');
    $s->bitacora->fallarEn = [];
    igual('creado', registrar($s, mov())['estado'], 'reintento con el mismo event_id');
});

prueba('CA-10', 'Inmutabilidad: ni la Bitacora ni los servicios ofrecen actualizar o borrar', function (): void {
    foreach ([Bitacora::class, ServicioRegistro::class, ServicioConsulta::class] as $clase) {
        foreach ((new ReflectionClass($clase))->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
            afirmar(preg_match('/^(actualizar|modificar|editar|borrar|eliminar|update|delete)/i', $metodo->getName()) !== 1,
                "$clase::{$metodo->getName()} permitiría cambiar la bitácora");
        }
    }
    afirmar(!(new ReflectionClass(Movimiento::class))->hasMethod('__set'), 'Movimiento admite __set');
});

prueba('CA-11', 'Sello encadenado: INTEGRA; alterar la fila 2 → ALTERADA en 2; si además se recalcula su sello → ALTERADA en 3', function (): void {
    $s = sistema();
    for ($i = 1; $i <= 4; $i++) {
        registrar($s, mov(['event_id' => "ev-$i"]));
    }
    igual(['estado' => 'INTEGRA', 'verificados' => 4], $s->verificador->verificar('superadmin', 'hosp-oriente')->datos, 'antes de alterar');
    $original = $s->bitacora->porId(2);
    $alterado = Movimiento::desdeSolicitud(new SolicitudRegistro('svc-pacientes', 'hosp-oriente', mov(['event_id' => 'ev-2', 'usuario' => 'u-encubierto'])))
        ->sellado(2, $original->secuencia, $original->sello);   // el atacante conserva el sello viejo
    $s->bitacora->alterarParaPrueba(2, $alterado);
    igual(['estado' => 'ALTERADA', 'primera_secuencia' => 2], $s->verificador->verificar('superadmin', 'hosp-oriente')->datos, 'después de alterar');
    // Atacante que conoce el algoritmo: recalcula el sello de la fila 2. Lo delata la fila 3, que dependía del sello viejo.
    $anterior = $s->bitacora->porId(1)->sello;
    $s->bitacora->alterarParaPrueba(2, $alterado->sellado(2, 2, Sello::calcular($anterior, 2, $alterado)));
    igual(['estado' => 'ALTERADA', 'primera_secuencia' => 3], $s->verificador->verificar('superadmin', 'hosp-oriente')->datos, 'sello recalculado');
    igual(['prohibido', 'ACCESO_DENEGADO'], par($s->verificador->verificar('auditor-oriente', 'hosp-oriente')->comoArreglo()), 'el auditor no verifica');
});

prueba('CA-12', 'Consulta sin rango, con un solo extremo, con fecha inexistente o invertida: 422', function () use ($rangoHoy): void {
    $s = sistema();
    foreach ([[], ['desde' => '2026-10-01'], ['desde' => '2026-02-30', 'hasta' => '2026-03-01'], ['desde' => '2026-10-22', 'hasta' => '2026-10-21'],
        ['usuario' => 'u-ana']] as $p) {
        igual(['invalido', 'DATOS_INVALIDOS'], par(consultar($s, $p)), json_encode($p));
    }
    igual('ok', consultar($s, $rangoHoy)['estado'], 'rango válido de un día');
});

prueba('CA-13', 'Filtros combinados (usuario + módulo + acción) y «hasta» inclusive en hora local (23:30 sí, 00:10 del día siguiente no)', function (): void {
    $s = sistema();
    registrar($s, mov(['event_id' => 'a', 'ocurrido_en' => '2026-10-20T23:30:00-06:00']));                                  // id 1
    registrar($s, mov(['event_id' => 'b', 'ocurrido_en' => '2026-10-21T00:10:00-06:00']));                                  // id 2
    registrar($s, mov(['event_id' => 'c', 'usuario' => 'u-luis', 'ocurrido_en' => '2026-10-20T10:00:00-06:00']));          // id 3
    registrar($s, mov(['event_id' => 'd', 'accion' => 'PACIENTE_ACTUALIZADO', 'ocurrido_en' => '2026-10-20T11:00:00-06:00'])); // id 4
    registrar($s, ['event_id' => 'e', 'modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO'] + mov(['ocurrido_en' => '2026-10-20T12:00:00-06:00']), 'svc-rbac'); // id 5
    $dia20 = ['desde' => '2026-10-20', 'hasta' => '2026-10-20'];
    igual([1, 5, 4, 3], consultar($s, $dia20)['datos']['ids'], 'todo el 20, descendente (la 23:30 local es 05:30 UTC del 21 y entra; la 00:10 del 21 no)');
    igual([1, 4], consultar($s, $dia20 + ['usuario' => 'u-ana', 'modulo' => 'pacientes'])['datos']['ids'], 'usuario + módulo');
    igual([4], consultar($s, $dia20 + ['usuario' => 'u-ana', 'modulo' => 'pacientes', 'accion' => 'PACIENTE_ACTUALIZADO'])['datos']['ids'], '+ acción');
    igual([], consultar($s, $dia20 + ['usuario' => 'u-nadie'])['datos']['ids'], 'sin resultados: 200 con lista vacía');
});

prueba('CA-14', 'Alcance: el auditor solo ve su hospital y no la red (403); el super administrador ve la red', function (): void {
    $s = sistema();
    registrar($s, mov(['event_id' => 'o']));
    registrar($s, mov(['event_id' => 'n', 'hospital' => 'hosp-norte']), 'svc-pacientes', 'hosp-norte');
    $hoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
    igual([1], consultar($s, $hoy)['datos']['ids'], 'auditor de oriente');
    igual(['prohibido', 'ACCESO_DENEGADO'], par(consultar($s, $hoy, 'auditor-oriente', 'hosp-oriente', 'red')), 'auditor pide la red');
    igual(['prohibido', 'ACCESO_DENEGADO'], par(consultar($s, $hoy, 'auditor-oriente', 'hosp-norte')), 'auditor pide norte');
    $red = consultar($s, $hoy, 'superadmin', null, 'red')['datos']['ids'];
    afirmar(in_array(1, $red, true) && in_array(2, $red, true), 'el super administrador no ve los dos hospitales: ' . json_encode($red));
});

prueba('CA-15', 'Página de 50 en orden descendente, con «hay_mas»; 50 exactos no tienen página siguiente', function (): void {
    $s = sistema();
    for ($i = 1; $i <= 51; $i++) {
        registrar($s, mov(['event_id' => "ev-$i", 'ocurrido_en' => sprintf('2026-10-21T08:%02d:00-06:00', $i - 1)]));
    }
    $r = consultar($s, ['desde' => '2026-10-21', 'hasta' => '2026-10-21']);
    igual([50, 51, 2, true], [count($r['datos']['ids']), $r['datos']['ids'][0], $r['datos']['ids'][49], $r['datos']['hay_mas']], 'página');
    $s2 = sistema();
    for ($i = 1; $i <= 50; $i++) {
        registrar($s2, mov(['event_id' => "ev-$i"]));
    }
    igual(false, consultar($s2, ['desde' => '2026-10-21', 'hasta' => '2026-10-21'])['datos']['hay_mas'], '50 exactos');
});

prueba('CA-16', 'Meta-auditoría: cada consulta deja AUDITORIA_CONSULTADA; si no se puede registrar, no se entregan resultados (503)', function (): void {
    $s = sistema();
    registrar($s, mov());
    consultar($s, ['desde' => '2026-10-21', 'hasta' => '2026-10-21', 'usuario' => 'u-ana']);
    $meta = $s->bitacora->porId(2);
    igual(['AUDITORIA_CONSULTADA', 'auditor-oriente', ['usuario' => 'u-ana'], 1],
        [$meta->accion, $meta->usuario, array_intersect_key($meta->detalle['filtros'], ['usuario' => 1]), $meta->detalle['resultados']], 'evento');
    igual(2, $meta->secuencia, 'la meta-auditoría también se encadena');
    $s->bitacora->fallarEn = ['agregar'];
    igual(['no_disponible', 'SERVICIO_NO_DISPONIBLE'], par(consultar($s, ['desde' => '2026-10-21', 'hasta' => '2026-10-21'])), 'sin meta-auditoría');
});

prueba('CA-17', 'Detalle: el de otro hospital da el MISMO 404 que uno inexistente; el propio se audita', function (): void {
    $s = sistema();
    registrar($s, mov(['event_id' => 'n', 'hospital' => 'hosp-norte']), 'svc-pacientes', 'hosp-norte');
    $ajeno = $s->consulta->detalle('auditor-oriente', 'hosp-oriente', 'hospital', 1)->comoArreglo();
    $inexistente = $s->consulta->detalle('auditor-oriente', 'hosp-oriente', 'hospital', 99)->comoArreglo();
    igual($inexistente, $ajeno, 'ajeno frente a inexistente');
    igual('no_encontrado', $ajeno['estado'], 'estado');
    $propio = $s->consulta->detalle('superadmin', 'hosp-norte', 'hospital', 1)->comoArreglo();
    igual(['ok', 'u-ana', 1], [$propio['estado'], $propio['datos']['movimiento']['usuario'], $propio['datos']['movimiento']['secuencia']], 'detalle propio');
    igual('AUDITORIA_CONSULTADA', $s->bitacora->porId(2)?->accion, 'el detalle quedó auditado');
});

// ---------------------------------------------------------------------------
// OCP
// ---------------------------------------------------------------------------

prueba('OCP-01', 'Extensiones nuevas sin tocar el núcleo: filtro por resultado y regla «cada cuenta solo su módulo»', function (): void {
    $s = sistema();
    registrar($s, mov(['event_id' => 'a']));
    registrar($s, mov(['event_id' => 'b', 'resultado' => 'denegado']));
    $hoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
    igual([2], consultar($s, $hoy + ['resultado' => 'denegado'])['datos']['ids'], 'solo denegados');
    igual(['invalido', 'DATOS_INVALIDOS'], par(consultar($s, $hoy + ['resultado' => 'quizas'])), 'valor inválido');
    igual(['prohibido', 'MODULO_NO_PERMITIDO'], par(registrar($s, mov(['event_id' => 'c', 'modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO']))),
        'svc-pacientes escribe a nombre de RBAC');
});

prueba('OCP-02', 'Los servicios no conocen reglas ni filtros concretos (análisis del código) y los reciben como iterable', function (): void {
    foreach (glob(__DIR__ . '/despues/src/Servicios/*.php') as $archivo) {
        $codigo = (string) file_get_contents($archivo);
        foreach (['Auditoria\\Reglas\\', 'Auditoria\\Filtros\\', 'Auditoria\\Extension\\', 'new Regla', 'new Filtro'] as $prohibido) {
            afirmar(!str_contains($codigo, $prohibido), basename($archivo) . " menciona «{$prohibido}»");
        }
    }
    foreach ([ServicioRegistro::class => 'reglas', ServicioConsulta::class => 'filtros'] as $clase => $parametro) {
        $tipo = (string) (new ReflectionMethod($clase, '__construct'))->getParameters()[0]->getType();
        igual('iterable', $tipo, "$clase::\$$parametro");
    }
});

prueba('OCP-03', 'Sin las extensiones (misma clase de servicio): «resultado» pasa a ser FILTRO_NO_SOPORTADO y la regla de módulo desaparece', function (): void {
    $s = sistema(false);
    igual(['invalido', 'FILTRO_NO_SOPORTADO'], par(consultar($s, ['desde' => '2026-10-21', 'hasta' => '2026-10-21', 'resultado' => 'denegado'])), 'filtro retirado');
    igual('creado', registrar($s, mov(['modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO']))['estado'], 'regla retirada');
});

prueba('PAR-01', 'Antes y después: el MISMO resultado (estado, código, ids, secuencias y sellos) en 16 pasos', function (): void {
    $antes = new BitacoraAuditoria(permisos(), new DateTimeImmutable(AHORA));
    $despues = sistema();
    $hoy = ['desde' => '2026-10-21', 'hasta' => '2026-10-21'];
    $pasos = [
        ['r', mov(), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(['usuario' => 'x']), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(['event_id' => '2', 'hospital' => 'hosp-norte']), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(['event_id' => '3', 'accion' => 'NO_EXISTE']), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(['event_id' => '4', 'ocurrido_en' => '2026-10-21T13:00:00-06:00']), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(['event_id' => '5', 'detalle' => ['token' => 't']]), 'svc-pacientes', 'hosp-oriente'],
        ['r', mov(['event_id' => '6', 'hospital' => 'hosp-norte']), 'svc-pacientes', 'hosp-norte'],
        ['r', ['event_id' => '7', 'modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO'] + mov(['resultado' => 'denegado']), 'svc-rbac', 'hosp-oriente'],
        ['r', mov(['event_id' => '8']), 'svc-sin-permiso', 'hosp-oriente'],
        ['c', $hoy, 'auditor-oriente', 'hosp-oriente', 'hospital'],
        ['c', $hoy + ['usuario' => 'u-ana', 'modulo' => 'rbac'], 'auditor-oriente', 'hosp-oriente', 'hospital'],
        ['c', $hoy, 'superadmin', null, 'red'],
        ['c', $hoy, 'auditor-oriente', 'hosp-oriente', 'red'],
        ['c', ['desde' => '2026-10-21'], 'auditor-oriente', 'hosp-oriente', 'hospital'],
        ['c', $hoy + ['ip' => '10.0.0.1'], 'auditor-oriente', 'hosp-oriente', 'hospital'],
    ];
    foreach ($pasos as $i => $paso) {
        if ($paso[0] === 'r') {
            $a = $antes->registrar($paso[2], $paso[3], $paso[1]);
            $d = registrar($despues, $paso[1], $paso[2], $paso[3]);
        } else {
            $a = $antes->consultar($paso[2], $paso[3], $paso[4], $paso[1]);
            $d = consultar($despues, $paso[1], $paso[2], $paso[3], $paso[4]);
        }
        unset($a['datos']['campos'], $d['datos']['campos']);
        igual($a, $d, 'paso ' . ($i + 1));
    }
});

// ---------------------------------------------------------------------------

$fallan = 0;
echo 'Auditoría semana 2 - verificacion OCP (PHP ' . PHP_VERSION . ')' . PHP_EOL . str_repeat('-', 78) . PHP_EOL;
foreach ($resultados as [$id, $descripcion, $error]) {
    printf("[%s] %-7s %s\n", $error === null ? 'PASA' : 'FALLA', $id, $descripcion);
    if ($error !== null) {
        $fallan++;
        echo "         -> $error" . PHP_EOL;
    }
}
echo str_repeat('-', 78) . PHP_EOL . sprintf('Total: %d  Pasan: %d  Fallan: %d', count($resultados), count($resultados) - $fallan, $fallan) . PHP_EOL;
exit($fallan === 0 ? 0 : 1);
