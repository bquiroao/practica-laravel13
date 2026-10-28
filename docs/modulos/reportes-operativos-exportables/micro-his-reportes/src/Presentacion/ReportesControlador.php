<?php
declare(strict_types=1);

// Presentación: traduce HTTP ↔ casos de uso. No tiene SQL ni reglas de reportes.
namespace Reportes\Presentacion;

use Reportes\Aplicacion\{ErrorPersistencia, ExportarReporte, Usuario};
use Reportes\Dominio\ErrorDominio;

final class ReportesControlador {
    private const ESTADOS = ['formato_no_soportado' => 400, 'acceso_denegado' => 403, 'reporte_no_encontrado' => 404, 'exportacion_no_encontrada' => 404, 'filtro_invalido' => 422];

    /** @param array<string, Usuario> $usuarios identidades de demostración mientras no se integre RBAC (no son credenciales) */
    public function __construct(private ExportarReporte $casos, private array $usuarios) {}

    /** @return array{0: int, 1: array|string, 2: array<string, string>} estado, cuerpo (JSON o archivo) y cabeceras */
    public function atender(string $metodo, string $ruta, string $usuarioDemo, array $consulta): array {
        $exportar = preg_match('#^/api/v1/reportes/(\w+)/exportar$#', $ruta, $r);
        $reproducir = preg_match('#^/api/v1/exportaciones/([\w-]+)/reproducir$#', $ruta, $x);
        if (!$exportar && !$reproducir) { return [404, ['codigo' => 'ruta_no_encontrada'], []]; }
        if ($metodo !== ($exportar ? 'GET' : 'POST')) { return [405, ['codigo' => 'metodo_no_permitido'], []]; }
        $usuario = $this->usuarios[$usuarioDemo] ?? null;
        if ($usuario === null) { return [401, ['codigo' => 'sin_sesion'], []]; }
        try {
            if ($reproducir) { return [200, $this->casos->reproducir($x[1], $usuario), []]; }
            $e = $this->casos->ejecutar($r[1], array_diff_key($consulta, ['formato' => 1]), (string) ($consulta['formato'] ?? 'csv'), $usuario);
            return [200, $e['contenido'], ['Content-Type' => 'text/csv; charset=utf-8', 'X-Exportacion-Id' => $e['id'], 'X-Reporte-Sha256' => $e['hash']]];
        } catch (ErrorDominio $e) {
            return [self::ESTADOS[$e->codigo] ?? 422, array_filter(['codigo' => $e->codigo, 'mensaje' => $e->getMessage(), 'campo' => $e->campo]), []];
        } catch (ErrorPersistencia) {
            return [500, ['codigo' => 'error_persistencia', 'mensaje' => 'No se pudo generar el reporte; no se entregó ningún archivo'], []];
        }
    }
}
