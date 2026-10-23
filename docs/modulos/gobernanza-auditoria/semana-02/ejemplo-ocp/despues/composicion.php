<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas.
 * Para extender el comportamiento se agrega una línea aquí; ServicioRegistro y
 * ServicioConsulta no se tocan.
 */

use Auditoria\Contratos\FiltroMovimiento;
use Auditoria\Contratos\ReglaMovimiento;
use Auditoria\Contratos\Reloj;
use Auditoria\Dominio\CatalogoAcciones;
use Auditoria\Extension\FiltroResultado;
use Auditoria\Extension\ReglaModuloDelEmisor;
use Auditoria\Filtros\FiltroAccion;
use Auditoria\Filtros\FiltroModulo;
use Auditoria\Filtros\FiltroRangoFechas;
use Auditoria\Filtros\FiltroUsuario;
use Auditoria\Reglas\ReglaAccionEnCatalogo;
use Auditoria\Reglas\ReglaCamposObligatorios;
use Auditoria\Reglas\ReglaFechaNoFutura;
use Auditoria\Reglas\ReglaResultadoValido;
use Auditoria\Reglas\ReglaSinDatosProhibidos;
use Auditoria\Reglas\ReglaTenantDelEmisor;

function catalogoDeAcciones(): CatalogoAcciones
{
    return new CatalogoAcciones([
        'pacientes' => ['PACIENTE_REGISTRADO', 'PACIENTE_ACTUALIZADO', 'PACIENTE_CONSULTADO', 'PACIENTE_BUSCADO'],
        'rbac' => ['ROL_ASIGNADO', 'ROL_REVOCADO', 'ACCESO_DENEGADO'],
        'citas' => ['CITA_CREADA', 'CITA_CANCELADA'],
        'auditoria' => ['AUDITORIA_CONSULTADA'],
    ]);
}

/** @return list<ReglaMovimiento> el ORDEN importa: el tenant va primero */
function reglasDeRegistro(Reloj $reloj, bool $conExtensiones = true): array
{
    $reglas = [
        new ReglaTenantDelEmisor(),
        new ReglaCamposObligatorios(),
        new ReglaAccionEnCatalogo(catalogoDeAcciones()),
        new ReglaResultadoValido(),
        new ReglaFechaNoFutura($reloj, 5),
        new ReglaSinDatosProhibidos(),
    ];
    if ($conExtensiones) {
        // Extensión OCP: requerimiento nuevo agregado sin modificar ninguna clase existente.
        $reglas[] = new ReglaModuloDelEmisor(['svc-pacientes' => 'pacientes', 'svc-rbac' => 'rbac', 'svc-citas' => 'citas']);
    }

    return $reglas;
}

/** @return list<FiltroMovimiento> */
function filtrosDeConsulta(bool $conExtensiones = true): array
{
    $filtros = [
        new FiltroRangoFechas('America/Guatemala'),
        new FiltroUsuario(),
        new FiltroModulo(),
        new FiltroAccion(),
    ];
    if ($conExtensiones) {
        // Extensión OCP: filtro nuevo agregado sin modificar ServicioConsulta.
        $filtros[] = new FiltroResultado();
    }

    return $filtros;
}
