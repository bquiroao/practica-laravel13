<?php

declare(strict_types=1);

// MVC: cada pieza se prueba por separado — formulario, vista y controlador sin Kernel.

use Rbac\Aplicacion\Resultados\ResultadoAsignacion;
use Rbac\Presentacion\Http\Controladores\AsignacionRolControlador;
use Rbac\Presentacion\Http\Formularios\DatosInvalidos;
use Rbac\Presentacion\Http\Formularios\FormularioAsignacion;
use Rbac\Presentacion\Http\Identidad;
use Rbac\Presentacion\Http\Peticion;
use Rbac\Presentacion\Http\Vistas\AsignacionVista;
use Rbac\Pruebas\Dobles\Escenario;

$peticion = static fn (array $cuerpo, string $usuario = 'admin-01'): Peticion => new Peticion(
    Escenario::http('POST', '/api/v1/roles/asignaciones', 'x', cuerpo: $cuerpo),
    new Identidad($usuario, Escenario::H_ORIENTE),
    [],
);

prueba('MVC-01', 'Formulario: convierte el JSON en comando (actor = identidad del token) o informa los campos inválidos', function () use ($peticion): void {
    $c = FormularioAsignacion::aComando($peticion(['usuario_id' => ' nuevo-01 ', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15']));
    igual('admin-01', $c->adminId, 'el actor sale del token, no del cuerpo');
    igual('nuevo-01', $c->usuarioDestinoId, 'usuario (recortado)');
    igual(Escenario::H_ORIENTE, $c->hospitalId, 'hospital desde X-Tenant-ID');
    igual(null, $c->validoHasta, 'sin fecha de fin');
    $e = lanza(DatosInvalidos::class, fn () => FormularioAsignacion::aComando($peticion(['rol' => '', 'valido_desde' => 'ayer?', 'valido_hasta' => 'x'])), 'inválido');
    igual(['usuario_id', 'rol', 'valido_desde', 'valido_hasta'], $e->campos, 'campos');
    // El formulario NO aplica reglas de negocio: un rol inexistente pasa el formato.
    igual('superusuario', FormularioAsignacion::aComando($peticion(['usuario_id' => 'u', 'rol' => 'superusuario', 'valido_desde' => '2026-11-15']))->rol, 'regla de negocio fuera');
});

prueba('MVC-02', 'Vista: cada resultado del caso de uso tiene un formato fijo; el 403 no revela el código interno', function (): void {
    $creado = AsignacionVista::resultado(ResultadoAsignacion::creado('c-1'));
    igual([201, ['codigo' => 'ROL_ASIGNADO', 'correlation_id' => 'c-1']], [$creado->estado, $creado->cuerpo], '201');
    $denegado = AsignacionVista::resultado(ResultadoAsignacion::denegado('c-2'));
    igual([403, ['mensaje' => 'Acceso denegado.', 'correlation_id' => 'c-2']], [$denegado->estado, $denegado->cuerpo], '403');
    $duplicado = AsignacionVista::resultado(ResultadoAsignacion::rechazado('ASIGNACION_DUPLICADA', 409));
    igual([409, ['codigo' => 'ASIGNACION_DUPLICADA']], [$duplicado->estado, $duplicado->cuerpo], '409');
    igual(500, AsignacionVista::resultado(ResultadoAsignacion::errorPersistencia())->estado, '500');
});

prueba('MVC-03', 'Controlador sin Kernel: delega en el caso de uso y en la vista (201 / 422 / 409)', function () use ($peticion): void {
    $controlador = new AsignacionRolControlador((new Escenario())->asignarRol);
    igual(201, $controlador->crear($peticion(['usuario_id' => 'nuevo-01', 'rol' => 'laboratorio', 'valido_desde' => '2026-11-15T00:00:00-06:00']))->estado, 'creado');
    igual(422, $controlador->crear($peticion(['rol' => 'laboratorio']))->estado, 'formato inválido');
    igual(409, $controlador->crear($peticion(['usuario_id' => 'ana', 'rol' => 'enfermeria', 'valido_desde' => '2026-11-15']))->estado, 'duplicado (regla del dominio)');
});
