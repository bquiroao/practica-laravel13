<?php
declare(strict_types=1);

// DESPUÉS (semana 7): el camino «filtros → filas → archivo» existe UNA sola vez (PreparadorDeArchivo). Exportar y reproducir lo usan igual,
// así que no pueden diferir: lo que se reproduce se arma exactamente como se armó al exportar.
namespace Reportes\Refactor;

use Reportes\Aplicacion\{FuenteReportes, RepositorioExportaciones, Usuario};
use Reportes\Dominio\{Archivo, ErrorDominio, Filtros};

/** Lo que devuelve el preparador: el archivo y los filtros ya normalizados (que es lo que se registra). */
final class Preparado { public function __construct(public readonly Archivo $archivo, public readonly Filtros $filtros) {} }

final class PreparadorDeArchivo {
    /** @param \Closure|null $posproceso fn(array $filas): array — punto único para una regla nueva de filas; vale para exportar y reproducir */
    public function __construct(private FuenteReportes $fuente, private array $catalogo, private array $formatos, private ?\Closure $posproceso = null) {}

    public function preparar(string $codigo, array $filtros, string $formato, string $hospitalId, bool $verDatosPersonales, string $corte): Preparado {
        $reporte = $this->catalogo[$codigo] ?? throw new ErrorDominio('reporte_no_encontrado', 'Reporte inexistente');
        $fmt = $this->formatos[$formato] ?? throw new ErrorDominio('formato_no_soportado', 'Formato no disponible');
        $f = Filtros::desde($filtros, $reporte, substr($corte, 0, 10));
        $filas = $this->fuente->filas($reporte->vista, $hospitalId, $f, $corte);
        if ($this->posproceso !== null) { $filas = ($this->posproceso)($filas); }
        return new Preparado(Archivo::generar($reporte, $filas, $fmt, $verDatosPersonales), $f);
    }
}

final class ExportarReporteDespues {
    private \Closure $reloj;

    public function __construct(private RepositorioExportaciones $exportaciones, private PreparadorDeArchivo $preparador, ?\Closure $reloj = null) {
        $this->reloj = $reloj ?? fn () => date('Y-m-d H:i:s');
    }

    public function ejecutar(string $codigo, array $filtros, string $formato, Usuario $usuario): array {
        if (!in_array($usuario->rol, ['jefatura', 'administracion', 'auditor'], true)) { throw new ErrorDominio('acceso_denegado', 'Rol sin permiso para reportes'); }
        $corte = ($this->reloj)();
        $p = $this->preparador->preparar($codigo, $filtros, $formato, $usuario->hospitalId, $usuario->verDatosPersonales, $corte);
        $id = $this->exportaciones->guardarExportacion(['codigo' => $codigo, 'filtros' => $p->filtros->comoArreglo(), 'formato' => $formato, 'corte' => $corte, 'filas' => $p->archivo->filas,
            'hash' => $p->archivo->hash, 'usuario_id' => $usuario->id, 'hospital_id' => $usuario->hospitalId, 'datos_personales' => $usuario->verDatosPersonales]);
        return ['id' => $id, 'hash' => $p->archivo->hash, 'filas' => $p->archivo->filas, 'contenido' => $p->archivo->contenido];
    }

    public function reproducir(string $id, Usuario $usuario): array {
        $e = $this->exportaciones->exportacion($id, $usuario->hospitalId) ?? throw new ErrorDominio('exportacion_no_encontrada', 'Exportación inexistente');
        $p = $this->preparador->preparar($e['codigo'], $e['filtros'], $e['formato'], $usuario->hospitalId, (bool) $e['datos_personales'], $e['corte']);
        return ['id' => $id, 'coincide' => hash_equals($e['hash'], $p->archivo->hash), 'hash' => $p->archivo->hash];
    }
}
