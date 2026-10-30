<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\PuertoNotificacion;

/** Única responsabilidad: entregar a cada usuario con reintentos (RN-08). No sabe qué canal usa. */
final class DespachadorNotificaciones
{
    public function __construct(private PuertoNotificacion $puerto, private int $maxIntentos = 3) {}

    /** @return array{entregados: string[], fallidos: string[]} */
    public function despachar(string $mensaje, array $usuarios): array
    {
        $resultado = ['entregados' => [], 'fallidos' => []];
        foreach ($usuarios as $usuario) {
            $entregado = false;
            for ($intento = 1; $intento <= $this->maxIntentos && !$entregado; $intento++) {
                try {
                    $entregado = $this->puerto->enviar($usuario, $mensaje);
                } catch (\Throwable) {
                    $entregado = false; // RNF-05: una excepción del canal cuenta como intento fallido
                }
            }
            $resultado[$entregado ? 'entregados' : 'fallidos'][] = $usuario;
        }
        return $resultado;
    }

    public function maxIntentos(): int
    {
        return $this->maxIntentos;
    }
}
