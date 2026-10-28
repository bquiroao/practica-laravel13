<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que elige implementaciones concretas.
 * Cambiar de repositorio principal a réplica o a caché es cambiar UNA línea aquí;
 * los servicios no se enteran porque solo conocen los contratos.
 */

use Pacientes\Adaptadores\ReplicaLecturaPacientes;
use Pacientes\Adaptadores\RepositorioPacientesConCache;
use Pacientes\EnMemoria\IndiceMaestroEnMemoria;
use Pacientes\EnMemoria\PoliticaAccesoEnMemoria;
use Pacientes\EnMemoria\RegistroAuditoriaEnMemoria;
use Pacientes\EnMemoria\RegistroAuditoriaQueFalla;
use Pacientes\EnMemoria\RepositorioPacientesEnMemoria;
use Pacientes\EnMemoria\TransaccionEnMemoria;
use Pacientes\Servicios\ControlAcceso;
use Pacientes\Servicios\ServicioActualizacionPacientes;
use Pacientes\Servicios\ServicioBusquedaPacientes;
use Pacientes\Servicios\ServicioRegistroPacientes;

const H_ORIENTE = 'HOSP-ORIENTE';
const H_NORTE = 'HOSP-NORTE';

/** Permisos de demostración (en el HIS los decide el módulo RBAC). */
function permisosDemo(): array
{
    $basicos = ['pacientes.registrar', 'pacientes.buscar', 'pacientes.leer', 'pacientes.editar'];

    return [
        'admision|' . H_ORIENTE => $basicos,
        'clinico|' . H_ORIENTE => ['pacientes.buscar', 'pacientes.leer'],
        'supervisor|' . H_ORIENTE => [...$basicos, 'pacientes.confirmar_duplicado', 'pacientes.editar_restringido'],
        'admision_norte|' . H_NORTE => $basicos,
    ];
}

/**
 * @param 'principal'|'replica' $fuenteBusqueda de dónde lee el servicio de búsqueda
 * @param bool $conCache si el repositorio de escritura va envuelto en la caché
 */
function componerPacientes(string $fuenteBusqueda = 'principal', bool $conCache = false, bool $auditoriaFalla = false): object
{
    $hoy = new DateTimeImmutable('2026-10-21');
    $almacen = new RepositorioPacientesEnMemoria();
    $indice = new IndiceMaestroEnMemoria();
    $bitacora = new RegistroAuditoriaEnMemoria();
    $auditoria = $auditoriaFalla ? new RegistroAuditoriaQueFalla() : $bitacora;
    $transaccion = new TransaccionEnMemoria([$almacen, $indice, $bitacora]);
    $acceso = new ControlAcceso(new PoliticaAccesoEnMemoria(permisosDemo()));

    $repositorio = $conCache ? new RepositorioPacientesConCache($almacen) : $almacen;
    $consulta = match ($fuenteBusqueda) {
        'principal' => $repositorio,
        'replica' => new ReplicaLecturaPacientes($almacen),
    };

    return (object) [
        'registro' => new ServicioRegistroPacientes($repositorio, $indice, $auditoria, $transaccion, $acceso, $hoy),
        'busqueda' => new ServicioBusquedaPacientes($consulta, $auditoria, $acceso),
        'actualizacion' => new ServicioActualizacionPacientes($repositorio, $indice, $auditoria, $transaccion, $acceso, $hoy),
        'almacen' => $almacen,
        'indice' => $indice,
        'bitacora' => $bitacora,
    ];
}
