<?php
declare(strict_types=1);

// Semana 2 — DESPUÉS: el exportador está cerrado a cambios y abierto a extensión (OCP).
// Reportes y formatos nuevos se agregan como objetos; las reglas comunes (filtros, corte, orden, enmascarado, hash) viven una sola vez.
namespace Reportes\Despues;

final class ErrorFiltro extends \DomainException {
    public function __construct(public readonly string $campo, string $mensaje) { parent::__construct($mensaje); }
}

/** Punto de extensión 1: qué datos trae un reporte. */
interface DefinicionReporte {
    public function codigo(): string;
    public function fuente(): string;
    /** @return array<string, bool> columna => ¿es dato personal? */
    public function columnas(): array;
    /** @return array<string, string[]> filtros opcionales permitidos => valores válidos */
    public function filtros(): array;
}

/** Punto de extensión 2: cómo se escribe el archivo. */
interface FormatoExportacion {
    public function codigo(): string;
    public function generar(array $columnas, array $filas): string;
}

final class ReporteTabular implements DefinicionReporte {
    public function __construct(private string $codigo, private string $fuente, private array $columnas, private array $filtros = []) {}
    public function codigo(): string { return $this->codigo; }
    public function fuente(): string { return $this->fuente; }
    public function columnas(): array { return $this->columnas; }
    public function filtros(): array { return $this->filtros; }
}

final class Csv implements FormatoExportacion {
    public function codigo(): string { return 'csv'; }
    public function generar(array $columnas, array $filas): string { return implode("\n", array_map(fn ($f) => implode(',', $f), array_merge([$columnas], $filas))); }
}

final class Json implements FormatoExportacion {
    public function codigo(): string { return 'json'; }
    public function generar(array $columnas, array $filas): string { return (string) json_encode(['columnas' => $columnas, 'filas' => $filas], JSON_UNESCAPED_UNICODE); }
}

/** Núcleo cerrado: no conoce ningún reporte ni formato concreto. */
final class ExportadorReproducible {
    public array $registro = [];
    private array $reportes = [];
    private array $formatos = [];

    public function __construct(private array $fuentes, private \Closure $reloj) {}

    public function registrarReporte(DefinicionReporte $r): void { $this->reportes[$r->codigo()] = $r; }
    public function registrarFormato(FormatoExportacion $f): void { $this->formatos[$f->codigo()] = $f; }
    public function agregarFuente(string $nombre, array $filas): void { $this->fuentes[$nombre] = $filas; }

    public function exportar(string $codigo, array $filtros, string $formato, bool $verDatosPersonales): array {
        $reporte = $this->reportes[$codigo] ?? throw new \DomainException('reporte_no_encontrado');
        $fmt = $this->formatos[$formato] ?? throw new \DomainException('formato_no_soportado');
        $filtros = $this->normalizar($reporte, $filtros);
        $corte = ($this->reloj)();
        $archivo = $this->generar($reporte, $filtros, $corte, $fmt, $verDatosPersonales);
        $id = 'exp-' . (count($this->registro) + 1);
        $this->registro[$id] = compact('codigo', 'filtros', 'formato', 'corte', 'verDatosPersonales') + ['hash' => $archivo['hash']];
        return ['id' => $id] + $archivo;
    }

    public function reproducir(string $id): bool {
        $r = $this->registro[$id];
        return $this->generar($this->reportes[$r['codigo']], $r['filtros'], $r['corte'], $this->formatos[$r['formato']], $r['verDatosPersonales'])['hash'] === $r['hash'];
    }

    /** Reglas comunes de filtros: rango obligatorio, desde ≤ hasta, máximo 92 días, sin fechas futuras, solo filtros del reporte. */
    private function normalizar(DefinicionReporte $r, array $filtros): array {
        $f = array_change_key_case(array_map(fn ($v) => strtolower(trim((string) $v)), $filtros));
        foreach (['desde', 'hasta'] as $c) { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$c] ?? '')) { throw new ErrorFiltro($c, 'Fecha obligatoria AAAA-MM-DD'); } }
        if ($f['desde'] > $f['hasta']) { throw new ErrorFiltro('desde', 'desde debe ser menor o igual a hasta'); }
        if ((new \DateTimeImmutable($f['desde']))->diff(new \DateTimeImmutable($f['hasta']))->days > 92) { throw new ErrorFiltro('hasta', 'Máximo 92 días'); }
        if ($f['hasta'] > substr(($this->reloj)(), 0, 10)) { throw new ErrorFiltro('hasta', 'No se aceptan fechas futuras'); }
        foreach (array_diff_key($f, ['desde' => 1, 'hasta' => 1]) as $campo => $valor) {
            if (!in_array($valor, $r->filtros()[$campo] ?? [], true)) { throw new ErrorFiltro($campo, 'Filtro no permitido para este reporte'); }
        }
        ksort($f);
        return $f;
    }

    /** Corte, orden estable, enmascarado y hash: se aplican igual a cualquier reporte y formato. */
    private function generar(DefinicionReporte $r, array $f, string $corte, FormatoExportacion $fmt, bool $verDatosPersonales): array {
        $filas = array_filter($this->fuentes[$r->fuente()], fn ($x) => $x['registrado_en'] <= $corte && $x['fecha'] >= $f['desde'] && $x['fecha'] <= $f['hasta']
            && !array_diff_assoc(array_diff_key($f, ['desde' => 1, 'hasta' => 1]), $x));
        $columnas = array_keys($r->columnas());
        $filas = array_map(fn ($x) => array_map(fn ($c) => (!$verDatosPersonales && $r->columnas()[$c]) ? '***' : $x[$c], $columnas), $filas);
        usort($filas, fn ($a, $b) => $a <=> $b);
        $contenido = $fmt->generar($columnas, $filas);
        return ['contenido' => $contenido, 'hash' => hash('sha256', $contenido), 'filas' => count($filas)];
    }
}

function fuentesEjemplo(): array {
    $r = fn (string $d, string $h = '08:00') => "{$d}T$h";
    return [
        'censo' => [['paciente' => 'Ana López', 'documento' => '1111', 'servicio' => 'medicina', 'cama' => 'A1', 'fecha' => '2026-10-20', 'registrado_en' => $r('2026-10-20')],
                    ['paciente' => 'Luis Pérez', 'documento' => '2222', 'servicio' => 'cirugia', 'cama' => 'B2', 'fecha' => '2026-10-21', 'registrado_en' => $r('2026-10-21')]],
        'egresos' => [['paciente' => 'Rosa Díaz', 'documento' => '3333', 'tipo_alta' => 'medica', 'fecha' => '2026-10-22', 'registrado_en' => $r('2026-10-22')]],
        'citas' => [['paciente' => 'Juan Cruz', 'documento' => '4444', 'especialidad' => 'pediatria', 'estado' => 'atendida', 'fecha' => '2026-10-23', 'registrado_en' => $r('2026-10-23')]],
    ];
}

function exportadorEstandar(\Closure $reloj): ExportadorReproducible {
    $e = new ExportadorReproducible(fuentesEjemplo(), $reloj);
    $e->registrarReporte(new ReporteTabular('censo', 'censo', ['paciente' => true, 'documento' => true, 'servicio' => false, 'cama' => false, 'fecha' => false], ['servicio' => ['medicina', 'cirugia']]));
    $e->registrarReporte(new ReporteTabular('egresos', 'egresos', ['paciente' => true, 'documento' => true, 'tipo_alta' => false, 'fecha' => false], ['tipo_alta' => ['medica', 'voluntaria', 'referencia', 'defuncion']]));
    $e->registrarReporte(new ReporteTabular('citas', 'citas', ['paciente' => true, 'documento' => true, 'especialidad' => false, 'estado' => false, 'fecha' => false], ['especialidad' => ['pediatria', 'medicina']]));
    $e->registrarFormato(new Csv());
    $e->registrarFormato(new Json());
    return $e;
}
