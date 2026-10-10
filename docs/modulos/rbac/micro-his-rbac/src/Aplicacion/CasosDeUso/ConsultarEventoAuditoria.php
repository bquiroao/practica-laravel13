<?php

declare(strict_types=1);

namespace Rbac\Aplicacion\CasosDeUso;

use Rbac\Dominio\Puertos\RegistroAuditoria;

/**
 * CU-06 Consultar denegaciones registradas.
 * Solo devuelve eventos del mismo hospital que consulta (aislamiento por tenant).
 */
final class ConsultarEventoAuditoria
{
    public function __construct(private readonly RegistroAuditoria $auditoria)
    {
    }

    /** @return array<string, mixed>|null */
    public function ejecutar(string $correlationId, string $hospitalId): ?array
    {
        $evento = $this->auditoria->buscarPorCorrelacion($correlationId);
        if ($evento === null || $evento->hospitalId !== $hospitalId) {
            return null;
        }

        return [
            'correlation_id' => $evento->correlationId,
            'tipo' => $evento->tipo,
            'actor' => $evento->actorId,
            'hospital' => $evento->hospitalId,
            'datos' => $evento->datos,
            'ocurrido_en' => $evento->ocurridoEn->format(DATE_ATOM),
        ];
    }
}
