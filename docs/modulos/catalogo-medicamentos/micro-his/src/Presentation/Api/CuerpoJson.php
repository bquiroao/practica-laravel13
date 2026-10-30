<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

use Catalogo\Presentation\Web\Peticion;

/** Lee el cuerpo JSON: 415 si no es JSON, 400 si está mal formado o no es un objeto. */
final class CuerpoJson
{
    /** @return array<string, mixed> */
    public static function leer(Peticion $p): array
    {
        if (!str_starts_with(strtolower($p->cabeceras['content-type'] ?? ''), 'application/json')) {
            throw new ErrorHttp(415, 'TIPO_NO_SOPORTADO', 'Envíe el cuerpo como application/json');
        }
        try {
            $datos = json_decode($p->crudo, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ErrorHttp(400, 'CUERPO_INVALIDO', 'El cuerpo no es JSON válido');
        }
        if (!is_array($datos) || array_is_list($datos) && $datos !== []) {
            throw new ErrorHttp(400, 'CUERPO_INVALIDO', 'El cuerpo debe ser un objeto JSON');
        }
        unset($datos['hospital_id'], $datos['usuario']); // el tenant y el autor salen del token (RNF-02)
        return $datos;
    }
}
