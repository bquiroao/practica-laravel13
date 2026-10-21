<?php
declare(strict_types=1);

namespace MicroHis\Presentation;

use MicroHis\Application\ResultadoConsulta;

/** Muestra el resultado en HTML. No calcula ni consulta datos: solo presenta. */
final class VistaIndicador
{
    /** @param array<string,string> $consulta parámetros recibidos, para armar los enlaces al siguiente nivel */
    public function html(ResultadoConsulta $r, array $consulta): string
    {
        $cuerpo = match ($r->estado) {
            'exito' => $this->indicador($r) . $this->desglose($r, $consulta) . $this->registros($r, $consulta),
            'sin_datos' => '<p class="aviso">Sin datos: ' . $this->e($r->mensaje) . '</p>',
            default => "<p class=\"error\" role=\"alert\">Error {$r->codigoHttp}: " . $this->e($r->mensaje) . '</p>',
        };
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Micro-HIS · Reportes y analytics básicos</title>'
            . '<style>body{font-family:system-ui;margin:2rem;max-width:60rem}table{border-collapse:collapse}td,th{border:1px solid #999;padding:.3rem .6rem}'
            . '.valor{font-size:2.5rem;font-weight:bold}.error{color:#a00}.aviso{color:#865}</style></head><body>'
            . '<h1>Reportes y analytics básicos</h1>' . $cuerpo . '</body></html>';
    }

    private function indicador(ResultadoConsulta $r): string
    {
        $i = $r->indicador;
        $ficha = '';
        foreach ($i->trazabilidad as $campo => $valor) {
            $ficha .= '<tr><th>' . $this->e($campo) . '</th><td>' . $this->e((string) $valor) . '</td></tr>';
        }
        return '<h2>Nivel 1 · ' . $this->e($i->nombre) . '</h2><p class="valor">' . $i->valor . ' ' . $this->e($i->unidad) . '</p>'
            . "<h3>Ficha de trazabilidad</h3><table>{$ficha}</table>";
    }

    private function desglose(ResultadoConsulta $r, array $consulta): string
    {
        if ($r->desglose === null) {
            return '<p><a href="?' . $this->e(http_build_query(['nivel' => 'desglose'] + $consulta)) . '">Ver desglose</a></p>';
        }
        $filas = '';
        foreach ($r->desglose->partes as $parte => $valor) {
            $enlace = '?' . http_build_query(['nivel' => 'registros', 'parte' => $parte] + $consulta);
            $filas .= '<tr><td><a href="' . $this->e($enlace) . '">' . $this->e($parte) . "</a></td><td>{$valor}</td></tr>";
        }
        return '<h2>Nivel 2 · Desglose por ' . $this->e($r->desglose->dimension) . '</h2>'
            . "<table><tr><th>Parte</th><th>Valor</th></tr>{$filas}<tr><th>Total</th><th>{$r->desglose->total()}</th></tr></table>";
    }

    private function registros(ResultadoConsulta $r, array $consulta): string
    {
        if ($r->registros === null) {
            return '';
        }
        $filas = '';
        foreach ($r->registros as $reg) {
            $filas .= '<tr><td>' . $this->e($reg->cama) . '</td><td>' . $this->e($reg->paciente) . '</td><td>'
                . $this->e($reg->ingreso) . '</td><td>' . $this->e($reg->egreso ?? 'en curso') . '</td></tr>';
        }
        return '<h2>Nivel 3 · Registros de origen: ' . $this->e($consulta['parte'] ?? '') . ' (' . count($r->registros) . ')</h2>'
            . "<table><tr><th>Cama</th><th>Paciente</th><th>Ingreso</th><th>Egreso</th></tr>{$filas}</table>";
    }

    private function e(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}
