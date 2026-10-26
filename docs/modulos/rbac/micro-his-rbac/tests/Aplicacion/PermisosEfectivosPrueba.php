<?php

declare(strict_types=1);

/*
 * Cambio solicitado de la semana 12 (brecha B-6): «la interfaz llena la auditoría de denegaciones
 * que nadie intentó». Escrito ANTES de implementar (rojo → verde).
 *
 * Regla del cambio: las consultas PASIVAS (qué mostrar) usan permisos efectivos y no dejan eventos;
 * los intentos ACTIVOS (ejecutar una operación, entrar a una ruta protegida) siguen auditados.
 */

use Rbac\Pruebas\Dobles\Escenario;

const PERMISOS_EFECTIVOS = '/api/v1/autorizaciones/permisos-efectivos';
const PERMISOS_UI = ['expediente.leer', 'signos_vitales.registrar', 'prescripciones.crear', 'roles.asignar', 'roles.revocar', 'auditoria.consultar'];

function consultarEfectivos(Escenario $e, string $usuario, string $tenantSolicitud = Escenario::H_ORIENTE): array
{
    return $e->caso('permisosEfectivos')->ejecutar($usuario, Escenario::H_ORIENTE, $tenantSolicitud, PERMISOS_UI);
}

prueba('PEF-01', 'Devuelve, para cada permiso pedido, si el usuario de la sesión lo tiene en el hospital', function (): void {
    $r = consultarEfectivos(new Escenario(), 'ana');
    igual([
        'expediente.leer' => true, 'signos_vitales.registrar' => true, 'prescripciones.crear' => false,
        'roles.asignar' => false, 'roles.revocar' => false, 'auditoria.consultar' => false,
    ], $r, 'permisos de Ana (enfermería)');
});

prueba('PEF-02', 'Consultar permisos efectivos NO registra eventos (aunque la mayoría sean «no»)', function (): void {
    $e = new Escenario();
    $antes = count($e->auditoria->eventos());
    foreach (['admin-01', 'ana', 'marta', 'pedro', 'lucia', 'nuevo-01'] as $u) {
        consultarEfectivos($e, $u);
    }
    igual($antes, count($e->auditoria->eventos()), 'eventos en la bitácora');
    igual(0, count($e->respaldo->eventos()), 'eventos en el respaldo');
});

prueba('PEF-03', 'Concordancia: para cada usuario y permiso, la respuesta coincide con la decisión del PDP', function (): void {
    $e = new Escenario();
    $diferencias = [];
    foreach (['admin-01', 'ana', 'marta', 'pedro', 'lucia', 'nuevo-01', 'jorge'] as $u) {
        $efectivos = consultarEfectivos($e, $u);
        foreach (PERMISOS_UI as $p) {
            $pdp = $e->autorizar->ejecutar(Escenario::comandoAutorizar($u, $p))->permitido;
            if ($efectivos[$p] !== $pdp) {
                $diferencias[] = "$u/$p";
            }
        }
    }
    igual([], $diferencias, 'pares usuario/permiso que difieren');
});

prueba('PEF-04', 'Tras revocar el rol, la consulta cambia de inmediato (sin caché en el servidor)', function (): void {
    $e = new Escenario();
    afirmar(consultarEfectivos($e, 'ana')['signos_vitales.registrar'], 'antes de revocar');
    $e->caso('revocarRol')->ejecutar(Escenario::comandoRevocar('admin-01', 'ana', 'enfermeria'));
    igual([], array_keys(array_filter(consultarEfectivos($e, 'ana'))), 'permisos después de revocar');
});

prueba('PEF-05', 'Hospital distinto al de la sesión: todo en «no» (aislamiento por tenant)', function (): void {
    igual([], array_keys(array_filter(consultarEfectivos(new Escenario(), 'admin-01', Escenario::H_NORTE))), 'permisos con tenant distinto');
});

prueba('PEF-06', 'Fail-closed: si no se pueden leer las membresías, todo en «no» y sin excepción', function (): void {
    $e = new Escenario();
    $e->membresias->fallarLectura = true;
    igual([], array_keys(array_filter(consultarEfectivos($e, 'admin-01'))), 'permisos con la lectura caída');
});

prueba('PEF-07', 'HTTP: 200 con el mapa de permisos; sin evento de auditoría', function (): void {
    $e = new Escenario();
    $antes = count($e->auditoria->eventos());
    $r = $e->kernel->manejar(Escenario::http('POST', PERMISOS_EFECTIVOS, 'token-ana', Escenario::H_ORIENTE, ['permisos' => ['signos_vitales.registrar', 'roles.asignar']]));
    igual(200, $r->estado, 'HTTP');
    igual(['permisos' => ['signos_vitales.registrar' => true, 'roles.asignar' => false]], $r->cuerpo, 'cuerpo');
    igual($antes, count($e->auditoria->eventos()), 'eventos');
});

prueba('PEF-08', 'HTTP: formato inválido → 422 (lista vacía, permiso mal escrito o más de 20)', function (): void {
    $e = new Escenario();
    $pedir = static fn (mixed $permisos) => $e->kernel->manejar(Escenario::http('POST', PERMISOS_EFECTIVOS, 'token-ana', Escenario::H_ORIENTE, ['permisos' => $permisos]));
    $veintiuno = array_map(static fn (int $i): string => "modulo.accion_$i", range(1, 21));
    foreach (['vacía' => [], 'mal escrito' => ['X'], 'no es lista' => 'roles.asignar', 'más de 20' => $veintiuno] as $caso => $permisos) {
        $r = $pedir($permisos);
        igual(422, $r->estado, "HTTP ($caso)");
        igual(['codigo' => 'DATOS_INVALIDOS', 'campos' => ['permisos']], $r->cuerpo, "cuerpo ($caso)");
    }
});

prueba('PEF-09', 'HTTP: sin hospital 400 y sin token 401 (mismo orden que el resto del kernel)', function (): void {
    $e = new Escenario();
    igual(400, $e->kernel->manejar(Escenario::http('POST', PERMISOS_EFECTIVOS, 'token-ana', '', ['permisos' => ['expediente.leer']]))->estado, 'sin tenant');
    igual(401, $e->kernel->manejar(Escenario::http('POST', PERMISOS_EFECTIVOS, 'token-falso', Escenario::H_ORIENTE, ['permisos' => ['expediente.leer']]))->estado, 'sin token válido');
});

prueba('PEF-10', 'Los intentos ACTIVOS siguen auditados: una operación denegada deja ACCESO_DENEGADO', function (): void {
    $e = new Escenario();
    $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/prescripciones', 'token-ana'));
    igual(403, $r->estado, 'HTTP');
    igual('PERMISO_NO_ASIGNADO', $e->auditoria->buscarPorCorrelacion((string) $r->cuerpo['correlation_id'])?->datos['motivo'] ?? null, 'evento');
});
