<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Aplicacion;

use MicroHis\DashboardOcupacion\Dominio\{Corte, OcupacionSala};

// Puertos por cliente (ISP de la semana 2). Ninguno permite cambiar el estado de una cama (R-09).
/** Camas de las salas activas (R-03), con 'sala', 'cama' (null si la sala no tiene), 'estado' y 'con_estancia'. */
interface FuenteCamas { public function camasDeSalasActivas(): array; }
interface EscritorCortes { public function guardarCorte(Corte $corte): void; }
interface LectorCortes { public function ultimoCorte(): ?Corte; }
/** Interfaz Repository del módulo, en el límite de Aplicación: la unión de los tres puertos de datos. La implementan PDO e InMemory. */
interface RepositorioOcupacion extends FuenteCamas, EscritorCortes, LectorCortes {}
interface Reloj { public function ahora(): int; }

final class ErrorPersistencia extends \RuntimeException {}   // fallo técnico de la base: no es una regla de negocio

/** CU-01 con CU-04: consolida por sala. Si la lectura falla, la excepción sube antes de guardar y el corte anterior queda (R-09). */
final class ConsolidadorOcupacion
{
    public function __construct(private FuenteCamas $fuente, private EscritorCortes $cortes, private Reloj $reloj) {}

    public function consolidar(): Corte
    {
        $porSala = [];
        foreach ($this->fuente->camasDeSalasActivas() as $f) {
            $porSala[$f['sala']] ??= [];
            if ($f['cama'] !== null) $porSala[$f['sala']][] = $f;
        }
        $corte = new Corte($this->reloj->ahora(), array_map([OcupacionSala::class, 'calcular'], $porSala));
        $this->cortes->guardarCorte($corte);
        return $corte;
    }
}

/** CU-02 con CU-03: el último corte, con aviso si es viejo y filtrado por estado; sin corte, el estado vacío. */
final class ConsultaOcupacion
{
    public function __construct(private LectorCortes $cortes, private Reloj $reloj) {}

    public function consultar(?string $estado): array
    {
        $corte = $this->cortes->ultimoCorte();
        if ($corte === null) return ['vacio' => true];
        return ['vacio' => false, 'hora' => $corte->hora, 'aviso_viejo' => $corte->esViejo($this->reloj->ahora()), 'salas' => $corte->filtrar($estado)];
    }
}
