<?php

declare(strict_types=1);

/*
 * Pruebas de contrato: las MISMAS verificaciones se ejecutan contra cada adaptador.
 * Si los dos las pasan, son intercambiables (LSP) y el resto del sistema no nota
 * cuál se usa. InMemory siempre; PDO solo si hay PostgreSQL (MICROHIS_DB_DSN).
 */

use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Persistencia\ConjuntoAdaptadores;
use Rbac\Persistencia\EnMemoria\FabricaEnMemoria;
use Rbac\Persistencia\Pdo\FabricaPdo;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Soporte\PostgresTemporal;

function contratoRepositorios(string $prefijo, ConjuntoAdaptadores $a): void
{
    $fecha = static fn (string $f): DateTimeImmutable => new DateTimeImmutable($f);

    prueba("$prefijo-01", 'RepositorioMembresias::membresiasDe filtra por usuario y hospital y conserva la vigencia', function () use ($a, $fecha): void {
        $ana = $a->membresias->membresiasDe('ana', 'hosp-oriente');
        igual(1, count($ana), 'membresías de ana');
        igual('enfermeria', $ana[0]->rol, 'rol');
        igual('active', $ana[0]->estado, 'estado');
        afirmar($ana[0]->validoDesde == $fecha('2026-01-01T00:00:00-06:00'), 'valido_desde');
        igual(null, $ana[0]->validoHasta, 'valido_hasta');
        afirmar($a->membresias->membresiasDe('pedro', 'hosp-oriente')[0]->validoHasta == $fecha('2026-06-30T00:00:00-06:00'), 'fin de pedro');
        igual([], $a->membresias->membresiasDe('ana', 'hosp-norte'), 'otro hospital');
        igual([], $a->membresias->membresiasDe('nadie', 'hosp-oriente'), 'usuario inexistente');
    });

    prueba("$prefijo-02", 'RepositorioMembresias::existeActiva solo cuenta membresías activas y abiertas', function () use ($a): void {
        afirmar($a->membresias->existeActiva('ana', 'hosp-oriente', 'enfermeria'), 'ana enfermería');
        afirmar(!$a->membresias->existeActiva('lucia', 'hosp-oriente', 'enfermeria'), 'revocada');
        afirmar(!$a->membresias->existeActiva('pedro', 'hosp-oriente', 'medico'), 'con fecha de fin');
        afirmar(!$a->membresias->existeActiva('ana', 'hosp-oriente', 'medico'), 'otro rol');
    });

    prueba("$prefijo-03", 'RepositorioMembresias::guardar persiste y rechaza el duplicado activo con MembresiaDuplicada', function () use ($a, $fecha): void {
        $a->membresias->guardar(new Membresia('nuevo-01', 'hosp-oriente', 'laboratorio', 'active', $fecha('2026-11-15T00:00:00-06:00')));
        afirmar($a->membresias->existeActiva('nuevo-01', 'hosp-oriente', 'laboratorio'), 'no se guardó');
        lanza(MembresiaDuplicada::class, fn () => $a->membresias->guardar(
            new Membresia('nuevo-01', 'hosp-oriente', 'laboratorio', 'active', $fecha('2026-12-01T00:00:00-06:00')),
        ), 'duplicado');
        // Con fecha de fin no es «abierta»: no choca con la regla de unicidad.
        $a->membresias->guardar(new Membresia('nuevo-01', 'hosp-oriente', 'laboratorio', 'active', $fecha('2026-01-01T00:00:00-06:00'), $fecha('2026-02-01T00:00:00-06:00')));
        igual(2, count($a->membresias->membresiasDe('nuevo-01', 'hosp-oriente')), 'membresías de nuevo-01');
    });

    prueba("$prefijo-04", 'CatalogoPermisos y DirectorioUsuarios responden igual que los datos semilla', function () use ($a): void {
        afirmar($a->catalogo->existeRol('medico') && !$a->catalogo->existeRol('superusuario'), 'existeRol');
        afirmar($a->catalogo->rolConcede('medico', 'prescripciones.crear'), 'médico prescribe');
        afirmar(!$a->catalogo->rolConcede('enfermeria', 'prescripciones.crear'), 'enfermería no prescribe');
        igual('active', $a->usuarios->estadoDe('ana'), 'ana');
        igual('inactive', $a->usuarios->estadoDe('jorge'), 'jorge');
        igual(null, $a->usuarios->estadoDe('nadie'), 'inexistente');
    });

    prueba("$prefijo-05", 'RegistroAuditoria guarda y recupera el evento completo por correlation_id', function () use ($a, $prefijo): void {
        $cid = strtolower($prefijo) . '-evento';
        $datos = ['permiso' => 'prescripciones.crear', 'recurso' => 'POST /x', 'motivo' => 'PERMISO_NO_ASIGNADO', 'ip' => '10.20.5.14'];
        $a->auditoria->registrar(new EventoAuditoria('ACCESO_DENEGADO', $cid, 'ana', 'hosp-oriente', $datos, new DateTimeImmutable(Escenario::AHORA)));
        $leido = $a->auditoria->buscarPorCorrelacion($cid);
        igual('ACCESO_DENEGADO', $leido?->tipo, 'tipo');
        igual('ana', $leido?->actorId, 'actor');
        $ordenado = static function (array $d): array {
            ksort($d);

            return $d;
        };
        igual($ordenado($datos), $ordenado($leido?->datos ?? []), 'datos');
        afirmar($leido?->ocurridoEn == new DateTimeImmutable(Escenario::AHORA), 'fecha');
        igual(null, $a->auditoria->buscarPorCorrelacion('no-existe'), 'inexistente');
    });

    prueba("$prefijo-06", 'Transaccion: confirma si el trabajo termina y revierte si lanza', function () use ($a, $fecha): void {
        $a->transaccion->ejecutar(fn () => $a->membresias->guardar(new Membresia('nuevo-01', 'hosp-oriente', 'medico', 'active', $fecha('2026-11-15T00:00:00-06:00'))));
        afirmar($a->membresias->existeActiva('nuevo-01', 'hosp-oriente', 'medico'), 'commit');
        lanza(RuntimeException::class, fn () => $a->transaccion->ejecutar(function () use ($a, $fecha): void {
            $a->membresias->guardar(new Membresia('nuevo-01', 'hosp-oriente', 'enfermeria', 'active', $fecha('2026-11-15T00:00:00-06:00')));
            throw new RuntimeException('falla a mitad del trabajo');
        }), 'trabajo falla');
        afirmar(!$a->membresias->existeActiva('nuevo-01', 'hosp-oriente', 'enfermeria'), 'rollback');
    });
}

contratoRepositorios('CON-MEM', FabricaEnMemoria::crear(Escenario::datosDemo()));

if (PostgresTemporal::disponible()) {
    PostgresTemporal::ejecutar(static fn (PDO $pdo) => contratoRepositorios('CON-PDO', FabricaPdo::crear($pdo)));
} else {
    prueba('CON-PDO', 'El mismo contrato contra los adaptadores PDO (PostgreSQL)', static fn () => omitir('defina MICROHIS_DB_DSN para ejecutarlo'));
}
