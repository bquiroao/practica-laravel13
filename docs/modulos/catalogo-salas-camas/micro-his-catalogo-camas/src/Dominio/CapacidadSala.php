<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Dominio;

/** Capacidad de una sala: máximo de camas en uso (cambio práctico de la semana 6). null = sin tope, como las salas ya creadas. */
final class CapacidadSala
{
    public readonly ?int $maximo;

    public function __construct(mixed $maximo)
    {
        if ($maximo !== null && (!is_int($maximo) || $maximo < 1)) throw new ReglaDeDominio('E2', 'La capacidad debe ser un entero mayor que 0.');
        $this->maximo = $maximo;
    }

    /** Las camas inactivas no cuentan: salieron de servicio (R-08) y no ocupan la capacidad habilitada. */
    public function admiteOtraCama(int $enUso): void
    {
        if ($this->maximo !== null && $enUso >= $this->maximo) throw new ReglaDeDominio('E9', "La sala llegó a su capacidad de {$this->maximo} camas.");
    }
}
