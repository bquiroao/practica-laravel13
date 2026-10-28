<?php

declare(strict_types=1);

use Pacientes\Aplicacion\Comandos\ComandoBuscar;
use Pacientes\Aplicacion\Comandos\ComandoConsultar;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Pruebas\Dobles\Escenario;

$buscar = static fn (object $e, array $criterios, string $actor = Escenario::CLINICO, string $hospital = Escenario::H_ORIENTE): Resultado =>
    $e->casos['buscar']->ejecutar(new ComandoBuscar($actor, $hospital, $criterios));
$consultar = static fn (object $e, string $codigo, string $actor = Escenario::CLINICO, string $hospital = Escenario::H_ORIENTE): Resultado =>
    $e->casos['consultar']->ejecutar(new ComandoConsultar($actor, $hospital, $codigo));

prueba('APP-01', 'Camino feliz: registrar → buscar → consultar → actualizar, con un evento de auditoría por paso', function () use ($buscar, $consultar): void {
    $e = Escenario::crear();

    $r = Escenario::registrar($e, Escenario::datosAna());
    igual([Resultado::CREADO, 'P-000001', 1], [$r->estado, $r->datos['codigo'], $r->datos['version']], 'registro');

    $b = $buscar($e, ['nombre' => 'lopez', 'nacimiento' => '1985']);
    igual(1, $b->datos['total'], 'búsqueda');
    igual('*********0101', $b->datos['resultados'][0]['identificador'], 'documento enmascarado');

    $c = $consultar($e, 'p-000001');
    igual(1, $c->datos['paciente']['version'], 'detalle con versión');

    $a = Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['telefono' => '+502 4444 0001']));
    igual([Resultado::OK, 2], [$a->estado, $a->datos['version']], 'actualización');

    igual([EventoAuditoria::REGISTRADO, EventoAuditoria::BUSCADO, EventoAuditoria::CONSULTADO, EventoAuditoria::ACTUALIZADO],
        array_map(static fn (EventoAuditoria $ev): string => $ev->accion, $e->bitacora->eventos()), 'eventos');
    $actualizado = $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)[0];
    igual(['+502 5555 1234', '+502 4444 0001'], [$actualizado->detalle['antes']['telefono'], $actualizado->detalle['despues']['telefono']], 'antes/después');
    afirmar(!str_contains((string) json_encode(array_map(static fn ($ev) => $ev->detalle, $e->bitacora->eventos())), Escenario::DPI_ANA),
        'el DPI completo aparece en la auditoría');
});

prueba('APP-02', 'Regla de dominio al registrar: el mismo DPI → conflicto PACIENTE_EXISTENTE, sin guardar ni auditar', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    $r = Escenario::registrar($e, Escenario::datosAna(['nombres' => 'Ana', 'numero_documento' => '2547 89632 0101']));
    igual(['conflicto', 'PACIENTE_EXISTENTE', 'P-000001'], [$r->estado, $r->error, $r->datos['codigo']], 'resultado');
    igual(1, count($e->repositorio->todos()), 'pacientes');
    igual(1, count($e->bitacora->eventos()), 'eventos');
});

prueba('APP-03', 'Regla de dominio al actualizar: versión desactualizada → conflicto y el cambio anterior se conserva', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['telefono' => '+502 4444 0001']));
    $r = Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['telefono' => '+502 3333 0002']));
    igual(['conflicto', 'VERSION_DESACTUALIZADA', 2], [$r->estado, $r->error, $r->datos['version_vigente']], 'resultado');
    igual('+502 4444 0001', $e->repositorio->porCodigo(Escenario::H_ORIENTE, 'P-000001')?->telefono, 'teléfono guardado');
    igual(1, count($e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)), 'actualizaciones auditadas');
});

prueba('APP-04', 'Reglas de dominio en la edición: motivo, DPI de otro paciente y paciente fallecido', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    Escenario::registrar($e, Escenario::datosLuis());
    igual('MOTIVO_REQUERIDO', Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['nombres' => 'Ana María']))->error, 'sin motivo');
    igual('IDENTIFICADOR_EN_USO', Escenario::actualizar($e, 'P-000001', 1,
        Escenario::datosAna(['numero_documento' => Escenario::DPI_LUIS]), 'Corrección')->error, 'DPI de Luis');
    $e->repositorio->actualizar($e->repositorio->porCodigo(Escenario::H_ORIENTE, 'P-000002')->conEstado('deceased'), 1);
    igual('REQUIERE_SUPERVISOR', Escenario::actualizar($e, 'P-000002', 2, Escenario::datosLuis())->error, 'fallecido');
    igual(Resultado::OK, Escenario::actualizar($e, 'P-000002', 2, Escenario::datosLuis(), null, Escenario::SUPERVISOR)->estado, 'supervisor');
});

prueba('APP-05', 'Acceso (R-05): el clínico busca y consulta, pero no registra ni edita; otro hospital da no_encontrado', function () use ($buscar, $consultar): void {
    $e = Escenario::crear();
    igual('ACCESO_DENEGADO', Escenario::registrar($e, Escenario::datosAna(), Escenario::CLINICO)->error, 'registrar');
    Escenario::registrar($e, Escenario::datosAna());
    igual(Resultado::OK, $buscar($e, ['codigo' => 'P-000001'])->estado, 'buscar');
    igual(Resultado::OK, $consultar($e, 'P-000001')->estado, 'consultar');
    igual('ACCESO_DENEGADO', Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(), null, Escenario::CLINICO)->error, 'editar');
    igual('PACIENTE_NO_ENCONTRADO', $consultar($e, 'P-000001', Escenario::ADMISION_NORTE, Escenario::H_NORTE)->error, 'otro hospital');
});

prueba('APP-06', 'Error de persistencia al guardar: no_disponible, sin paciente ni evento', function (): void {
    $e = Escenario::crear();
    $e->repositorio->fallarEn = ['registrar'];
    $r = Escenario::registrar($e, Escenario::datosAna());
    igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE', []], [$r->estado, $r->error, $r->datos], 'resultado');
    igual([], $e->repositorio->todos(), 'pacientes');
    igual([], $e->bitacora->eventos(), 'eventos');
});

prueba('APP-07', 'Error de persistencia en la bitácora: la transacción revierte el registro y la actualización', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosLuis());
    $e->bitacora->fallar = true;

    igual(Resultado::NO_DISPONIBLE, Escenario::registrar($e, Escenario::datosAna())->estado, 'registro');
    igual(1, count($e->repositorio->todos()), 'Ana no quedó guardada');

    $r = Escenario::actualizar($e, 'P-000001', 1, Escenario::datosLuis() + ['telefono' => '+502 4444 0001']);
    igual(Resultado::NO_DISPONIBLE, $r->estado, 'actualización');
    $luis = $e->repositorio->porCodigo(Escenario::H_ORIENTE, 'P-000001');
    igual([1, null], [$luis?->version, $luis?->telefono], 'versión y teléfono sin cambios');
});

prueba('APP-08', 'Error de persistencia al leer: búsqueda y detalle responden no_disponible', function () use ($buscar, $consultar): void {
    $e = Escenario::crear();
    $e->repositorio->fallarEn = ['buscar', 'porCodigo'];
    igual(Resultado::NO_DISPONIBLE, $buscar($e, ['nombre' => 'lopez'])->estado, 'búsqueda');
    igual(Resultado::NO_DISPONIBLE, $consultar($e, 'P-000001')->estado, 'detalle');
    igual([], $e->bitacora->eventos(), 'no se audita lo que no ocurrió');
});
