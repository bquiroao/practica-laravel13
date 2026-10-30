<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Puerto de persistencia. Los adaptadores lanzan ErrorPersistenciaException ante cualquier falla. */
interface AlertaRepository
{
    public function guardar(Alerta $alerta): void;

    public function buscar(string $id, string $tenant): ?Alerta;

    public function buscarPorOrigen(string $tenant, string $origen, string $idOrigen): ?Alerta;

    /** @return Alerta[] alertas ENVIADA o ESCALADA sin escalamiento agotado (todos los tenants). */
    public function pendientesDeAcuse(): array;

    /** @return Alerta[] alertas del tenant, más recientes primero. */
    public function listar(string $tenant): array;
}
