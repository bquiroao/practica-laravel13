<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Persistencia;

use MicroHis\DashboardBase\Aplicacion\FuenteConteos;

/** Adaptador en memoria de la interfaz Repository FuenteConteos: cuenta con las mismas reglas que RepositorioPdo, sin base. Para pruebas y demos. */
final class RepositorioEnMemoria implements FuenteConteos
{
    /**
     * @param string[] $pacientes estados de local_patients
     * @param list<array{0: string, 1: string}> $citas [inicio 'Y-m-d H:i', estado] de appointments
     * @param string[] $camas estados de beds
     */
    public function __construct(private array $pacientes = [], private array $citas = [], private array $camas = []) {}

    public function conteo(string $tarjeta, string $dia): int
    {
        return match ($tarjeta) {
            'pacientes' => count(array_keys($this->pacientes, 'active', true)),
            'citas_hoy' => count(array_filter($this->citas, fn (array $c) => substr($c[0], 0, 10) === $dia && in_array($c[1], ['scheduled', 'confirmed'], true))),
            'camas_disponibles' => count(array_keys($this->camas, 'available', true)),
        };
    }
}
