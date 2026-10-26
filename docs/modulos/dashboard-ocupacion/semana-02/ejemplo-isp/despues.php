<?php
declare(strict_types=1);

// DESPUÉS (ISP): una interfaz por cliente. Ninguna permite escribir camas (R-09).

/** Lectura de wards, beds y bed_stays para el consolidador. Solo lectura. */
interface FuenteCamas
{
    public function salasActivas(): array;
    /** @return array<string,string> cama => estado */
    public function camas(string $sala): array;
    /** @return string[] camas "sala/cama" con estancia abierta */
    public function estanciasAbiertas(): array;
}
interface EscritorCortes { public function guardarCorte(array $corte): void; }
interface LectorCortes { public function ultimoCorte(): ?array; }

/** CU-01 con CU-04: consolida por sala y marca inconsistencias (R-01–R-05, R-09). */
final class ConsolidadorOcupacion
{
    public function __construct(private FuenteCamas $fuente, private EscritorCortes $cortes) {}

    public function consolidar(int $ahora): bool
    {
        try {
            $salas = $this->fuente->salasActivas();
            $abiertas = $this->fuente->estanciasAbiertas();
            $porSala = [];
            foreach ($salas as $sala) {
                $camas = $this->fuente->camas($sala);
                $n = array_count_values($camas) + ['available' => 0, 'occupied' => 0, 'maintenance' => 0, 'inactive' => 0];
                $operativas = $n['available'] + $n['occupied'];
                $inconsistentes = [];
                foreach ($camas as $cama => $estado) {
                    if (($estado === 'occupied') !== in_array("$sala/$cama", $abiertas, true)) $inconsistentes[] = $cama;
                }
                $porSala[$sala] = $n + ['ocupacion' => $operativas === 0 ? 'no aplica' : round(100 * $n['occupied'] / $operativas, 1), 'inconsistentes' => $inconsistentes];
            }
        } catch (RuntimeException) {
            return false;   // E2: falla la lectura; el corte anterior se conserva
        }
        $this->cortes->guardarCorte(['hora' => $ahora, 'salas' => $porSala]);
        return true;
    }
}

/** CU-02 con CU-03: último corte, aviso si es viejo y filtro por estado (R-05–R-07). */
final class ConsultaOcupacion
{
    public function __construct(private LectorCortes $cortes) {}

    public function consultar(int $ahora, ?string $estado = null): array
    {
        $corte = $this->cortes->ultimoCorte();
        if ($corte === null) return ['status' => 200, 'vacio' => true];
        $salas = $estado === null ? $corte['salas'] : array_filter($corte['salas'], fn ($s) => $s[$estado] > 0);
        return ['status' => 200, 'hora' => $corte['hora'], 'aviso_viejo' => $ahora - $corte['hora'] > 15 * 60, 'salas' => $salas];
    }
}

/** Detalles en memoria. La fuente refleja el esquema; el almacén de cortes implementa las dos interfaces de cortes. */
final class FuenteEnMemoria implements FuenteCamas
{
    public bool $falla = false;
    public function __construct(private array $salas, private array $camas, private array $abiertas) {}
    public function salasActivas(): array { if ($this->falla) throw new RuntimeException('sin conexión'); return $this->salas; }
    public function camas(string $sala): array { return $this->camas[$sala] ?? []; }
    public function estanciasAbiertas(): array { return $this->abiertas; }
}

final class AlmacenCortesEnMemoria implements EscritorCortes, LectorCortes
{
    private ?array $ultimo = null;
    public function guardarCorte(array $corte): void { $this->ultimo = $corte; }
    public function ultimoCorte(): ?array { return $this->ultimo; }
}
