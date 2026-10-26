<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\Comandos;

final class ComandoVerificar
{
    /**
     * @param string|null $hospitalVerificado la cadena a verificar (null = la de nivel red)
     * @param array{secuencia: int, sello: string}|null $ancla semana 5: una cabeza de cadena publicada antes
     *        fuera de la base; si la cadena ya no la contiene, fue truncada o reescrita
     */
    public function __construct(
        public readonly string $actor,
        public readonly ?string $hospitalVerificado,
        public readonly ?array $ancla = null,
    ) {
    }
}
