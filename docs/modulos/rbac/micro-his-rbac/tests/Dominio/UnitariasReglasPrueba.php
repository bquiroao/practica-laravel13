<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas UNITARIAS: cada regla de dominio aislada, sin casos de uso, HTTP ni persistencia real
 * (los puertos se resuelven con adaptadores en memoria construidos aquí). Trazadas a RF/RNF en
 * docs/modulos/rbac/semana-15/matriz-rf-rnf-pruebas.md.
 */

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Modelo\SolicitudAutorizacion;
use Rbac\Dominio\Modelo\SolicitudRevocacion;
use Rbac\Dominio\Reglas\Asignacion\ReglaNoAutoasignacion;
use Rbac\Dominio\Reglas\Asignacion\ReglaUsuarioDestinoActivo;
use Rbac\Dominio\Reglas\Asignacion\ReglaVigenciaValida;
use Rbac\Dominio\Reglas\Autorizacion\ReglaMembresiaVigente;
use Rbac\Dominio\Reglas\Autorizacion\ReglaRolConcedePermiso;
use Rbac\Dominio\Reglas\Autorizacion\ReglaTenantCoincide;
use Rbac\Dominio\Reglas\ReglaAutorizacion;
use Rbac\Dominio\Reglas\Revocacion\ReglaMotivoSuficiente;
use Rbac\Dominio\Reglas\Revocacion\ReglaNoAutorrevocacion;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;
use Rbac\Persistencia\EnMemoria\CatalogoPermisosEnMemoria;
use Rbac\Persistencia\EnMemoria\DirectorioUsuariosEnMemoria;

$ahora = new DateTimeImmutable('2026-11-15T10:00:00-06:00');
$m = static fn (string $rol, string $estado = 'active', string $desde = '2026-01-01T00:00:00-06:00', ?string $hasta = null): Membresia
    => new Membresia('ana', 'hosp-oriente', $rol, $estado, new DateTimeImmutable($desde), $hasta === null ? null : new DateTimeImmutable($hasta));
$solicitud = static fn (string $permiso, string $tenantToken = 'hosp-oriente', string $tenantSolicitud = 'hosp-oriente'): SolicitudAutorizacion
    => new SolicitudAutorizacion('ana', $tenantToken, $tenantSolicitud, $permiso, 'POST /api/v1/signos-vitales', '10.20.5.14', $ahora);
$asignacion = static fn (string $admin, string $destino, ?string $hasta = null, string $desde = '2026-11-15T00:00:00-06:00'): SolicitudAsignacion
    => new SolicitudAsignacion($admin, 'hosp-oriente', $destino, 'medico', new DateTimeImmutable($desde), $hasta === null ? null : new DateTimeImmutable($hasta), $ahora);
$revocacion = static fn (string $admin, string $destino, string $motivo): SolicitudRevocacion
    => new SolicitudRevocacion($admin, 'hosp-oriente', $destino, 'enfermeria', $motivo, $ahora);
$catalogo = new CatalogoPermisosEnMemoria([
    'medico' => ['prescripciones.crear', 'signos_vitales.registrar', 'expediente.leer'],
    'enfermeria' => ['signos_vitales.registrar', 'expediente.leer'],
    'laboratorio' => ['resultados.registrar'],
]);

prueba('UNI-01', 'ReglaTenantCoincide: mismo hospital → sin motivo; hospital distinto → TENANT_NO_COINCIDE', function () use ($solicitud): void {
    $regla = new ReglaTenantCoincide();
    igual(null, $regla->evaluar(new ContextoAutorizacion($solicitud('expediente.leer'), [])), 'mismo tenant');
    igual('TENANT_NO_COINCIDE', $regla->evaluar(new ContextoAutorizacion($solicitud('expediente.leer', 'hosp-norte'), [])), 'tenant distinto');
});

prueba('UNI-02', 'Vigencia (valores límite): desde = ahora vigente; hasta = ahora NO vigente; hasta = ahora + 1 s vigente; revoked/inactive no', function () use ($m, $ahora): void {
    $regla = new ReglaMembresiaVigente();
    $vigente = static fn (Membresia $x): bool => $x->estaVigenteEn($ahora);
    afirmar($vigente($m('enfermeria', 'active', '2026-11-15T10:00:00-06:00')), 'desde = ahora (límite inferior inclusivo)');
    afirmar(!$vigente($m('enfermeria', 'active', '2026-11-15T10:00:01-06:00')), 'desde = ahora + 1 s (aún no vigente)');
    afirmar(!$vigente($m('enfermeria', 'active', '2026-01-01T00:00:00-06:00', '2026-11-15T10:00:00-06:00')), 'hasta = ahora (límite superior exclusivo)');
    afirmar($vigente($m('enfermeria', 'active', '2026-01-01T00:00:00-06:00', '2026-11-15T10:00:01-06:00')), 'hasta = ahora + 1 s');
    afirmar(!$vigente($m('enfermeria', 'revoked')), 'estado revoked');
    afirmar(!$vigente($m('enfermeria', 'inactive')), 'estado inactive');
    igual('MEMBRESIA_NO_VIGENTE', $regla->evaluar(new ContextoAutorizacion(
        new SolicitudAutorizacion('ana', 'hosp-oriente', 'hosp-oriente', 'expediente.leer', 'r', '', $ahora), [$m('enfermeria', 'revoked')])), 'regla con solo una revocada');
});

prueba('UNI-03', 'ReglaRolConcedePermiso: concede si ALGUNA membresía vigente incluye el permiso; si no, PERMISO_NO_ASIGNADO', function () use ($m, $solicitud, $catalogo): void {
    $regla = new ReglaRolConcedePermiso($catalogo);
    igual(null, $regla->evaluar(new ContextoAutorizacion($solicitud('signos_vitales.registrar'), [$m('enfermeria')])), 'enfermería registra signos');
    igual('PERMISO_NO_ASIGNADO', $regla->evaluar(new ContextoAutorizacion($solicitud('prescripciones.crear'), [$m('enfermeria')])), 'enfermería no prescribe');
    igual(null, $regla->evaluar(new ContextoAutorizacion($solicitud('prescripciones.crear'), [$m('laboratorio'), $m('medico')])), 'la segunda membresía concede');
    igual('PERMISO_NO_ASIGNADO', $regla->evaluar(new ContextoAutorizacion($solicitud('prescripciones.crear'), [$m('medico', 'active', '2026-01-01', '2026-06-30')])), 'médico vencido no cuenta');
});

prueba('UNI-04', 'ReglaVigenciaValida (límites): hasta = desde → VIGENCIA_INVALIDA; hasta = desde + 1 s y hasta nulo → válidas', function () use ($asignacion): void {
    $regla = new ReglaVigenciaValida();
    igual('VIGENCIA_INVALIDA', $regla->evaluar($asignacion('admin-01', 'nuevo-01', '2026-11-15T00:00:00-06:00'))?->codigo, 'hasta = desde');
    igual(422, $regla->evaluar($asignacion('admin-01', 'nuevo-01', '2026-11-14T00:00:00-06:00'))?->codigoHttp, 'hasta < desde → 422');
    igual(null, $regla->evaluar($asignacion('admin-01', 'nuevo-01', '2026-11-15T00:00:01-06:00')), 'hasta = desde + 1 s');
    igual(null, $regla->evaluar($asignacion('admin-01', 'nuevo-01')), 'sin fecha de fin');
});

prueba('UNI-05', 'ReglaMotivoSuficiente (límites): 9 → insuficiente, 10 → suficiente, se recortan espacios y cuenta caracteres, no bytes', function () use ($revocacion): void {
    $regla = new ReglaMotivoSuficiente();
    igual('MOTIVO_INSUFICIENTE', $regla->evaluar($revocacion('admin-01', 'ana', str_repeat('a', 9)))?->codigo, '9 caracteres');
    igual(null, $regla->evaluar($revocacion('admin-01', 'ana', str_repeat('a', 10))), '10 caracteres');
    igual('MOTIVO_INSUFICIENTE', $regla->evaluar($revocacion('admin-01', 'ana', '   ' . str_repeat('a', 9) . '   '))?->codigo, '9 + espacios alrededor');
    igual('MOTIVO_INSUFICIENTE', $regla->evaluar($revocacion('admin-01', 'ana', str_repeat('ñ', 9)))?->codigo, '9 «ñ» = 18 bytes, siguen siendo 9 caracteres');
    igual(null, $regla->evaluar($revocacion('admin-01', 'ana', str_repeat('ñ', 10))), '10 «ñ»');
});

prueba('UNI-06', 'Autoasignación y autorrevocación prohibidas (R-04); otra persona sí', function () use ($asignacion, $revocacion): void {
    igual('AUTOASIGNACION', (new ReglaNoAutoasignacion())->evaluar($asignacion('admin-01', 'admin-01'))?->codigo, 'autoasignación');
    igual(null, (new ReglaNoAutoasignacion())->evaluar($asignacion('admin-01', 'nuevo-01')), 'asignar a otro');
    igual('AUTORREVOCACION', (new ReglaNoAutorrevocacion())->evaluar($revocacion('admin-01', 'admin-01', 'Motivo suficiente'))?->codigo, 'autorrevocación');
    igual(null, (new ReglaNoAutorrevocacion())->evaluar($revocacion('admin-01', 'ana', 'Motivo suficiente')), 'revocar a otro');
});

prueba('UNI-07', 'ReglaUsuarioDestinoActivo: inexistente → USUARIO_NO_ENCONTRADO 404; inactivo → USUARIO_INACTIVO 422; activo → sin error', function () use ($asignacion): void {
    $regla = new ReglaUsuarioDestinoActivo(new DirectorioUsuariosEnMemoria(['nuevo-01' => 'active', 'jorge' => 'inactive']));
    $inexistente = $regla->evaluar($asignacion('admin-01', 'nadie'));
    igual(['USUARIO_NO_ENCONTRADO', 404], [$inexistente?->codigo, $inexistente?->codigoHttp], 'inexistente');
    $inactivo = $regla->evaluar($asignacion('admin-01', 'jorge'));
    igual(['USUARIO_INACTIVO', 422], [$inactivo?->codigo, $inactivo?->codigoHttp], 'inactivo');
    igual(null, $regla->evaluar($asignacion('admin-01', 'nuevo-01')), 'activo');
});

prueba('UNI-08', 'EventoAuditoria::accesoDenegado lleva los 8 datos de R-06 y el hospital de la SOLICITUD', function () use ($solicitud): void {
    $e = EventoAuditoria::accesoDenegado($solicitud('roles.asignar', 'hosp-norte', 'hosp-oriente'), 'TENANT_NO_COINCIDE', 'corr-1');
    igual(['ACCESO_DENEGADO', 'corr-1', 'ana', 'hosp-oriente'], [$e->tipo, $e->correlationId, $e->actorId, $e->hospitalId], 'cabecera del evento');
    igual(['permiso' => 'roles.asignar', 'recurso' => 'POST /api/v1/signos-vitales', 'motivo' => 'TENANT_NO_COINCIDE', 'ip' => '10.20.5.14'], $e->datos, 'datos');
    igual('2026-11-15T10:00:00-06:00', $e->ocurridoEn->format(DATE_ATOM), 'fecha');
});

prueba('UNI-09', 'PoliticaAutorizacion: devuelve el motivo de la PRIMERA regla que falla y no evalúa las siguientes (corto circuito)', function () use ($solicitud): void {
    $llamadas = new ArrayObject();
    $regla = static fn (string $nombre, ?string $motivo): ReglaAutorizacion => new class ($nombre, $motivo, $llamadas) implements ReglaAutorizacion {
        public function __construct(private string $nombre, private ?string $motivo, private ArrayObject $llamadas)
        {
        }

        public function evaluar(ContextoAutorizacion $contexto): ?string
        {
            $this->llamadas[] = $this->nombre;
            return $this->motivo;
        }
    };
    $politica = new PoliticaAutorizacion([$regla('A', null), $regla('B', 'MOTIVO_B'), $regla('C', 'MOTIVO_C')]);
    igual('MOTIVO_B', $politica->evaluar(new ContextoAutorizacion($solicitud('x.y'), [])), 'motivo devuelto');
    igual(['A', 'B'], $llamadas->getArrayCopy(), 'reglas evaluadas');
});
