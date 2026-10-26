<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Dominio;

/** Violación de una regla de negocio. El código (E2..E7, NF) lo traduce a HTTP la capa de presentación. */
final class ReglaDeDominio extends \DomainException
{
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}

/** Estados de una versión (R-03, R-08): la publicada no se edita y la obsoleta no se borra ni vuelve atrás. */
enum EstadoVersion: string
{
    case EnRevision = 'in_review'; case Publicada = 'published'; case Rechazada = 'rejected'; case Obsoleta = 'obsolete';
    public function validarCambioA(self $nuevo): void
    {
        $permitidas = match ($this) { self::EnRevision => [self::Publicada, self::Rechazada], self::Publicada => [self::Obsoleta], default => [] };
        if (!in_array($nuevo, $permitidas, true)) throw new ReglaDeDominio('E7', "Una versión {$this->value} no puede pasar a {$nuevo->value}.");
    }
}

/** Versión enviada a revisión (R-01, R-02): tipo conocido, x.y.z mayor que las no rechazadas y audiencia no vacía. */
final class VersionNueva
{
    private function __construct(public readonly string $slug, public readonly string $tipo, public readonly string $version, public readonly array $audiencia) {}
    public static function validar(string $slug, string $tipo, string $version, array $audiencia, array $existentes): self
    {
        $audiencia = array_values(array_unique(array_filter(array_map('trim', $audiencia))));
        if ($slug === '' || !in_array($tipo, ['openapi', 'postman', 'manual'], true) || !preg_match('/^\d+\.\d+\.\d+$/', $version) || $audiencia === [])
            throw new ReglaDeDominio('E2', 'Faltan el tipo, la versión x.y.z o la audiencia.');
        foreach ($existentes as $v) if (version_compare($version, $v, '<=')) throw new ReglaDeDominio('E3', "La versión debe ser mayor que la $v.");
        return new self($slug, $tipo, $version, $audiencia);
    }
}

/** R-06: un lector ve una versión solo si comparte al menos un rol con su audiencia. */
final class Audiencia { public static function permite(array $audiencia, array $roles): bool { return array_intersect($roles, $audiencia) !== []; } }
