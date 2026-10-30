<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, AlertaNoEncontradaException, AlertaRepository, Reloj};

/** CU-04: acuse idempotente (RN-06) de un destinatario notificado (RN-07) del mismo tenant (RNF-01). */
final class AcusarAlerta
{
    public function __construct(private AlertaRepository $repositorio, private Reloj $reloj) {}

    /** @return array{alerta: Alerta, cambio: bool} */
    public function ejecutar(string $idAlerta, string $usuario, string $tenant): array
    {
        $alerta = $this->repositorio->buscar($idAlerta, $tenant)
            ?? throw new AlertaNoEncontradaException("No existe la alerta $idAlerta en el tenant $tenant");
        $cambio = $alerta->acusar($usuario, $this->reloj->ahora());
        if ($cambio) {
            $this->repositorio->guardar($alerta);
        }
        return ['alerta' => $alerta, 'cambio' => $cambio];
    }
}
