<?php

declare(strict_types=1);

namespace Auditoria\Cliente;

/** Lo que recibe el módulo emisor: la respuesta y DE DÓNDE salió. */
final class ResultadoCliente
{
    public const SERVIDOR = 'SERVIDOR';                   // el servicio respondió (2xx o 4xx)
    public const EN_BANDEJA_LOCAL = 'EN_BANDEJA_LOCAL';   // no hubo servicio: el movimiento quedó guardado para reenviarlo
    public const FALLA_SERVICIO = 'FALLA_SERVICIO';       // consultas: sin respuesta tras los reintentos
    public const CIRCUITO_ABIERTO = 'CIRCUITO_ABIERTO';   // consultas: ni se intentó

    /** @param array<string, mixed> $cuerpo */
    public function __construct(
        public readonly ?int $estado,
        public readonly array $cuerpo,
        public readonly string $origen,
        public readonly string $requestId,
        public readonly int $intentos,
    ) {
    }

    /** El movimiento quedó en la bitácora (201 nuevo o 200 reintento ya registrado). */
    public function registrado(): bool
    {
        return $this->origen === self::SERVIDOR && in_array($this->estado, [200, 201], true);
    }

    /** El servicio lo rechazó por una regla (4xx): reenviarlo no servirá; hay que corregir el emisor. */
    public function rechazado(): bool
    {
        return $this->origen === self::SERVIDOR && $this->estado !== null && $this->estado >= 400 && $this->estado < 500;
    }
}
