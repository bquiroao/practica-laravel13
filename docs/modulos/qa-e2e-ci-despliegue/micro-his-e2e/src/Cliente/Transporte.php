<?php

declare(strict_types=1);

namespace E2e\Cliente;

/**
 * Cómo llega una petición al servidor. El cliente se programa contra el CONTRATO (api/openapi.yaml), no contra las capas:
 * no importa nada de E2e\Aplicacion, Dominio, Persistencia ni Presentacion (ARQ-07).
 */
interface Transporte
{
    /**
     * @param array<string, string> $cabeceras
     * @return array{estado: int, cuerpo: array<string, mixed>}
     * @throws FallaTransporte red caída, tiempo agotado o respuesta ilegible
     */
    public function enviar(string $metodo, string $ruta, array $cabeceras, string $cuerpo): array;
}
