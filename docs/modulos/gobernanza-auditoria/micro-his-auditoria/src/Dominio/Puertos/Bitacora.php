<?php

declare(strict_types=1);

namespace Auditoria\Dominio\Puertos;

use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\ConsultaMovimientos;
use Auditoria\Dominio\Modelo\Movimiento;

/**
 * Puerto de persistencia de audit_logs. A propósito NO tiene actualizar() ni eliminar():
 * la inmutabilidad (R-02) empieza en el contrato. Todas las operaciones pueden lanzar ErrorPersistencia.
 */
interface Bitacora
{
    /** Inserta un movimiento YA sellado y lo devuelve con el id asignado. @throws ErrorPersistencia */
    public function agregar(Movimiento $movimiento): Movimiento;

    /**
     * Serializa los registros de UNA cadena hasta el fin de la transacción, para que dos
     * registros simultáneos no tomen la misma secuencia. @throws ErrorPersistencia
     */
    public function bloquearCadena(?string $hospital): void;

    /** @throws ErrorPersistencia */
    public function porEvento(string $emisor, string $eventId): ?Movimiento;

    /** @throws ErrorPersistencia */
    public function porId(int $id): ?Movimiento;

    /** Último movimiento de la cadena de ese hospital (null = nivel red). @throws ErrorPersistencia */
    public function ultimo(?string $hospital): ?Movimiento;

    /**
     * Ordenados por ocurrido_en e id, de forma descendente (R-09), con el límite de la consulta.
     *
     * @return list<Movimiento>
     * @throws ErrorPersistencia
     */
    public function buscar(ConsultaMovimientos $consulta): array;

    /** Semana 6: la corrección que referencia a ese movimiento, si existe (hay a lo sumo una). @throws ErrorPersistencia */
    public function correccionDe(int $id): ?Movimiento;

    /**
     * Semana 6: para una página de resultados, qué movimientos fueron corregidos y por cuál.
     *
     * @param list<int> $ids
     * @return array<int, int> id original => id de su corrección
     * @throws ErrorPersistencia
     */
    public function correccionesDe(array $ids): array;

    /**
     * La cadena completa de un hospital, por secuencia ascendente (para verificarla).
     *
     * @return list<Movimiento>
     * @throws ErrorPersistencia
     */
    public function cadena(?string $hospital): array;
}
