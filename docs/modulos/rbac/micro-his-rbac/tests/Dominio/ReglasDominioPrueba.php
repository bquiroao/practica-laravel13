<?php

declare(strict_types=1);

// Reglas de dominio: se prueban sin base de datos, sin HTTP y sin casos de uso.

use Rbac\Dominio\Modelo\ContextoAutorizacion;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Dominio\Modelo\SolicitudAsignacion;
use Rbac\Dominio\Modelo\SolicitudAutorizacion;
use Rbac\Dominio\Reglas\Asignacion\ReglaNoAutoasignacion;
use Rbac\Dominio\Reglas\Asignacion\ReglaRolEnCatalogo;
use Rbac\Dominio\Reglas\Asignacion\ReglaUsuarioDestinoActivo;
use Rbac\Dominio\Reglas\Asignacion\ReglaVigenciaValida;
use Rbac\Dominio\Reglas\Autorizacion\ReglaMembresiaVigente;
use Rbac\Dominio\Reglas\Autorizacion\ReglaRolConcedePermiso;
use Rbac\Dominio\Reglas\Autorizacion\ReglaTenantCoincide;
use Rbac\Dominio\Reglas\ReglaAutorizacion;
use Rbac\Dominio\Servicios\PoliticaAutorizacion;
use Rbac\Persistencia\EnMemoria\CatalogoPermisosEnMemoria;
use Rbac\Persistencia\EnMemoria\DirectorioUsuariosEnMemoria;

$ahora = new DateTimeImmutable('2026-11-15T10:00:00-06:00');
$catalogo = new CatalogoPermisosEnMemoria(['enfermeria' => ['signos_vitales.registrar'], 'medico' => ['prescripciones.crear']]);

$contexto = static function (string $permiso, array $membresias, string $tenantToken = 'h1') use ($ahora): ContextoAutorizacion {
    return new ContextoAutorizacion(new SolicitudAutorizacion('ana', $tenantToken, 'h1', $permiso, 'r', '10.0.0.1', $ahora), $membresias);
};
$membresia = static fn (string $rol, string $estado = 'active', string $desde = '2026-01-01', ?string $hasta = null): Membresia =>
    new Membresia('ana', 'h1', $rol, $estado, new DateTimeImmutable($desde), $hasta === null ? null : new DateTimeImmutable($hasta));
$asignacion = static fn (string $admin, string $destino, string $rol, string $desde = '2026-11-15', ?string $hasta = null): SolicitudAsignacion =>
    new SolicitudAsignacion($admin, 'h1', $destino, $rol, new DateTimeImmutable($desde), $hasta === null ? null : new DateTimeImmutable($hasta), $ahora);

prueba('DOM-01', 'Membresía vigente solo si está activa y dentro de [desde, hasta)', function () use ($membresia, $ahora): void {
    afirmar($membresia('enfermeria')->estaVigenteEn($ahora), 'activa sin fin debe estar vigente');
    afirmar(!$membresia('enfermeria', 'revoked')->estaVigenteEn($ahora), 'revocada');
    afirmar(!$membresia('enfermeria', 'inactive')->estaVigenteEn($ahora), 'inactiva');
    afirmar(!$membresia('enfermeria', 'active', '2026-01-01', '2026-06-30')->estaVigenteEn($ahora), 'vencida');
    afirmar(!$membresia('enfermeria', 'active', '2027-01-01')->estaVigenteEn($ahora), 'futura');
    afirmar(!$membresia('enfermeria', 'active', '2026-01-01', '2026-11-15T10:00:00-06:00')->estaVigenteEn($ahora), 'el fin es exclusivo');
});

prueba('DOM-02', 'Política de autorización: la primera regla que falla define el motivo', function () use ($catalogo, $contexto, $membresia): void {
    $politica = new PoliticaAutorizacion([new ReglaTenantCoincide(), new ReglaMembresiaVigente(), new ReglaRolConcedePermiso($catalogo)]);
    igual(null, $politica->evaluar($contexto('signos_vitales.registrar', [$membresia('enfermeria')])), 'permitido');
    igual('TENANT_NO_COINCIDE', $politica->evaluar($contexto('signos_vitales.registrar', [$membresia('enfermeria')], 'h2')), 'tenant');
    igual('MEMBRESIA_NO_VIGENTE', $politica->evaluar($contexto('signos_vitales.registrar', [$membresia('enfermeria', 'revoked')])), 'vigencia');
    igual('PERMISO_NO_ASIGNADO', $politica->evaluar($contexto('prescripciones.crear', [$membresia('enfermeria')])), 'permiso');
    igual(null, $politica->evaluar($contexto('prescripciones.crear', [$membresia('enfermeria'), $membresia('medico')])), 'varios roles');
});

prueba('DOM-03', 'Fail-closed: sin reglas o con una regla que lanza excepción se deniega', function () use ($contexto, $membresia): void {
    igual('SIN_REGLAS_CONFIGURADAS', (new PoliticaAutorizacion([]))->evaluar($contexto('x', [$membresia('enfermeria')])), 'sin reglas');
    $rota = new class () implements ReglaAutorizacion {
        public function evaluar(ContextoAutorizacion $contexto): ?string
        {
            throw new LogicException('fallo inesperado');
        }
    };
    igual('ERROR_EVALUACION', (new PoliticaAutorizacion([$rota]))->evaluar($contexto('x', [$membresia('enfermeria')])), 'regla rota');
});

prueba('DOM-04', 'Reglas de asignación: autoasignación, vigencia, rol y usuario destino', function () use ($catalogo, $asignacion): void {
    igual('AUTOASIGNACION', (new ReglaNoAutoasignacion())->evaluar($asignacion('a1', 'a1', 'medico'))?->codigo, 'autoasignación');
    igual(null, (new ReglaNoAutoasignacion())->evaluar($asignacion('a1', 'u1', 'medico')), 'otro usuario');
    igual('VIGENCIA_INVALIDA', (new ReglaVigenciaValida())->evaluar($asignacion('a1', 'u1', 'medico', '2026-11-15', '2026-11-01'))?->codigo, 'fin antes del inicio');
    igual('ROL_INVALIDO', (new ReglaRolEnCatalogo($catalogo))->evaluar($asignacion('a1', 'u1', 'superusuario'))?->codigo, 'rol inexistente');
    $usuarios = new DirectorioUsuariosEnMemoria(['u1' => 'active', 'u2' => 'inactive']);
    igual(404, (new ReglaUsuarioDestinoActivo($usuarios))->evaluar($asignacion('a1', 'nadie', 'medico'))?->codigoHttp, 'inexistente');
    igual(422, (new ReglaUsuarioDestinoActivo($usuarios))->evaluar($asignacion('a1', 'u2', 'medico'))?->codigoHttp, 'inactivo');
    igual(null, (new ReglaUsuarioDestinoActivo($usuarios))->evaluar($asignacion('a1', 'u1', 'medico')), 'activo');
});
