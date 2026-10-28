<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Persistencia;

use MicroHis\DashboardOcupacion\Aplicacion\RepositorioOcupacion;
use MicroHis\DashboardOcupacion\Dominio\Corte;

/** Adaptador en memoria de la interfaz Repository: entrega las mismas filas que RepositorioPdo, sin base. Para pruebas y demos. */
final class RepositorioEnMemoria implements RepositorioOcupacion
{
    /** @var list<Corte> */
    private array $cortes = [];

    /**
     * @param array<string, bool> $salas código => activa (wards)
     * @param list<array{0: string, 1: string, 2: string, 3: bool}> $camas [sala, cama, estado, con estancia abierta] (beds y bed_stays)
     */
    public function __construct(private array $salas = [], private array $camas = []) {}

    public function camasDeSalasActivas(): array
    {
        $activas = array_keys(array_filter($this->salas)); sort($activas);   // salas activas (R-03) en el mismo orden que el ORDER BY de PDO
        $filas = [];
        foreach ($activas as $sala) {
            $deLaSala = array_filter($this->camas, fn (array $c) => $c[0] === $sala);
            usort($deLaSala, fn (array $x, array $y) => strcmp($x[1], $y[1]));
            if ($deLaSala === []) $filas[] = ['sala' => $sala, 'cama' => null, 'estado' => null, 'con_estancia' => false];   // como el LEFT JOIN
            foreach ($deLaSala as [, $cama, $estado, $abierta]) $filas[] = ['sala' => $sala, 'cama' => $cama, 'estado' => $estado, 'con_estancia' => $abierta];
        }
        return $filas;
    }

    public function guardarCorte(Corte $corte): void { $this->cortes[] = $corte; }
    public function ultimoCorte(): ?Corte { return $this->cortes === [] ? null : $this->cortes[array_key_last($this->cortes)]; }
}
