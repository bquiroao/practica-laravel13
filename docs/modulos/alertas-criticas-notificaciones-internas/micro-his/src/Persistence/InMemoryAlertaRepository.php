<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\{Alerta, AlertaRepository, ErrorPersistenciaException};

/**
 * Adaptador en memoria con el MISMO contrato que PdoAlertaRepository: guarda filas (no objetos vivos),
 * respeta la unicidad de origen (RN-02), filtra por tenant y devuelve copias reconstruidas.
 */
final class InMemoryAlertaRepository implements AlertaRepository
{
    /** @var array<string, array{fila: array, destinatarios: array<string,int>, historial: array}> */
    private array $filas = [];

    public function guardar(Alerta $alerta): void
    {
        foreach ($this->filas as $id => $f) {
            if ($id !== $alerta->id && $f['fila']['tenant'] === $alerta->tenant
                && $f['fila']['origen'] === $alerta->origen && $f['fila']['id_origen'] === $alerta->idOrigen) {
                throw new ErrorPersistenciaException('Violación de unicidad: ya existe una alerta para ese evento de origen');
            }
        }
        $previo = $this->filas[$alerta->id] ?? null;
        $historial = $previo['historial'] ?? [];
        foreach ($alerta->historial() as $evento) {
            $historial[$evento->orden] ??= $evento; // RN-10: los eventos ya guardados no se reemplazan
        }
        $destinatarios = ($previo['destinatarios'] ?? []) + $alerta->destinatarios();
        uksort($destinatarios, fn ($a, $b) => [$destinatarios[$a], $a] <=> [$destinatarios[$b], $b]); // como ORDER BY nivel, usuario
        $this->filas[$alerta->id] = [
            'fila' => [
                'id' => $alerta->id, 'tenant' => $alerta->tenant, 'codigo_paciente' => $alerta->paciente->valor,
                'servicio' => $alerta->servicio, 'origen' => $alerta->origen, 'id_origen' => $alerta->idOrigen,
                'prioridad' => $alerta->prioridad->value, 'descripcion' => $alerta->descripcion,
                'estado' => $alerta->estado()->value, 'nivel' => $alerta->nivel(),
                'plazo_desde' => $alerta->plazoDesde()?->format(DATE_ATOM),
                'escalamiento_agotado' => $alerta->escalamientoAgotado(),
                'creada_en' => $alerta->historial()[0]->momento,
            ],
            'destinatarios' => $destinatarios,
            'historial' => $historial,
        ];
    }

    public function buscar(string $id, string $tenant): ?Alerta
    {
        $f = $this->filas[$id] ?? null;
        return $f !== null && $f['fila']['tenant'] === $tenant ? $this->reconstruir($f) : null;
    }

    public function buscarPorOrigen(string $tenant, string $origen, string $idOrigen): ?Alerta
    {
        foreach ($this->filas as $f) {
            if ([$f['fila']['tenant'], $f['fila']['origen'], $f['fila']['id_origen']] === [$tenant, $origen, $idOrigen]) {
                return $this->reconstruir($f);
            }
        }
        return null;
    }

    public function pendientesDeAcuse(): array
    {
        $pendientes = array_filter($this->filas, fn ($f) => in_array($f['fila']['estado'], ['ENVIADA', 'ESCALADA'], true)
            && !$f['fila']['escalamiento_agotado']);
        usort($pendientes, fn ($a, $b) => $a['fila']['plazo_desde'] <=> $b['fila']['plazo_desde']);
        return array_map(fn ($f) => $this->reconstruir($f), $pendientes);
    }

    public function listar(string $tenant): array
    {
        $delTenant = array_filter($this->filas, fn ($f) => $f['fila']['tenant'] === $tenant);
        usort($delTenant, fn ($a, $b) => $b['fila']['creada_en'] <=> $a['fila']['creada_en']);
        return array_map(fn ($f) => $this->reconstruir($f), array_slice($delTenant, 0, 100));
    }

    private function reconstruir(array $f): Alerta
    {
        ksort($f['historial']);
        return Alerta::reconstituir($f['fila'], $f['destinatarios'], array_values($f['historial']));
    }
}
