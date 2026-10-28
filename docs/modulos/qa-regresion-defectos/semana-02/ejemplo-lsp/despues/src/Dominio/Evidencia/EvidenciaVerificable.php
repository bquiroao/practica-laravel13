<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/**
 * Contrato que TODO subtipo de evidencia cumple (LSP). Es lo que permite usar una evidencia para probar una FALLA:
 *   - contenido(): los bytes capturados al crearla; no lanza y no cambia nunca;
 *   - huella():    sha256 de exactamente ese contenido;
 *   - tamano():    strlen(contenido());
 *   - clasificacion(): si contiene datos clínicos. Proteger el acceso es tarea de la Aplicación, no del objeto.
 * Las precondiciones (formato válido) se comprueban UNA vez, en el constructor; los métodos no tienen ninguna.
 */
interface EvidenciaVerificable
{
    public function tipo(): string;

    public function contenido(): string;

    public function huella(): string;

    public function tamano(): int;

    public function clasificacion(): Clasificacion;
}
