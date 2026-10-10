<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Vistas;

use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Presentacion\Http\Respuesta;

/**
 * Representación HTML para el navegador. Usa plantillas PHP de plantillas/ y escapa TODO lo que
 * viene de la nota con htmlspecialchars: un "<script>" escrito en una sección se muestra como texto.
 */
final class VistaHtml implements Vista
{
    private const ETIQUETAS_ESTADO = ['draft' => 'Borrador', 'signed' => 'Firmada'];

    public function nota(NotaResultado $nota, int $estado): Respuesta
    {
        return Respuesta::html($estado, $this->pintar('nota', [
            'nota' => $nota,
            'estadoTexto' => self::ETIQUETAS_ESTADO[$nota->estado] ?? $nota->estado,
        ]));
    }

    public function error(int $estado, string $codigo, string $mensaje): Respuesta
    {
        return Respuesta::html($estado, $this->pintar('error', ['estado' => $estado, 'codigo' => $codigo, 'mensaje' => $mensaje]));
    }

    /** @param array<string, mixed> $datos */
    private function pintar(string $plantilla, array $datos): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        extract($datos, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/plantillas/' . $plantilla . '.php';

        return (string) ob_get_clean();
    }
}
