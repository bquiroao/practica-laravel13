<?php
declare(strict_types=1);

namespace MicroHis\Persistence;

use MicroHis\Domain\Filtros;
use MicroHis\Domain\OcupacionRepository;
use MicroHis\Domain\RegistroOrigen;

/** Adaptador en memoria del Repository: mismas reglas que el adaptador PDO, sin base de datos. */
final class OcupacionInMemoryRepository implements OcupacionRepository
{
    /**
     * @param array<string,array{sala:string,habilitada:bool}> $camas por código de cama
     * @param list<array{cama:string,paciente:string,ingreso:string,egreso:?string}> $estancias fechas AAAA-MM-DD
     */
    public function __construct(private readonly array $camas, private readonly array $estancias)
    {
    }

    public function camasHabilitadas(): int
    {
        return count(array_filter($this->camas, static fn (array $c) => $c['habilitada']));
    }

    public function ocupadasPorSala(Filtros $f): array
    {
        $porSala = [];
        foreach ($this->estanciasEnPeriodo($f) as $e) {
            $porSala[$this->camas[$e['cama']]['sala']][$e['cama']] = true;
        }
        $conteo = array_map('count', $porSala);
        uksort($conteo, static fn ($a, $b) => [$conteo[$b], $a] <=> [$conteo[$a], $b]);   // como ORDER BY ocupadas DESC, sala
        return $conteo;
    }

    public function registrosDeSala(Filtros $f, string $sala): array
    {
        $ultimaPorCama = [];
        foreach ($this->estanciasEnPeriodo($f) as $e) {
            if ($this->camas[$e['cama']]['sala'] === $sala && ($ultimaPorCama[$e['cama']]['ingreso'] ?? '') < $e['ingreso']) {
                $ultimaPorCama[$e['cama']] = $e;
            }
        }
        ksort($ultimaPorCama);
        return array_values(array_map(
            static fn (array $e) => new RegistroOrigen($e['cama'], $e['paciente'], $e['ingreso'], $e['egreso']),
            $ultimaPorCama,
        ));
    }

    /** Misma regla que el SQL: la estancia se cruza con el período y la cama está habilitada. */
    private function estanciasEnPeriodo(Filtros $f): array
    {
        return array_filter($this->estancias, fn (array $e) => $this->camas[$e['cama']]['habilitada']
            && $e['ingreso'] <= $f->hasta && ($e['egreso'] === null || $e['egreso'] >= $f->desde));
    }
}
