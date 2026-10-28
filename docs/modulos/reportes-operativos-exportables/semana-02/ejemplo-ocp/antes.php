<?php
declare(strict_types=1);

// Semana 2 — ANTES: un exportador con un switch por reporte y otro por formato.
// Cada reporte nuevo o formato nuevo obliga a editar esta clase, y cada rama repite (o se olvida) las reglas comunes.
namespace Reportes\Antes;

class ExportadorReportes {
    public array $registro = [];

    public function __construct(public array $fuentes, private string $ahora) {}

    public function exportar(string $reporte, array $filtros, string $formato, bool $verDatosPersonales): array {
        $corte = $this->ahora;
        [$desde, $hasta] = [$filtros['desde'] ?? '', $filtros['hasta'] ?? ''];
        switch ($reporte) {
            case 'censo':
                if ($desde > $hasta) { throw new \DomainException('filtro_invalido'); }
                $filas = array_filter($this->fuentes['censo'], fn ($f) => $f['fecha'] >= $desde && $f['fecha'] <= $hasta && $f['registrado_en'] <= $corte
                    && (!isset($filtros['servicio']) || $f['servicio'] === $filtros['servicio']));
                if (!$verDatosPersonales) { $filas = array_map(fn ($f) => ['paciente' => '***', 'documento' => '***'] + $f, $filas); }
                break;
            case 'egresos':   // D3: aquí nadie agregó el enmascarado de datos personales
                if ($desde > $hasta) { throw new \DomainException('filtro_invalido'); }
                $filas = array_filter($this->fuentes['egresos'], fn ($f) => $f['fecha'] >= $desde && $f['fecha'] <= $hasta && $f['registrado_en'] <= $corte);
                break;
            case 'citas':     // D1: aquí se olvidó la fecha de corte, así que la exportación no se puede reproducir
                $filas = array_filter($this->fuentes['citas'], fn ($f) => $f['fecha'] >= $desde && $f['fecha'] <= $hasta
                    && (!isset($filtros['especialidad']) || $f['especialidad'] === $filtros['especialidad']));
                break;
            default:
                throw new \DomainException('reporte_no_encontrado');
        }
        $filas = array_values(array_map(fn ($f) => array_diff_key($f, ['registrado_en' => 1]), $filas));
        switch ($formato) {   // D2: un formato nuevo (por ejemplo PDF o TSV) exige otra rama aquí
            case 'csv':
                $contenido = implode("\n", array_map(fn ($f) => implode(',', $f), array_merge($filas ? [array_keys($filas[0])] : [], $filas)));
                break;
            case 'json':
                $contenido = json_encode($filas, JSON_UNESCAPED_UNICODE);
                break;
            default:
                throw new \DomainException('formato_no_soportado');
        }
        $id = 'exp-' . (count($this->registro) + 1);
        $this->registro[$id] = compact('reporte', 'filtros', 'formato', 'corte', 'verDatosPersonales') + ['hash' => hash('sha256', $contenido)];
        return ['id' => $id, 'hash' => $this->registro[$id]['hash'], 'contenido' => $contenido];
    }

    public function reproducir(string $id): bool {
        $r = $this->registro[$id];
        $ahora = $this->ahora;
        $this->ahora = $r['corte'];
        $copia = $this->exportar($r['reporte'], $r['filtros'], $r['formato'], $r['verDatosPersonales']);
        $this->ahora = $ahora;
        return $copia['hash'] === $r['hash'];
    }
}
