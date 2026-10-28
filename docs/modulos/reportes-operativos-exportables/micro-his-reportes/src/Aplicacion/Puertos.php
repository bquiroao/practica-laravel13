<?php
declare(strict_types=1);

// Límite de la Aplicación (semana 4): lo que los casos de uso necesitan, sin saber qué almacén hay detrás.
namespace Reportes\Aplicacion;

use Reportes\Dominio\Filtros;

/** Repository de lo que este módulo posee y escribe: el registro de exportaciones (auditoría y reproducción). Si falla lanza ErrorPersistencia. */
interface RepositorioExportaciones {
    /** @param array{codigo: string, filtros: array, formato: string, corte: string, filas: int, hash: string, usuario_id: string, hospital_id: string, datos_personales: bool} $e @return string id */
    public function guardarExportacion(array $e): string;
    /** @return array<string, mixed>|null null si no existe o es de otro hospital */
    public function exportacion(string $id, string $hospitalId): ?array;
}

/** Datos que otros módulos publican y este solo lee: filas de una vista del catálogo, del hospital, del rango y registradas hasta el corte. */
interface FuenteReportes {
    /** @return array<int, array<string, mixed>> */
    public function filas(string $vista, string $hospitalId, Filtros $f, string $corte): array;
}
