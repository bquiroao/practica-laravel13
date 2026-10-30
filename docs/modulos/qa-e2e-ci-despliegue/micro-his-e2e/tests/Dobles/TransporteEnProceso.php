<?php

declare(strict_types=1);

use E2e\Cliente\FallaTransporte;
use E2e\Cliente\Transporte;
use E2e\Presentacion\Http\Kernel;
use E2e\Presentacion\Http\Solicitud;

/**
 * Transporte que pasa por el kernel REAL en proceso, con fallas programables:
 *   · caidas: las próximas N peticiones no llegan (red caída);
 *   · perdidas: las próximas N llegan y se procesan, pero la respuesta se pierde (el caso más traicionero).
 */
final class TransporteEnProceso implements Transporte
{
    public int $caidas = 0;
    public int $perdidas = 0;
    /** @var list<array{metodo: string, ruta: string, cabeceras: array<string, string>}> */
    public array $enviadas = [];

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function enviar(string $metodo, string $ruta, array $cabeceras, string $cuerpo): array
    {
        $this->enviadas[] = compact('metodo', 'ruta', 'cabeceras');
        if ($this->caidas > 0) {
            $this->caidas--;
            throw new FallaTransporte('red caída (doble)');
        }
        $r = $this->kernel->manejar(new Solicitud($metodo, $ruta, array_change_key_case($cabeceras, CASE_LOWER), $cuerpo));
        if ($this->perdidas > 0) {
            $this->perdidas--;
            throw new FallaTransporte('respuesta perdida (doble)');
        }

        return ['estado' => $r->estado, 'cuerpo' => $r->cuerpo];
    }
}
