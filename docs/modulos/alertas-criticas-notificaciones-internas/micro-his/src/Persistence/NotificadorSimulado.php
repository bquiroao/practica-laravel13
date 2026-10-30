<?php
declare(strict_types=1);

namespace AlertasCriticas\Persistence;

use AlertasCriticas\Domain\PuertoNotificacion;

/**
 * Adaptador SIMULADO del puerto de notificación: registra lo «enviado» en memoria y,
 * si se indica, en un archivo de bitácora. NO envía correos, SMS ni push reales.
 */
final class NotificadorSimulado implements PuertoNotificacion
{
    /** @var array<int, array{usuario: string, mensaje: string}> */
    public array $enviados = [];
    /** @var array<string,int> intentos por usuario */
    public array $intentos = [];

    /** @param string[] $fallanSiempre usuarios cuya entrega falla siempre (para probar reintentos) */
    public function __construct(private array $fallanSiempre = [], private ?string $bitacora = null) {}

    public function enviar(string $usuario, string $mensaje): bool
    {
        $this->intentos[$usuario] = ($this->intentos[$usuario] ?? 0) + 1;
        if (in_array($usuario, $this->fallanSiempre, true)) {
            return false;
        }
        $this->enviados[] = ['usuario' => $usuario, 'mensaje' => $mensaje];
        if ($this->bitacora !== null) {
            file_put_contents($this->bitacora, date('c') . " [SIMULADO] para $usuario: $mensaje" . PHP_EOL, FILE_APPEND);
        }
        return true;
    }
}
