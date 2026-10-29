<?php
declare(strict_types=1);

// DESPUÉS: la regla «qué franjas libres hay» sale a una función pura (en el repositorio iría como Agenda::disponibles en Dominio).
// El caso de uso queda con lo suyo: rol, rango, leer y entregar. Mismo resultado, sin base de datos para probar la regla.
namespace Medicos\Despues;

use Medicos\Dominio\{Agenda, Vigencia};
use Medicos\Persistencia\MedicosPdo;
use function Medicos\Aplicacion\exigirRol;
use Medicos\Aplicacion\Usuario;

final class Disponibilidad {
    /** @param array $medicos id => [nombre, Vigencia[]]; $horarios id => [díaSemana => [[inicio, fin]]]; $citas id => [[inicio, fin]] */
    public static function calcular(array $medicos, array $horarios, array $citas, string $desde, string $hasta): array {
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
final class ConsultarDisponibilidad {
    public function __construct(private MedicosPdo $bd, private \Closure $hoy) {}
    public function ejecutar(string $codigo, string $desde, string $hasta, Usuario $u): array {
        exigirRol($u, ['admin', 'recepcion', 'medico']);
        Agenda::exigirRango($desde, $hasta, ($this->hoy)());
        $ids = array_keys($medicos = $this->bd->medicosConEspecialidad($codigo));
        return Disponibilidad::calcular($medicos, $this->bd->horarios($ids), $this->bd->citasActivas($ids, $desde, $hasta), $desde, $hasta);
    }
}
