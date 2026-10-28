<?php

declare(strict_types=1);

namespace Auditoria\Contratos;

use Auditoria\Dominio\ConsultaMovimientos;
use Auditoria\Dominio\ErrorPersistencia;
use Auditoria\Dominio\Movimiento;

/**
 * Puerto de persistencia de audit_logs. A propósito NO tiene actualizar() ni eliminar():
 * la inmutabilidad (R-02) empieza en el contrato.
 * Todas las operaciones pueden lanzar ErrorPersistencia.
 */
interface Bitacora
{
    /** @throws ErrorPersistencia */
    public function agregar(Movimiento $movimiento): void;

    /** @throws ErrorPersistencia */
    public function porEvento(string $emisor, string $eventId): ?Movimiento;

    /** @throws ErrorPersistencia */
    public function porId(int $id): ?Movimiento;

    /** Último movimiento de la cadena de ese hospital (null = nivel red). @throws ErrorPersistencia */
    public function ultimo(?string $hospital): ?Movimiento;

    /** @throws ErrorPersistencia */
    public function siguienteId(): int;

    /**
     * Ordenados por ocurrido_en y id, de forma descendente (R-09).
     *
     * @return list<Movimiento>
     * @throws ErrorPersistencia
     */
    public function buscar(ConsultaMovimientos $consulta): array;

    /**
     * La cadena completa de un hospital, por secuencia ascendente (para verificarla).
     *
     * @return list<Movimiento>
     * @throws ErrorPersistencia
     */
    public function cadena(?string $hospital): array;
}
