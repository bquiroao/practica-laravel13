<?php
declare(strict_types=1);

// Controlador: lee la petición, identifica al usuario, llama al caso de uso y elige la vista. Sin SQL ni reglas de reportes.
namespace Reportes\Presentacion;

use Reportes\Aplicacion\{ErrorPersistencia, ExportarReporte, Usuario};
use Reportes\Dominio\ErrorDominio;

final class ReportesControlador {
    /** @param array<string, Usuario> $usuarios identidades de demostración mientras no se integre RBAC (no son credenciales) */
    public function __construct(private ExportarReporte $exportar, private array $usuarios) {}

    public function exportar(Peticion $p, string $codigo): array {
        return $this->conUsuario($p, fn (Usuario $u) => VistaHttp::csv($this->exportar->ejecutar(
            $codigo, array_diff_key($p->consulta, ['formato' => 1]), (string) ($p->consulta['formato'] ?? 'csv'), $u)));
    }

    public function reproducir(Peticion $p, string $id): array {
        return $this->conUsuario($p, fn (Usuario $u) => VistaHttp::json($this->exportar->reproducir($id, $u)));
    }

    private function conUsuario(Peticion $p, callable $accion): array {
        $usuario = $this->usuarios[$p->usuarioDemo] ?? null;
        if ($usuario === null) { return VistaHttp::error('sin_sesion'); }
        try { return $accion($usuario); }
        catch (ErrorDominio $e) { return VistaHttp::error($e->codigo, $e->getMessage(), $e->campo); }
        catch (ErrorPersistencia) { return VistaHttp::error('error_persistencia', 'No se pudo generar el reporte; no se entregó ningún archivo'); }
    }
}
