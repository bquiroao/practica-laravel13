<?php

declare(strict_types=1);

use Qa\Cliente\FallaTransporte;
use Qa\Cliente\Transporte;
use Qa\Presentacion\Http\Kernel;
use Qa\Presentacion\Http\Solicitud;

/**
 * Transporte de prueba: entrega la petición al kernel EN PROCESO y simula la red según un guion, un paso por llamada:
 *   'ok'       → llega y responde;
 *   'caida'    → no llega (FallaTransporte antes del servidor);
 *   'perdida'  → llega, el servidor la PROCESA, y la respuesta se pierde (FallaTransporte después);
 *   'http502'  → un proxy responde 502 sin llegar al servidor.
 * Cuando el guion se agota, todo es 'ok'.
 */
final class TransporteEnProceso implements Transporte
{
    /** @var list<array{metodo: string, ruta: string, cabeceras: array<string, string>, paso: string}> */
    public array $llamadas = [];

    /** @param list<string> $guion */
    public function __construct(private readonly Kernel $kernel, private array $guion = [])
    {
    }

    public function enviar(string $metodo, string $ruta, array $cabeceras, ?array $cuerpo): array
    {
        $paso = array_shift($this->guion) ?? 'ok';
        $this->llamadas[] = compact('metodo', 'ruta', 'cabeceras', 'paso');
        if ($paso === 'caida') {
            throw new FallaTransporte('red caída');
        }
        if ($paso === 'http502') {
            return ['estado' => 502, 'cabeceras' => [], 'cuerpo' => ['error' => 'BAD_GATEWAY']];
        }
        $r = $this->kernel->manejar(new Solicitud($metodo, $ruta, array_change_key_case($cabeceras), $cuerpo === null ? '' : (string) json_encode($cuerpo)));
        if ($paso === 'perdida') {
            throw new FallaTransporte('respuesta perdida');
        }

        return ['estado' => $r->estado, 'cabeceras' => array_change_key_case($r->cabeceras), 'cuerpo' => $r->cuerpo];
    }
}
