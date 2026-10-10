<?php

declare(strict_types=1);

/*
 * Evidencia verificable de la semana 2.
 * Ejecuta los criterios de aceptación (CA) contra el diseño DESPUÉS, verifica
 * que la extensión cumple OCP y compara el comportamiento con el diseño ANTES.
 *
 * Uso:  php docs/modulos/rbac/semana-02/ejemplo-ocp/verificar.php
 * Sale con código 0 si todas las pruebas pasan, 1 si alguna falla.
 */

require __DIR__ . '/despues/autoload.php';
require __DIR__ . '/despues/composicion.php';
require __DIR__ . '/antes/ServicioAutorizacion.php';

use Rbac\Antes\ServicioAutorizacion as ServicioAutorizacionAntes;
use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Dominio\ContextoAutorizacion;
use Rbac\Dominio\Membresia;
use Rbac\Dominio\SolicitudAsignacion;
use Rbac\Dominio\SolicitudAutorizacion;
use Rbac\EnMemoria\CatalogoPermisosEnMemoria;
use Rbac\EnMemoria\DirectorioUsuariosEnMemoria;
use Rbac\EnMemoria\RegistroAuditoriaEnMemoria;
use Rbac\EnMemoria\RegistroAuditoriaQueFalla;
use Rbac\EnMemoria\RepositorioMembresiasEnMemoria;
use Rbac\EnMemoria\TransaccionEnMemoria;
use Rbac\Reglas\ReglaMembresiaVigente;
use Rbac\Reglas\ReglaRolConcedePermiso;
use Rbac\Reglas\ReglaTenantCoincide;
use Rbac\Servicios\Autorizador;
use Rbac\Servicios\ServicioAsignacionRol;

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
// Datos ficticios
// ---------------------------------------------------------------------------

const H_ORIENTE = 'hosp-oriente';
const H_NORTE = 'hosp-norte';
const IP_INTERNA = '10.20.5.14';
const IP_EXTERNA = '190.56.10.8';

$AHORA = new \DateTimeImmutable('2026-11-15T10:00:00-06:00');

$PERMISOS = [
    'admin_hospital' => ['roles.asignar', 'auditoria.consultar'],
    'medico' => ['prescripciones.crear', 'signos_vitales.registrar', 'expediente.leer'],
    'enfermeria' => ['signos_vitales.registrar', 'expediente.leer'],
    'laboratorio' => ['resultados.registrar'],
];

/** @return list<Membresia> */
function membresiasIniciales(): array
{
    $f = static fn (string $fecha): \DateTimeImmutable => new \DateTimeImmutable($fecha);

    return [
        new Membresia('admin-01', H_ORIENTE, 'admin_hospital', 'active', $f('2026-01-01T00:00:00-06:00')),
        new Membresia('ana', H_ORIENTE, 'enfermeria', 'active', $f('2026-11-01T00:00:00-06:00')),
        new Membresia('marta', H_ORIENTE, 'medico', 'active', $f('2026-02-01T00:00:00-06:00')),
        new Membresia('pedro', H_ORIENTE, 'medico', 'active', $f('2026-01-01T00:00:00-06:00'), $f('2026-06-30T00:00:00-06:00')),
        new Membresia('lucia', H_ORIENTE, 'enfermeria', 'revoked', $f('2026-01-01T00:00:00-06:00')),
        new Membresia('carlos', H_ORIENTE, 'medico', 'active', $f('2027-01-01T00:00:00-06:00')),
    ];
}

/**
 * Arma un entorno nuevo e independiente para cada prueba.
 *
 * @param list<ReglaAutorizacion>|null $reglas
 */
function entorno(bool $auditoriaFalla = false, ?array $reglas = null, bool $respaldoFalla = false): object
{
    global $PERMISOS;

    $membresias = new RepositorioMembresiasEnMemoria(membresiasIniciales());
    $catalogo = new CatalogoPermisosEnMemoria($PERMISOS);
    $usuarios = new DirectorioUsuariosEnMemoria([
        'admin-01' => 'active', 'ana' => 'active', 'marta' => 'active', 'nuevo-01' => 'active', 'jorge' => 'inactive',
    ]);
    $auditoria = new RegistroAuditoriaEnMemoria();
    $respaldo = new RegistroAuditoriaEnMemoria();
    $contador = 0;
    $correlacion = static function () use (&$contador): string {
        return sprintf('corr-%04d', ++$contador);
    };

    $autorizador = new Autorizador(
        $reglas ?? reglasDeAutorizacion($catalogo),
        $membresias,
        $auditoriaFalla ? new RegistroAuditoriaQueFalla() : $auditoria,
        $respaldoFalla ? new RegistroAuditoriaQueFalla() : $respaldo,
        $correlacion,
    );

    $asignacion = new ServicioAsignacionRol(
        $autorizador,
        reglasDeAsignacion($usuarios, $catalogo, $membresias),
        $membresias,
        $auditoriaFalla ? new RegistroAuditoriaQueFalla() : $auditoria,
        new TransaccionEnMemoria($membresias),
        $correlacion,
    );

    return (object) compact('membresias', 'catalogo', 'auditoria', 'respaldo', 'autorizador', 'asignacion');
}

function pedirOperacion(string $usuario, string $permiso, string $tenantToken = H_ORIENTE, string $tenantSolicitud = H_ORIENTE, string $ip = IP_INTERNA): SolicitudAutorizacion
{
    global $AHORA;

    return new SolicitudAutorizacion($usuario, $tenantToken, $tenantSolicitud, $permiso, 'recurso-demo', $ip, $AHORA);
}

function pedirAsignacion(string $admin, string $destino, string $rol): SolicitudAsignacion
{
    global $AHORA;

    return new SolicitudAsignacion($admin, H_ORIENTE, H_ORIENTE, $destino, $rol, new \DateTimeImmutable('2026-11-15T00:00:00-06:00'), null, IP_INTERNA, $AHORA);
}

// ---------------------------------------------------------------------------
// Criterios de aceptación — Asignación de rol (CU-01)
// ---------------------------------------------------------------------------

prueba('CA-01', 'Asignación válida crea membresía activa y registra ROL_ASIGNADO (201)', function (): void {
    $e = entorno();
    $r = $e->asignacion->asignar(pedirAsignacion('admin-01', 'nuevo-01', 'laboratorio'));
    igual(201, $r->codigoHttp, 'HTTP');
    afirmar($e->membresias->existeActiva('nuevo-01', H_ORIENTE, 'laboratorio'), 'la membresía no se creó');
    $evento = $e->auditoria->buscarPorCorrelacion((string) $r->correlationId);
    igual('ROL_ASIGNADO', $evento?->tipo, 'tipo de evento');
    igual('admin-01', $evento?->actorId, 'actor del evento');
});

prueba('CA-02', 'Usuario sin «roles.asignar» recibe 403 y se registra ACCESO_DENEGADO', function (): void {
    $e = entorno();
    $antes = $e->membresias->total();
    $r = $e->asignacion->asignar(pedirAsignacion('ana', 'nuevo-01', 'medico'));
    igual(403, $r->codigoHttp, 'HTTP');
    igual('PERMISO_NO_ASIGNADO', $r->codigo, 'motivo');
    igual($antes, $e->membresias->total(), 'membresías');
    igual('ACCESO_DENEGADO', $e->auditoria->buscarPorCorrelacion((string) $r->correlationId)?->tipo, 'evento');
});

prueba('CA-03', 'Autoasignación de rol se rechaza (422)', function (): void {
    $r = entorno()->asignacion->asignar(pedirAsignacion('admin-01', 'admin-01', 'medico'));
    igual(422, $r->codigoHttp, 'HTTP');
    igual('AUTOASIGNACION', $r->codigo, 'código');
});

prueba('CA-04', 'Usuario destino inexistente (404) o inactivo (422)', function (): void {
    $e = entorno();
    igual(404, $e->asignacion->asignar(pedirAsignacion('admin-01', 'fantasma', 'medico'))->codigoHttp, 'inexistente');
    igual(422, $e->asignacion->asignar(pedirAsignacion('admin-01', 'jorge', 'medico'))->codigoHttp, 'inactivo');
});

prueba('CA-05', 'Rol que no existe en el catálogo se rechaza (422)', function (): void {
    $r = entorno()->asignacion->asignar(pedirAsignacion('admin-01', 'nuevo-01', 'superusuario'));
    igual(422, $r->codigoHttp, 'HTTP');
    igual('ROL_INVALIDO', $r->codigo, 'código');
});

prueba('CA-06', 'Membresía activa duplicada se rechaza (409)', function (): void {
    $r = entorno()->asignacion->asignar(pedirAsignacion('admin-01', 'ana', 'enfermeria'));
    igual(409, $r->codigoHttp, 'HTTP');
    igual('ASIGNACION_DUPLICADA', $r->codigo, 'código');
});

prueba('CA-07', 'Si la bitácora falla, la asignación se revierte (500, sin membresía)', function (): void {
    // La bitácora falla solo al escribir ROL_ASIGNADO; el admin sí está autorizado.
    global $PERMISOS;
    $membresias = new RepositorioMembresiasEnMemoria(membresiasIniciales());
    $catalogo = new CatalogoPermisosEnMemoria($PERMISOS);
    $auditoria = new RegistroAuditoriaEnMemoria();
    $autorizador = new Autorizador(reglasDeAutorizacion($catalogo), $membresias, $auditoria, $auditoria, static fn (): string => 'corr-x');
    $servicio = new ServicioAsignacionRol(
        $autorizador,
        reglasDeAsignacion(new DirectorioUsuariosEnMemoria(['nuevo-01' => 'active']), $catalogo, $membresias),
        $membresias,
        new RegistroAuditoriaQueFalla(),
        new TransaccionEnMemoria($membresias),
        static fn (): string => 'corr-y',
    );
    $antes = $membresias->total();
    $r = $servicio->asignar(pedirAsignacion('admin-01', 'nuevo-01', 'laboratorio'));
    igual(500, $r->codigoHttp, 'HTTP');
    igual($antes, $membresias->total(), 'membresías tras rollback');
    afirmar(!$membresias->existeActiva('nuevo-01', H_ORIENTE, 'laboratorio'), 'quedó una membresía sin evidencia');
});

// ---------------------------------------------------------------------------
// Criterios de aceptación — Autorización y denegación trazable (CU-02 a CU-06)
// ---------------------------------------------------------------------------

prueba('CA-08', 'Operación permitida por el rol vigente (200) sin registrar denegación', function (): void {
    $e = entorno();
    $d = $e->autorizador->autorizar(pedirOperacion('ana', 'signos_vitales.registrar'));
    afirmar($d->permitido, 'debió permitirse');
    igual(200, $d->codigoHttp, 'HTTP');
    igual(0, count($e->auditoria->eventos()), 'eventos de denegación');
});

prueba('CA-09', 'Permiso no asignado: 403 + correlation_id + evento con todos los campos', function (): void {
    $e = entorno();
    $d = $e->autorizador->autorizar(pedirOperacion('ana', 'prescripciones.crear'));
    igual(403, $d->codigoHttp, 'HTTP');
    igual('PERMISO_NO_ASIGNADO', $d->motivo, 'motivo');
    afirmar($d->correlationId !== null, 'falta correlation_id');
    $ev = $e->auditoria->buscarPorCorrelacion((string) $d->correlationId);
    igual('ACCESO_DENEGADO', $ev?->tipo, 'tipo');
    igual('ana', $ev?->actorId, 'actor');
    igual(H_ORIENTE, $ev?->hospitalId, 'hospital');
    igual('prescripciones.crear', $ev?->datos['permiso'] ?? null, 'permiso');
    igual('recurso-demo', $ev?->datos['recurso'] ?? null, 'recurso');
    igual(IP_INTERNA, $ev?->datos['ip'] ?? null, 'ip');
});

prueba('CA-10', 'Tenant del token distinto al de la solicitud: 403 TENANT_NO_COINCIDE', function (): void {
    $d = entorno()->autorizador->autorizar(pedirOperacion('ana', 'signos_vitales.registrar', H_ORIENTE, H_NORTE));
    igual(403, $d->codigoHttp, 'HTTP');
    igual('TENANT_NO_COINCIDE', $d->motivo, 'motivo');
});

prueba('CA-11', 'Membresía vencida, revocada, futura o inexistente: MEMBRESIA_NO_VIGENTE', function (): void {
    $e = entorno();
    foreach (['pedro' => 'vencida', 'lucia' => 'revocada', 'carlos' => 'futura', 'sin-rol' => 'inexistente'] as $usuario => $caso) {
        igual('MEMBRESIA_NO_VIGENTE', $e->autorizador->autorizar(pedirOperacion($usuario, 'expediente.leer'))->motivo, $caso);
    }
});

prueba('CA-12', 'Bitácora caída al denegar: sigue 403 y el evento va al respaldo (fail-closed)', function (): void {
    $e = entorno(auditoriaFalla: true);
    $d = $e->autorizador->autorizar(pedirOperacion('ana', 'prescripciones.crear'));
    igual(403, $d->codigoHttp, 'HTTP');
    igual('ACCESO_DENEGADO', $e->respaldo->buscarPorCorrelacion((string) $d->correlationId)?->tipo, 'evento en respaldo');

    $sinRespaldo = entorno(auditoriaFalla: true, respaldoFalla: true);
    igual(403, $sinRespaldo->autorizador->autorizar(pedirOperacion('ana', 'prescripciones.crear'))->codigoHttp, 'sin respaldo');
});

prueba('CA-13', 'Fail-closed: sin reglas o con una regla que lanza excepción, se deniega', function (): void {
    igual('SIN_REGLAS_CONFIGURADAS', entorno(reglas: [])->autorizador->autorizar(pedirOperacion('marta', 'expediente.leer'))->motivo, 'sin reglas');

    $reglaRota = new class () implements ReglaAutorizacion {
        public function evaluar(ContextoAutorizacion $contexto): ?string
        {
            throw new \LogicException('fallo inesperado');
        }
    };
    igual('ERROR_EVALUACION', entorno(reglas: [$reglaRota])->autorizador->autorizar(pedirOperacion('marta', 'expediente.leer'))->motivo, 'regla rota');
});

prueba('CA-14', 'El auditor encuentra la denegación con el correlation_id que vio el usuario', function (): void {
    $e = entorno();
    $d = $e->autorizador->autorizar(pedirOperacion('ana', 'resultados.registrar'));
    $ev = $e->auditoria->buscarPorCorrelacion((string) $d->correlationId);
    afirmar($ev !== null, 'evento no encontrado');
    igual('PERMISO_NO_ASIGNADO', $ev->datos['motivo'] ?? null, 'motivo registrado');
});

// ---------------------------------------------------------------------------
// Evidencia OCP
// ---------------------------------------------------------------------------

prueba('OCP-01', 'Regla nueva (red hospitalaria) funciona sin modificar el Autorizador', function (): void {
    $e = entorno();
    $fuera = $e->autorizador->autorizar(pedirOperacion('marta', 'prescripciones.crear', ip: IP_EXTERNA));
    igual('RED_NO_AUTORIZADA', $fuera->motivo, 'desde red externa');
    afirmar($e->autorizador->autorizar(pedirOperacion('marta', 'prescripciones.crear', ip: IP_INTERNA))->permitido, 'desde red interna');
    afirmar($e->autorizador->autorizar(pedirOperacion('marta', 'expediente.leer', ip: IP_EXTERNA))->permitido, 'permiso no sensible');
});

prueba('OCP-02', 'Autorizador y ServicioAsignacionRol no dependen de reglas concretas', function (): void {
    foreach (['Servicios/Autorizador.php', 'Servicios/ServicioAsignacionRol.php'] as $archivo) {
        $codigo = (string) file_get_contents(__DIR__ . '/despues/src/' . $archivo);
        afirmar(!str_contains($codigo, 'Rbac\\Reglas\\'), "$archivo importa reglas concretas");
        afirmar(!str_contains($codigo, 'Rbac\\Extension\\'), "$archivo importa extensiones");
        afirmar(preg_match('/new\s+Regla/', $codigo) === 0, "$archivo instancia reglas");
    }
    $tipo = (new \ReflectionClass(Autorizador::class))->getConstructor()?->getParameters()[0]->getType();
    igual('iterable', (string) $tipo, 'tipo del parámetro de reglas');
});

prueba('OCP-03', 'Quitar la extensión no exige cambios: con las 3 reglas base se permite desde fuera', function (): void {
    global $PERMISOS;
    $base = [new ReglaTenantCoincide(), new ReglaMembresiaVigente(), new ReglaRolConcedePermiso(new CatalogoPermisosEnMemoria($PERMISOS))];
    afirmar(entorno(reglas: $base)->autorizador->autorizar(pedirOperacion('marta', 'prescripciones.crear', ip: IP_EXTERNA))->permitido, 'debió permitirse sin la extensión');
});

// ---------------------------------------------------------------------------
// Paridad: el diseño DESPUÉS conserva el comportamiento del diseño ANTES
// ---------------------------------------------------------------------------

prueba('PAR-01', 'Antes y después producen la misma decisión en 7 escenarios', function (): void {
    global $PERMISOS, $AHORA;
    $mapa = [];
    foreach (membresiasIniciales() as $m) {
        $mapa[$m->usuarioId . '|' . $m->hospitalId][] = [
            'rol' => $m->rol, 'estado' => $m->estado,
            'desde' => $m->validoDesde->format(DATE_ATOM), 'hasta' => $m->validoHasta?->format(DATE_ATOM),
        ];
    }
    $bitacora = tempnam(sys_get_temp_dir(), 'rbac');
    $antes = new ServicioAutorizacionAntes($mapa, $PERMISOS, (string) $bitacora);
    $base = [new ReglaTenantCoincide(), new ReglaMembresiaVigente(), new ReglaRolConcedePermiso(new CatalogoPermisosEnMemoria($PERMISOS))];
    $despues = entorno(reglas: $base)->autorizador;

    $escenarios = [
        ['ana', 'signos_vitales.registrar', H_ORIENTE],
        ['ana', 'prescripciones.crear', H_ORIENTE],
        ['ana', 'signos_vitales.registrar', H_NORTE],
        ['pedro', 'expediente.leer', H_ORIENTE],
        ['lucia', 'expediente.leer', H_ORIENTE],
        ['carlos', 'expediente.leer', H_ORIENTE],
        ['marta', 'prescripciones.crear', H_ORIENTE],
    ];
    try {
        foreach ($escenarios as [$usuario, $permiso, $tenantSolicitud]) {
            $a = $antes->autorizar($usuario, H_ORIENTE, $tenantSolicitud, $permiso, 'recurso-demo', IP_INTERNA, $AHORA);
            $d = $despues->autorizar(pedirOperacion($usuario, $permiso, H_ORIENTE, $tenantSolicitud));
            igual($a['http'], $d->codigoHttp, "$usuario/$permiso HTTP");
            igual($a['motivo'] ?? null, $d->motivo, "$usuario/$permiso motivo");
        }
    } finally {
        @unlink((string) $bitacora);
    }
});

// ---------------------------------------------------------------------------
// Reporte
// ---------------------------------------------------------------------------

$fallidas = 0;
echo 'RBAC semana 2 - verificacion OCP (PHP ' . PHP_VERSION . ')' . PHP_EOL;
echo str_repeat('-', 78) . PHP_EOL;
foreach ($resultados as [$id, $descripcion, $error]) {
    echo sprintf('[%s] %-7s %s', $error === null ? 'PASA' : 'FALLA', $id, $descripcion) . PHP_EOL;
    if ($error !== null) {
        $fallidas++;
        echo '        -> ' . $error . PHP_EOL;
    }
}
echo str_repeat('-', 78) . PHP_EOL;
echo sprintf('Total: %d  Pasan: %d  Fallan: %d', count($resultados), count($resultados) - $fallidas, $fallidas) . PHP_EOL;

exit($fallidas === 0 ? 0 : 1);
