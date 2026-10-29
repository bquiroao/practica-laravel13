<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Web;

use Alergias\Aplicacion\SolicitudRegistro;

/** Solo formato: convierte el POST en una solicitud. Las reglas las decide el Dominio. */
final class FormularioAlergia
{
    /** @param array<string, string> $post */
    public static function solicitud(string $codigoPaciente, array $post, string $usuarioId): SolicitudRegistro
    {
        $texto = static fn (string $campo): ?string => isset($post[$campo]) && $post[$campo] !== '' ? (string) $post[$campo] : null;
        return new SolicitudRegistro(
            $codigoPaciente, $texto('codigo'), $texto('alergeno'), $texto('tipo') ?? 'allergy',
            $texto('severidad') ?? '', $texto('reaccion'), $texto('inicio'), $usuarioId,
        );
    }
}
