<?php

declare(strict_types=1);

namespace Auditoria\Pruebas\Dobles;

use Auditoria\Cliente\FallaTransporte;
use Auditoria\Cliente\PeticionHttp;
use Auditoria\Cliente\RespuestaHttp;
use Auditoria\Cliente\Transporte;
use Auditoria\Presentacion\Http\Kernel;
use Auditoria\Presentacion\Http\Solicitud;

/**
 * Transporte que entrega la petición al Kernel REAL en el mismo proceso, con fallas
 * programables de red:
 *  - 'red'    : la petición no llega (conexión rechazada / timeout antes de enviar).
 *  - '503'    : responde un intermediario caído.
 *  - 'perder' : el servidor SÍ procesa la petición, pero la respuesta se pierde (timeout al leer).
 */
final class TransporteEnProceso implements Transporte
{
    /** @var list<string> */
    public array $fallas = [];
    /** @var list<PeticionHttp> */
    public array $peticiones = [];
    public int $llegaronAlServidor = 0;

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function enviar(PeticionHttp $peticion, float $timeoutSegundos): RespuestaHttp
    {
        $this->peticiones[] = $peticion;
        $falla = array_shift($this->fallas);
        if ($falla === 'red') {
            throw new FallaTransporte('Simulado: conexión rechazada');
        }
        if ($falla === '503') {
            return new RespuestaHttp(503, [], ['error' => 'SERVICIO_NO_DISPONIBLE']);
        }

        $this->llegaronAlServidor++;
        $r = $this->kernel->manejar(new Solicitud($peticion->metodo, $peticion->ruta, $peticion->cabeceras, $peticion->consulta, $peticion->cuerpo));
        if ($falla === 'perder') {
            throw new FallaTransporte('Simulado: la respuesta se perdió');
        }

        return new RespuestaHttp($r->estado, $r->cabeceras, $r->cuerpo);
    }
}
