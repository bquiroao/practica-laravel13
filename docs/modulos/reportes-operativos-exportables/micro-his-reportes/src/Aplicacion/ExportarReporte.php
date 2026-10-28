<?php
declare(strict_types=1);

// Aplicación: orquesta CU-03 (permiso → catálogo → filtros → corte → archivo → registrar) y CU-05 (reproducir). Sin SQL ni HTTP.
namespace Reportes\Aplicacion;

use Reportes\Dominio\{Archivo, ErrorDominio, Filtros};
use Reportes\Persistencia\ReportesPdo;

final class Usuario {
    public function __construct(public readonly string $id, public readonly string $rol, public readonly string $hospitalId, public readonly bool $verDatosPersonales) {}
}

final class ErrorPersistencia extends \RuntimeException {}

final class ExportarReporte {
    private \Closure $reloj;

    // Semana 3: depende directo del adaptador PDO. En la semana 4 se invierte con una interfaz Repository.
    public function __construct(private ReportesPdo $bd, private array $catalogo, private array $formatos, ?\Closure $reloj = null) {
        $this->reloj = $reloj ?? fn () => date('Y-m-d H:i:s');
    }

    public function ejecutar(string $codigo, array $filtros, string $formato, Usuario $usuario): array {
        if (!in_array($usuario->rol, ['jefatura', 'administracion', 'auditor'], true)) { throw new ErrorDominio('acceso_denegado', 'Rol sin permiso para reportes'); }
        $reporte = $this->catalogo[$codigo] ?? throw new ErrorDominio('reporte_no_encontrado', 'Reporte inexistente');
        $fmt = $this->formatos[$formato] ?? throw new ErrorDominio('formato_no_soportado', 'Formato no disponible');
        $corte = ($this->reloj)();
        $f = Filtros::desde($filtros, $reporte, substr($corte, 0, 10));
        $archivo = Archivo::generar($reporte, $this->bd->filas($reporte->vista, $usuario->hospitalId, $f, $corte), $fmt, $usuario->verDatosPersonales);
        $id = $this->bd->guardarExportacion(['codigo' => $codigo, 'filtros' => $f->comoArreglo(), 'formato' => $formato, 'corte' => $corte, 'filas' => $archivo->filas,
            'hash' => $archivo->hash, 'usuario_id' => $usuario->id, 'hospital_id' => $usuario->hospitalId, 'datos_personales' => $usuario->verDatosPersonales]);
        return ['id' => $id, 'hash' => $archivo->hash, 'filas' => $archivo->filas, 'contenido' => $archivo->contenido];   // se registra antes de entregar
    }

    /** CU-05: vuelve a generar con los filtros, el corte y el permiso guardados y compara el hash. */
    public function reproducir(string $id, Usuario $usuario): array {
        $e = $this->bd->exportacion($id, $usuario->hospitalId) ?? throw new ErrorDominio('exportacion_no_encontrada', 'Exportación inexistente');
        $reporte = $this->catalogo[$e['codigo']];
        $f = Filtros::desde($e['filtros'], $reporte, substr($e['corte'], 0, 10));
        $archivo = Archivo::generar($reporte, $this->bd->filas($reporte->vista, $usuario->hospitalId, $f, $e['corte']), $this->formatos[$e['formato']], (bool) $e['datos_personales']);
        return ['id' => $id, 'coincide' => hash_equals($e['hash'], $archivo->hash), 'hash' => $archivo->hash];
    }
}
