<?php

declare(strict_types=1);

namespace Rbac\Pruebas\Dobles;

use Rbac\Cliente\FallaTransporte;
use Rbac\Cliente\RespuestaHttp;
use Rbac\Cliente\Transporte;

/** Transporte con un guion de resultados: respuestas HTTP o fallas de red, en orden. */
final class TransporteGuionado implements Transporte
{
    public int $llamadas = 0;

    /** @param list<RespuestaHttp|FallaTransporte> $guion */
    public function __construct(private array $guion)
    {
    }

    public static function decision(string $decision, int $estado = 200): RespuestaHttp
    {
        return new RespuestaHttp($estado, (string) json_encode(['decision' => $decision] + ($decision === 'DENEGADO' ? ['correlation_id' => 'corr-remoto'] : [])));
    }

    public static function caida(): FallaTransporte
    {
        return new FallaTransporte('conexión rechazada (simulada)');
    }

    public function enviar(string $metodo, string $url, array $cabeceras, ?string $cuerpo, float $timeoutSegundos): RespuestaHttp
    {
        $this->llamadas++;
        $siguiente = array_shift($this->guion) ?? self::caida();
        if ($siguiente instanceof FallaTransporte) {
            throw $siguiente;
        }

        return $siguiente;
    }
}
