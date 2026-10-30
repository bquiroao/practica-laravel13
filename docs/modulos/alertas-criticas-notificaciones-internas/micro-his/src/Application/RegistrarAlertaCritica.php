<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, AlertaRepository, Reloj};

/** CU-01: registra la alerta (sin duplicar), la guarda y notifica al nivel 0. */
final class RegistrarAlertaCritica
{
    public function __construct(
        private AlertaRepository $repositorio,
        private NotificarNivel $notificar,
        private Reloj $reloj,
    ) {}

    /** @return array{alerta: Alerta, duplicada: bool} */
    public function ejecutar(array $datos): array
    {
        $ahora = $this->reloj->ahora();
        $tenant = trim((string) ($datos['tenant'] ?? ''));
        $origen = trim((string) ($datos['origen'] ?? ''));
        $idOrigen = trim((string) ($datos['id_origen'] ?? ''));
        if ($tenant !== '' && $origen !== '' && $idOrigen !== '') {
            $existente = $this->repositorio->buscarPorOrigen($tenant, $origen, $idOrigen); // RN-02
            if ($existente !== null) {
                return ['alerta' => $existente, 'duplicada' => true];
            }
        }
        $alerta = Alerta::crear($datos, $ahora); // RN-01
        $this->repositorio->guardar($alerta);    // se guarda antes de notificar: si falla, no se envía nada
        $this->notificar->ejecutar($alerta, $ahora);
        $this->repositorio->guardar($alerta);
        return ['alerta' => $alerta, 'duplicada' => false];
    }
}
