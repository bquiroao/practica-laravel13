<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Cliente\RespuestaHttp;
use Rbac\Cliente\Transporte;
use Rbac\Presentacion\Http\Kernel;
use Rbac\Presentacion\Http\Solicitud;

/**
 * Transporte que entrega la petición al Kernel del servidor dentro del mismo proceso:
 * prueba cliente ↔ servidor por el contrato HTTP sin levantar red.
 */
final class TransporteEnProceso implements Transporte
{
    /** @var list<array{metodo: string, url: string, cabeceras: array<string, string>}> */
    public array $enviadas = [];

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function enviar(string $metodo, string $url, array $cabeceras, ?string $cuerpo, float $timeoutSegundos): RespuestaHttp
    {
        $this->enviadas[] = ['metodo' => $metodo, 'url' => $url, 'cabeceras' => $cabeceras];
        $datos = $cuerpo === null ? null : json_decode($cuerpo, true);

        $respuesta = $this->kernel->manejar(new Solicitud(
            $metodo,
            (string) parse_url($url, PHP_URL_PATH),
            $cabeceras,
            is_array($datos) ? $datos : null,
            '10.20.5.99',
        ));

        return new RespuestaHttp(
            $respuesta->estado,
            (string) json_encode($respuesta->cuerpo),
            array_change_key_case($respuesta->cabeceras, CASE_LOWER),
        );
    }
}
