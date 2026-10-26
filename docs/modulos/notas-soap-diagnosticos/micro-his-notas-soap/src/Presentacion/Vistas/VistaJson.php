<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Vistas;

use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Aplicacion\Resultados\ResumenNotaResultado;
use NotasSoap\Presentacion\Http\Respuesta;

/** Representación JSON: la usan el frontend del HIS, curl y Postman. */
final class VistaJson implements Vista
{
    public function nota(NotaResultado $nota, int $estado): Respuesta
    {
        $r = Respuesta::json($estado, $nota->comoArreglo());

        // Semana 5: el cliente sabe que su reintento no creó otra nota.
        return $nota->repetida ? $r->conCabecera('Idempotent-Replayed', 'true') : $r;
    }

    public function lista(string $expedienteId, array $resumenes, int $estado): Respuesta
    {
        return Respuesta::json($estado, [
            'medical_record_id' => $expedienteId,
            'items' => array_map(static fn (ResumenNotaResultado $r): array => $r->comoArreglo(), $resumenes),
        ]);
    }

    public function error(int $estado, string $codigo, string $mensaje): Respuesta
    {
        return Respuesta::error($estado, $codigo, $mensaje);
    }
}
