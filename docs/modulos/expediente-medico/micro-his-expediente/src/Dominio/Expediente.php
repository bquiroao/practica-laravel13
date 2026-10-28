<?php
declare(strict_types=1);

namespace MicroHis\Expediente\Dominio;

/** Violación de una regla de negocio. El código (E2..E5, NF) lo traduce a HTTP la capa de presentación. */
final class ReglaDeDominio extends \DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}

/** Paciente local del hospital (R-01, R-03): solo uno activo puede tener expediente, y uno solo. */
final class Paciente
{
    public function __construct(public readonly string $codigo, public readonly string $idGlobal, public readonly string $estado,
                                public readonly bool $vinculadoMpi, public readonly ?string $expediente) {}
    public function numeroParaAbrir(): string
    {
        if ($this->estado !== 'active') throw new ReglaDeDominio('E2', 'Solo un paciente activo puede tener expediente.');
        if ($this->expediente !== null) throw new ReglaDeDominio('E3', "El paciente ya tiene el expediente {$this->expediente}.");
        return "EXP-{$this->codigo}";
    }
}

/** Consentimiento de CENTRAL (R-06, R-10): vale para este hospital solo dentro de su vigencia [desde, hasta). */
final class Consentimiento
{
    public static function vigente(?array $c, int $ahora): bool { return $c !== null && $c['desde'] <= $ahora && ($c['hasta'] === null || $ahora < $c['hasta']); }
}
