<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

/** Único lugar que conoce los subtipos para CREARLOS (los usos trabajan con EvidenciaVerificable; semana 2, LSP). */
final class FabricaEvidencias
{
    public const TIPOS = ['captura', 'registro', 'junit'];

    /** @throws EvidenciaInvalida */
    public static function crear(string $tipo, string $bytes, Clasificacion $clasificacion): EvidenciaVerificable
    {
        return match ($tipo) {
            'captura' => new Captura($bytes, $clasificacion),
            'registro' => new RegistroTexto($bytes, $clasificacion),
            'junit' => new ResultadoJUnit($bytes, $clasificacion),
            default => throw new EvidenciaInvalida('TIPO_NO_PERMITIDO'),
        };
    }
}
