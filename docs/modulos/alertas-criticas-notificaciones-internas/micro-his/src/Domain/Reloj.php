<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Reloj inyectable (RNF-04): en pruebas se usa uno fijo, nunca se esperan minutos reales. */
interface Reloj
{
    public function ahora(): \DateTimeImmutable;
}
