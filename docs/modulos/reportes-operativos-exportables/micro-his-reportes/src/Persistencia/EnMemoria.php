<?php
declare(strict_types=1);

// Adaptador en memoria: mismo contrato que ReportesPdo, sin base. Sirve para pruebas rápidas, demostración y como doble que falla.
namespace Reportes\Persistencia;

use Reportes\Aplicacion\{ErrorPersistencia, FuenteReportes, RepositorioExportaciones};
use Reportes\Dominio\Filtros;

final class EnMemoria implements RepositorioExportaciones, FuenteReportes {
    public bool $fallarAlGuardar = false;
    private array $exportaciones = [];

    /** @param array<string, array<int, array<string, string>>> $vistas vista => filas (hospital_id, fecha, registrado_en y las columnas del reporte) */
    public function __construct(private array $vistas = []) {}

    /** Los mismos datos de demostración que sql/esquema.sql. */
    public static function demo(): self {
        $censo = ['hospital_id', 'paciente', 'documento', 'servicio', 'cama', 'fecha', 'registrado_en'];
        $egresos = ['hospital_id', 'paciente', 'documento', 'tipo_alta', 'fecha', 'registrado_en'];
        return new self([
            'vw_censo_servicio' => array_map(fn ($f) => array_combine($censo, $f), [['hosp-1', 'Luis Pérez', '2222', 'cirugia', 'B2', '2026-10-21', '2026-10-21 09:30:00'],
                ['hosp-1', 'Ana López', '1111', 'medicina', 'A1', '2026-10-20', '2026-10-20 08:00:00'], ['hosp-2', 'Otra Persona', '9999', 'medicina', 'C3', '2026-10-21', '2026-10-21 10:00:00']]),
            'vw_egresos' => [array_combine($egresos, ['hosp-1', 'Rosa Díaz', '3333', 'medica', '2026-10-22', '2026-10-22 11:00:00'])]]);
    }

    public function filas(string $vista, string $hospitalId, Filtros $f, string $corte): array {
        return array_values(array_filter($this->vistas[$vista] ?? [], fn ($x) => $x['hospital_id'] === $hospitalId && $x['fecha'] >= $f->desde
            && $x['fecha'] <= $f->hasta && $x['registrado_en'] <= $corte && array_diff_assoc($f->extras, $x) === []));
    }

    public function guardarExportacion(array $e): string {
        if ($this->fallarAlGuardar) { throw new ErrorPersistencia('Falla simulada al guardar'); }
        $id = 'exp-' . bin2hex(random_bytes(6));
        $this->exportaciones[$id] = $e;
        return $id;
    }

    public function exportacion(string $id, string $hospitalId): ?array {
        $e = $this->exportaciones[$id] ?? null;
        return ($e !== null && $e['hospital_id'] === $hospitalId) ? $e : null;
    }

    public function cantidad(): int { return count($this->exportaciones); }
}
