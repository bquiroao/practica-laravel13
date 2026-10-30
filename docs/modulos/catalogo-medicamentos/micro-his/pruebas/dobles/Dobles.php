<?php
declare(strict_types=1);

use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Modelo\ClaveEquivalencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Puertos\ConsultaUsoPrescripciones;
use Catalogo\Domain\Puertos\GeneradorId;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Domain\Repositorio\RepositorioMedicamentos;

/** Doble de prueba (fake) del repositorio: arreglo en memoria y registro de llamadas. */
final class CatalogoFalso implements RepositorioMedicamentos
{
    /** @var list<Medicamento> */
    public array $versiones = [];
    /** @var list<string> */
    public array $llamadas = [];

    public function buscarVigentes(string $hospitalId, CriterioBusqueda $criterio): array
    {
        $this->llamadas[] = 'buscarVigentes';
        return array_values(array_filter($this->versiones, fn (Medicamento $m) => $m->hospitalId === $hospitalId && $criterio->coincide($m)));
    }

    public function vigentePorCodigo(string $hospitalId, CodigoMedicamento $codigo, DateTimeImmutable $fecha): ?Medicamento
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
        $this->llamadas[] = 'historial';
        return array_values(array_filter($this->versiones, fn (Medicamento $m) => $m->hospitalId === $hospitalId && $m->codigo->igualA($codigo)));
    }

    public function equivalenteVigente(string $hospitalId, ClaveEquivalencia $clave, Vigencia $vigencia, ?CodigoMedicamento $excluir = null): ?CodigoMedicamento
    {
        $this->llamadas[] = 'equivalenteVigente';
        foreach ($this->versiones as $m) {
            if ($m->hospitalId === $hospitalId && $m->clave()->igualA($clave) && $m->vigencia->seCruzaCon($vigencia)
                && ($excluir === null || !$m->codigo->igualA($excluir))) {
                return $m->codigo;
            }
        }
        return null;
    }

    public function siguienteCodigo(string $hospitalId): CodigoMedicamento
    {
        $max = 0;
        foreach ($this->versiones as $m) {
            $max = $m->hospitalId === $hospitalId ? max($max, $m->codigo->numero()) : $max;
        }
        return CodigoMedicamento::desdeNumero($max + 1);
    }

    public function agregar(Medicamento $nuevo): void
    {
        $this->llamadas[] = 'agregar';
        $this->versiones[] = $nuevo;
    }

    public function reemplazarVigente(Medicamento $anteriorCerrada, Medicamento $nueva): void
    {
        $this->llamadas[] = 'reemplazarVigente';
        $this->cerrar($anteriorCerrada);
        $this->versiones[] = $nueva;
    }

    public function cerrar(Medicamento $cerrada): void
    {
        $this->llamadas[] = 'cerrar';
        foreach ($this->versiones as $i => $m) {
            if ($m->id === $cerrada->id) {
                $this->versiones[$i] = $cerrada;
            }
        }
    }

    public function eliminar(string $hospitalId, CodigoMedicamento $codigo): void
    {
        $this->llamadas[] = 'eliminar';
        $this->versiones = array_values(array_filter($this->versiones, fn (Medicamento $m) => !($m->hospitalId === $hospitalId && $m->codigo->igualA($codigo))));
    }
}

/** Doble que simula una base caída en cualquier escritura (error de persistencia). */
final class EscrituraQueFalla implements Catalogo\Domain\Repositorio\EscrituraCatalogo
{
    public function siguienteCodigo(string $hospitalId): CodigoMedicamento
    {
        return CodigoMedicamento::desdeNumero(999);
    }

    public function agregar(Medicamento $nuevo): void
    {
        throw new ErrorPersistencia(new RuntimeException('conexión rechazada (simulada)'));
    }

    public function reemplazarVigente(Medicamento $anteriorCerrada, Medicamento $nueva): void
    {
        $this->agregar($nueva);
    }

    public function cerrar(Medicamento $cerrada): void
    {
        $this->agregar($cerrada);
    }

    public function eliminar(string $hospitalId, CodigoMedicamento $codigo): void
    {
        throw new ErrorPersistencia();
    }
}

final class UsoFalso implements ConsultaUsoPrescripciones
{
    /** @param list<string> $usados códigos usados en prescripciones ficticias */
    public function __construct(public array $usados = [])
    {
    }

    public function fueUsado(string $hospitalId, CodigoMedicamento $codigo): bool
    {
        return in_array($codigo->valor, $this->usados, true);
    }
}

final class RelojFijo implements Reloj
{
    public function __construct(private readonly string $hoy = Fabrica::HOY)
    {
    }

    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->hoy . ' 10:00:00', new DateTimeZone('America/Guatemala'));
    }
}

final class IdsSecuenciales implements GeneradorId
{
    private int $n = 0;

    public function nuevo(): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', ++$this->n);
    }
}
