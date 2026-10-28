<?php

declare(strict_types=1);

namespace NotasSoap\Cliente;

/**
 * Lo que recibe el módulo que usa el cliente. Nunca lanza excepción por fallas de red:
 *   OK            el servidor atendió (2xx).
 *   RECHAZADO     el servidor respondió con un error del cliente o de negocio (4xx): no se reintenta.
 *   NO_DISPONIBLE no hubo respuesta útil (red, 5xx o circuito abierto): la pantalla muestra
 *                 "Notas no disponibles por ahora" y conserva lo que el médico escribió.
 */
final class ResultadoCliente
{
    public const OK = 'OK';
    public const RECHAZADO = 'RECHAZADO';
    public const NO_DISPONIBLE = 'NO_DISPONIBLE';

    /** @param array<string, mixed>|null $cuerpo */
    public function __construct(
        public readonly string $tipo,
        public readonly ?int $estadoHttp,
        public readonly ?array $cuerpo,
        public readonly string $origen,
        public readonly int $intentos,
        public readonly string $requestId,
        public readonly bool $repetida = false,
    ) {
    }

    public function codigoError(): ?string
    {
        $codigo = $this->cuerpo['error']['code'] ?? null;

        return is_string($codigo) ? $codigo : null;
    }
}
