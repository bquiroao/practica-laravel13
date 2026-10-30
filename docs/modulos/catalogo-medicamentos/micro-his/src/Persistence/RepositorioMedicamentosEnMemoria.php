<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Modelo\ClaveEquivalencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Repositorio\RepositorioMedicamentos;

/**
 * Adaptador InMemory con el MISMO contrato que el PDO (lo demuestra pruebas/contrato): mismos
 * resultados, mismo orden (strcmp = COLLATE "C"), mismo límite y las mismas restricciones que la
 * base (UNIQUE y EXCLUDE) con los mismos códigos de error. Cada escritura es atómica: valida todo
 * antes de modificar el arreglo. Los datos viven mientras dura el proceso.
 */
final class RepositorioMedicamentosEnMemoria implements RepositorioMedicamentos
{
    /** @var array<string, Medicamento> id => versión */
    private array $versiones = [];

    public function buscarVigentes(string $hospitalId, CriterioBusqueda $criterio): array
    {
        $lista = array_filter($this->versiones, fn (Medicamento $m) => $m->hospitalId === $hospitalId && $criterio->coincide($m));
        usort($lista, fn (Medicamento $a, Medicamento $b) => strcmp($a->ficha->nombre, $b->ficha->nombre) ?: strcmp($a->codigo->valor, $b->codigo->valor));
        return array_slice($lista, 0, $criterio->limite);
    }

    public function vigentePorCodigo(string $hospitalId, CodigoMedicamento $codigo, \DateTimeImmutable $fecha): ?Medicamento
    {
        foreach ($this->historial($hospitalId, $codigo) as $m) {
            if ($m->vigenteEn($fecha)) {
                return $m;
            }
        }
        return null;
    }

    public function historial(string $hospitalId, CodigoMedicamento $codigo): array
    {
        $lista = array_filter($this->versiones, fn (Medicamento $m) => $m->hospitalId === $hospitalId && $m->codigo->igualA($codigo));
        usort($lista, fn (Medicamento $a, Medicamento $b) => $a->version <=> $b->version);
        return $lista;
    }

    public function equivalenteVigente(string $hospitalId, ClaveEquivalencia $clave, Vigencia $vigencia, ?CodigoMedicamento $excluir = null): ?CodigoMedicamento
    {
        $codigos = [];
        foreach ($this->versiones as $m) {
            if ($m->hospitalId === $hospitalId && $m->clave()->igualA($clave) && $m->vigencia->seCruzaCon($vigencia)
                && ($excluir === null || !$m->codigo->igualA($excluir))) {
                $codigos[] = $m->codigo->valor;
            }
        }
        sort($codigos, SORT_STRING);
        return $codigos === [] ? null : CodigoMedicamento::desdeTexto($codigos[0]);
    }

    public function siguienteCodigo(string $hospitalId): CodigoMedicamento
    {
        $max = 0;
        foreach ($this->versiones as $m) {
            if ($m->hospitalId === $hospitalId) {
                $max = max($max, $m->codigo->numero());
            }
        }
        return CodigoMedicamento::desdeNumero($max + 1);
    }

    public function agregar(Medicamento $nuevo): void
    {
        $this->verificarRestricciones($nuevo, $this->versiones);
        $this->versiones[$nuevo->id] = $nuevo;
    }

    public function reemplazarVigente(Medicamento $anteriorCerrada, Medicamento $nueva): void
    {
        $copia = $this->versiones;
        $this->aplicarCierre($copia, $anteriorCerrada);
        $this->verificarRestricciones($nueva, $copia);
        $copia[$nueva->id] = $nueva;
        $this->versiones = $copia; // solo si todo lo anterior pasó (atómico)
    }

    public function cerrar(Medicamento $cerrada): void
    {
        $copia = $this->versiones;
        $this->aplicarCierre($copia, $cerrada);
        $this->versiones = $copia;
    }

    public function eliminar(string $hospitalId, CodigoMedicamento $codigo): void
    {
        $this->versiones = array_filter($this->versiones, fn (Medicamento $m) => !($m->hospitalId === $hospitalId && $m->codigo->igualA($codigo)));
    }

    /** Igual que el UPDATE ... WHERE vigente_hasta IS NULL del adaptador PDO. */
    private function aplicarCierre(array &$versiones, Medicamento $cerrada): void
    {
        $actual = $versiones[$cerrada->id] ?? null;
        if ($actual === null || $actual->hospitalId !== $cerrada->hospitalId || !$actual->vigencia->abierta()) {
            throw new ErrorReglaNegocio('CONFLICTO_CONCURRENTE', 'Otra persona cambió este medicamento. Vuelva a cargarlo e intente de nuevo.');
        }
        $versiones[$cerrada->id] = $cerrada;
    }

    /**
     * Equivalente de UNIQUE (hospital, código, versión) y de las dos restricciones EXCLUDE, en el
     * mismo orden en que PostgreSQL revisa los índices, para devolver el mismo código de error.
     */
    private function verificarRestricciones(Medicamento $nuevo, array $versiones): void
    {
        $mismos = array_filter($versiones, fn (Medicamento $m) => $m->hospitalId === $nuevo->hospitalId);
        $reglas = [
            'CONFLICTO_CONCURRENTE' => fn (Medicamento $m) => $m->codigo->igualA($nuevo->codigo) && $m->version === $nuevo->version,
            'SOLAPE_VIGENCIA' => fn (Medicamento $m) => $m->codigo->igualA($nuevo->codigo) && $m->vigencia->seCruzaCon($nuevo->vigencia),
            'DUPLICADO' => fn (Medicamento $m) => $m->clave()->igualA($nuevo->clave()) && $m->vigencia->seCruzaCon($nuevo->vigencia),
        ];
        $mensajes = [
            'CONFLICTO_CONCURRENTE' => 'Otra persona registró este código o versión al mismo tiempo. Intente de nuevo.',
            'SOLAPE_VIGENCIA' => 'Las vigencias de este código se cruzarían',
            'DUPLICADO' => 'Ya existe un medicamento equivalente vigente en esas fechas',
        ];
        foreach ($reglas as $codigo => $viola) {
            if (array_filter($mismos, $viola) !== []) {
                throw new ErrorReglaNegocio($codigo, $mensajes[$codigo]);
            }
        }
    }
}
