<?php
declare(strict_types=1);

// Dominio: reglas que hacen reproducible y segura una exportación. No conoce HTTP, SQL ni casos de uso (diseño OCP de la semana 2).
namespace Reportes\Dominio;

final class ErrorDominio extends \DomainException {
    public function __construct(public readonly string $codigo, string $mensaje, public readonly ?string $campo = null) { parent::__construct($mensaje); }
}

/** Qué trae un reporte. La vista la fija el catálogo, nunca el usuario. columnas: columna => ¿dato personal?; filtros: filtro => valores válidos. */
final class DefinicionReporte {
    public function __construct(public readonly string $codigo, public readonly string $vista, public readonly array $columnas, public readonly array $filtros = []) {}
}

/** Filtros normalizados (minúsculas, sin espacios, claves ordenadas) y validados: rango obligatorio, desde ≤ hasta, máx. 92 días, sin futuro. */
final class Filtros {
    private function __construct(public readonly string $desde, public readonly string $hasta, public readonly array $extras) {}

    public static function desde(array $crudos, DefinicionReporte $r, string $hoy): self {
        $f = array_change_key_case(array_map(fn ($v) => strtolower(trim((string) $v)), $crudos));
        foreach (['desde', 'hasta'] as $c) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$c] ?? '')) { throw new ErrorDominio('filtro_invalido', 'Fecha obligatoria AAAA-MM-DD', $c); }
        }
        if ($f['desde'] > $f['hasta']) { throw new ErrorDominio('filtro_invalido', 'desde debe ser menor o igual a hasta', 'desde'); }
        if ((new \DateTimeImmutable($f['desde']))->diff(new \DateTimeImmutable($f['hasta']))->days > 92) { throw new ErrorDominio('filtro_invalido', 'Máximo 92 días', 'hasta'); }
        if ($f['hasta'] > $hoy) { throw new ErrorDominio('filtro_invalido', 'No se aceptan fechas futuras', 'hasta'); }
        $extras = array_diff_key($f, ['desde' => 1, 'hasta' => 1]);
        foreach ($extras as $campo => $valor) {
            if (!in_array($valor, $r->filtros[$campo] ?? [], true)) { throw new ErrorDominio('filtro_invalido', 'Filtro no permitido para este reporte', $campo); }
        }
        ksort($extras);
        return new self($f['desde'], $f['hasta'], $extras);
    }

    public function comoArreglo(): array { return ['desde' => $this->desde, 'hasta' => $this->hasta] + $this->extras; }
}

interface FormatoExportacion {
    public function codigo(): string;
    public function generar(array $columnas, array $filas): string;
}

final class Csv implements FormatoExportacion {
    public function codigo(): string { return 'csv'; }
    public function generar(array $columnas, array $filas): string {
        $celda = fn (string $v) => preg_match('/[",\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
        return implode("\n", array_map(fn ($f) => implode(',', array_map($celda, $f)), array_merge([$columnas], $filas)));
    }
}

/** Columnas del catálogo, enmascarado, orden estable y hash: iguales para cualquier reporte y formato. */
final class Archivo {
    private function __construct(public readonly string $contenido, public readonly string $hash, public readonly int $filas) {}

    public static function generar(DefinicionReporte $r, array $filas, FormatoExportacion $formato, bool $verDatosPersonales): self {
        $columnas = array_keys($r->columnas);
        $datos = array_map(fn ($x) => array_map(fn ($c) => (!$verDatosPersonales && $r->columnas[$c]) ? '***' : (string) $x[$c], $columnas), $filas);
        usort($datos, fn ($a, $b) => $a <=> $b);
        $contenido = $formato->generar($columnas, $datos);
        return new self($contenido, hash('sha256', $contenido), count($datos));
    }
}

/** @return array<string, DefinicionReporte> */
function catalogo(): array {
    return [
        'censo' => new DefinicionReporte('censo', 'vw_censo_servicio', ['paciente' => true, 'documento' => true, 'servicio' => false, 'cama' => false, 'fecha' => false], ['servicio' => ['medicina', 'cirugia', 'pediatria']]),
        'egresos' => new DefinicionReporte('egresos', 'vw_egresos', ['paciente' => true, 'documento' => true, 'tipo_alta' => false, 'fecha' => false], ['tipo_alta' => ['medica', 'voluntaria', 'referencia', 'defuncion']]),
    ];
}
