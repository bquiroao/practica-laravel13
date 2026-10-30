<?php
declare(strict_types=1);

// DESPUÉS: el evaluador está CERRADO a modificación y ABIERTO a extensión.
// Cada criterio es una clase que implementa CriterioElegibilidadCama.
// Los mismos nombres se usan en el Micro-HIS (semana 3, capa Domain).

namespace OcpDespues;

final class CamaDisponible
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $servicio,
        public readonly string $tipo,
        public readonly bool $aislamiento,
    ) {
    }
}

final class Requerimiento
{
    public function __construct(
        public readonly string $servicio,
        public readonly string $tipo,
        public readonly bool $requiereAislamiento,
    ) {
    }
}

interface CriterioElegibilidadCama
{
    /** Devuelve null si la cama cumple, o el motivo de rechazo. */
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string;
}

final class CriterioServicio implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        return $cama->servicio === $req->servicio ? null : 'La cama es de otro servicio.';
    }
}

final class CriterioTipoCama implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        return $cama->tipo === $req->tipo ? null : 'El tipo de cama no coincide.';
    }
}

// Extensión: el requisito de aislamiento es una clase NUEVA.
// EvaluadorElegibilidad no se tocó para agregarla.
final class CriterioAislamiento implements CriterioElegibilidadCama
{
    public function motivoDeRechazo(CamaDisponible $cama, Requerimiento $req): ?string
    {
        if ($req->requiereAislamiento === $cama->aislamiento) {
            return null;
        }
        return $req->requiereAislamiento
            ? 'El paciente requiere una cama de aislamiento.'
            : 'La cama de aislamiento se reserva para quien la requiere.';
    }
}

final class EvaluadorElegibilidad
{
    /** @var list<CriterioElegibilidadCama> */
    private array $criterios;

    public function __construct(CriterioElegibilidadCama ...$criterios)
    {
        $this->criterios = $criterios;
    }

    /** @return list<string> lista vacía = la cama es elegible */
    public function motivosDeRechazo(CamaDisponible $cama, Requerimiento $req): array
    {
        $motivos = [];
        foreach ($this->criterios as $criterio) {
            $motivo = $criterio->motivoDeRechazo($cama, $req);
            if ($motivo !== null) {
                $motivos[] = $motivo;
            }
        }
        return $motivos;
    }
}
