<?php

declare(strict_types=1);

namespace Prescripciones\Cliente;

/** Cómo viaja una petición. HTTP real en producción; en las pruebas, el kernel en proceso con fallas simuladas. */
interface Transporte
{
    /**
     * @param array<string, string> $cabeceras
     * @return array{estado: int, cabeceras: array<string, string>, cuerpo: array<string, mixed>}
     * @throws FallaTransporte si no hubo respuesta (red caída, tiempo agotado, conexión rechazada)
     */
    public function enviar(string $metodo, string $ruta, array $cabeceras, ?array $cuerpo): array;
}
