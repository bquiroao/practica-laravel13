<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/**
 * Enlace a algo que vive en otro sistema (un build de CI, un ticket). NO es una evidencia: su contenido puede cambiar
 * y nadie lo puede volver a comprobar. Se adjunta como contexto y nunca cuenta para exigir evidencia (R-05).
 */
final class ReferenciaExterna
{
    public function __construct(public readonly string $url, public readonly string $descripcion)
    {
        if (!preg_match('#^https://\S+$#', $url)) {
            throw new EvidenciaInvalida('URL_INVALIDA');
        }
    }
}
