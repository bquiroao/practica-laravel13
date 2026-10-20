<?php
declare(strict_types=1);

namespace MicroHis\Domain;

/** El contrato de la semana 2 (LSP) más el nivel 3: todos los reportes prometen lo mismo. */
interface ContratoReporte
{
    public function tipo(): string;

    /** @return list<string> Las dimensiones se declaran en lugar de fallar. */
    public function dimensiones(): array;

    /** @throws SinDatos si el período no tiene datos (RN-06) */
    public function calcular(Filtros $f): Indicador;

    /** Pre: $dimension está en dimensiones(). Post: total() = numerador (RN-07). */
    public function desglosar(Filtros $f, string $dimension): Desglose;

    /**
     * Pre: $dimension está en dimensiones().
     * Post: count(resultado) = valor de $parte en el desglose (RN-07 hasta el nivel 3).
     * @return list<RegistroOrigen>
     */
    public function registros(Filtros $f, string $dimension, string $parte): array;
}
