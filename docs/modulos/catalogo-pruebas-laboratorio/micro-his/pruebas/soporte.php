<?php
declare(strict_types=1);

use Lab\Application\Reloj;
use Lab\Domain\ErrorPersistencia;
use Lab\Domain\LabTest;
use Lab\Domain\LabTestRepository;

/** Doble: reloj fijo en 2027-01-02 para que las fechas de los ejemplos sean válidas. */
final class RelojFijo implements Reloj
{
    public function __construct(private readonly string $momento = '2027-01-02 08:00:00')
    {
    }

    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->momento);
    }
}

/** Doble espía: guarda en un arreglo y registra cada llamada a guardarNuevaVersion. */
final class RepositorioEspia implements LabTestRepository
{
    /** @var LabTest[] */
    public array $filas = [];
    public int $guardados = 0;

    public function ultimaVersion(string $tenantId, string $codigo): ?LabTest
    {
        $versiones = array_filter($this->filas, fn (LabTest $p) => $p->tenantId === $tenantId && $p->codigo === $codigo);
        usort($versiones, fn (LabTest $a, LabTest $b) => $b->version <=> $a->version);
        return $versiones[0] ?? null;
    }

    public function guardarNuevaVersion(LabTest $nueva, ?LabTest $anteriorCerrada): void
    {
        $this->guardados++;
        if ($anteriorCerrada !== null) {
            $this->filas[$anteriorCerrada->id] = $anteriorCerrada;
        }
        $this->filas[$nueva->id] = $nueva;
    }

    public function buscarVigentes(string $tenantId, DateTimeImmutable $fecha, string $texto = '', ?string $seccion = null, ?string $tipoMuestra = null): array
    {
        return array_values(array_filter($this->filas, fn (LabTest $p) => $p->tenantId === $tenantId && $p->vigencia->incluye($fecha)));
    }

    public function buscarVigentePorCodigo(string $tenantId, string $codigo, DateTimeImmutable $fecha): ?LabTest
    {
        foreach ($this->buscarVigentes($tenantId, $fecha) as $p) {
            if ($p->codigo === $codigo) {
                return $p;
            }
        }
        return null;
    }
}

/** Doble que simula la caída de la base al guardar (RN-07). */
final class RepositorioQueFalla implements LabTestRepository
{
    public function ultimaVersion(string $tenantId, string $codigo): ?LabTest
    {
        return null;
    }

    public function guardarNuevaVersion(LabTest $nueva, ?LabTest $anteriorCerrada): void
    {
        throw new ErrorPersistencia('No se pudo guardar la prueba; no se guardó ningún cambio.');
    }

    public function buscarVigentes(string $tenantId, DateTimeImmutable $fecha, string $texto = '', ?string $seccion = null, ?string $tipoMuestra = null): array
    {
        return [];
    }

    public function buscarVigentePorCodigo(string $tenantId, string $codigo, DateTimeImmutable $fecha): ?LabTest
    {
        return null;
    }
}

/** Datos ficticios de datos/<archivo>.json con cambios opcionales. */
function datos(string $archivo, array $cambios = []): array
{
    $base = json_decode((string) file_get_contents(__DIR__ . "/../datos/$archivo.json"), true);
    return $cambios + $base;
}

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

/** Ejecuta $accion y exige que lance $clase; devuelve la excepción. */
function esperarExcepcion(string $clase, callable $accion): Throwable
{
    try {
        $accion();
    } catch (Throwable $e) {
        afirmar($e instanceof $clase, "Se esperaba $clase y llegó " . get_class($e) . ': ' . $e->getMessage());
        return $e;
    }
    throw new RuntimeException("Se esperaba $clase y no se lanzó ninguna excepción");
}
