<?php
declare(strict_types=1);

namespace MicroHis\Catalogo\Aplicacion;

use MicroHis\Catalogo\Dominio\{EstadoCama, ReglaDeDominio};

/** Puerto de persistencia: lo define la aplicación y lo implementa la capa de persistencia (DIP). */
interface RepositorioCatalogo
{
    public function salaActiva(string $sala): ?bool;   // null = la sala no existe
    public function guardarSala(string $sala, string $nombre): void;
    public function estadoCama(string $sala, string $cama): ?string;
    public function guardarCama(string $sala, string $cama): void;
    /** Cambia el estado solo si sigue siendo $de y deja el historial; false = otro usuario la cambió antes. */
    public function cambiarEstado(string $sala, string $cama, string $de, string $a, string $motivo): bool;
}

/** Fallo técnico de la base: no es una regla de negocio. */
final class ErrorPersistencia extends \RuntimeException {}

/** Casos de uso CU-01 a CU-03 de la semana 1. Ordena el flujo; las reglas viven en el dominio. */
final class ServicioCatalogo
{
    public function __construct(private RepositorioCatalogo $repo) {}

    public function registrarSala(string $sala, string $nombre): void
    {
        if (trim($sala) === '' || trim($nombre) === '') throw new ReglaDeDominio('E2', 'Código y nombre son obligatorios.');
        if ($this->repo->salaActiva($sala) !== null) throw new ReglaDeDominio('E3', "La sala $sala ya existe.");
        $this->repo->guardarSala($sala, $nombre);
    }

    public function registrarCama(string $sala, string $cama): void
    {
        if ($this->repo->salaActiva($sala) !== true) throw new ReglaDeDominio('E4', "La sala $sala no existe o está inactiva.");
        if (trim($cama) === '') throw new ReglaDeDominio('E2', 'El código de cama es obligatorio.');
        if ($this->repo->estadoCama($sala, $cama) !== null) throw new ReglaDeDominio('E3', "La cama $sala/$cama ya existe.");
        $this->repo->guardarCama($sala, $cama);
    }

    public function cambiarEstado(string $sala, string $cama, string $nuevo, string $motivo): void
    {
        $actual = $this->repo->estadoCama($sala, $cama) ?? throw new ReglaDeDominio('NF', "La cama $sala/$cama no existe.");
        if (trim($motivo) === '') throw new ReglaDeDominio('E2', 'El motivo es obligatorio.');
        $destino = EstadoCama::tryFrom($nuevo) ?? throw new ReglaDeDominio('E2', "Estado desconocido: $nuevo.");
        EstadoCama::from($actual)->validarCambioA($destino);
        if (!$this->repo->cambiarEstado($sala, $cama, $actual, $nuevo, $motivo)) throw new ReglaDeDominio('E7', 'Otro usuario cambió la cama: recarga y reintenta.');
    }
}
