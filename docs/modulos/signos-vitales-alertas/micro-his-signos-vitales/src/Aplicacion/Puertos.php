<?php
declare(strict_types=1);

// Límite de la Aplicación (semana 4): lo que el caso de uso necesita, sin saber si detrás hay PDO o memoria.
namespace SignosVitales\Aplicacion;

use SignosVitales\Dominio\{RangoSigno, Toma};

/** Repository del agregado Toma (datos propios del módulo). Guarda todo o nada; si falla lanza ErrorPersistencia. */
interface RepositorioTomas {
    public function guardar(Toma $toma): void;
    /** @return array<int, array{toma_id: string, tomada_en: string, autor_id: string, mediciones: array}> más reciente primero */
    public function deEpisodio(string $episodioId): array;
}

/** Datos que el módulo solo lee: episodio y paciente (otros módulos) y catálogo de rangos (compartido). */
interface FuenteClinica {
    /** @return array{hospital_id: string, fecha_nacimiento: string}|null null si no existe o no está activo */
    public function episodioActivo(string $pacienteId, string $episodioId): ?array;
    /** @return array<string, RangoSigno> vacío si no hay rango para esa edad */
    public function rangosPara(int $edad): array;
}
