<?php
declare(strict_types=1);

// ANTES: ConsultarDisponibilidad tal como está en la semana 3. Lee tres fuentes y además decide qué médico atiende cada día.
namespace Medicos\Antes;

use Medicos\Dominio\{Agenda, Vigencia};
use Medicos\Persistencia\MedicosPdo;
use function Medicos\Aplicacion\exigirRol;
use Medicos\Aplicacion\Usuario;

final class ConsultarDisponibilidad {
    public function __construct(private MedicosPdo $bd, private \Closure $hoy) {}
    public function ejecutar(string $codigo, string $desde, string $hasta, Usuario $u): array {
        exigirRol($u, ['admin', 'recepcion', 'medico']);
        Agenda::exigirRango($desde, $hasta, ($this->hoy)());
        $medicos = $this->bd->medicosConEspecialidad($codigo);
        [$horarios, $citas] = [$this->bd->horarios(array_keys($medicos)), $this->bd->citasActivas(array_keys($medicos), $desde, $hasta)];
        $franjas = [];
        for ($d = new \DateTimeImmutable($desde); ($fecha = $d->format('Y-m-d')) <= $hasta; $d = $d->modify('+1 day')) {
            foreach ($medicos as $id => [$nombre, $vigencias]) {
                if (!array_filter($vigencias, fn (Vigencia $v) => $v->incluye($fecha))) { continue; }
                foreach (Agenda::franjas($fecha, $horarios[$id][(int) $d->format('N')] ?? [], $citas[$id] ?? []) as $inicio) {
                    $franjas[] = ['medico_id' => $id, 'medico' => $nombre, 'inicio' => $inicio];
                }
            }
        }
        return $franjas;
    }
}
