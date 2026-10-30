<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

interface DirectorioDestinatarios
{
    /** @return string[] usuarios que reciben la alerta en ese nivel (0, 1 o 2). */
    public function destinatarios(string $tenant, string $servicio, int $nivel): array;
}
