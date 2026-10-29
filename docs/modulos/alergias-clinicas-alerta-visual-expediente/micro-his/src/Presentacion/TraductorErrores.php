<?php
declare(strict_types=1);

namespace Alergias\Presentacion;

use Alergias\Aplicacion\PacienteNoEncontrado;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\ReglaDominioViolada;
use Throwable;

/** Un solo mapa de excepción → estado HTTP y código (lo usan MVC y API). */
final class TraductorErrores
{
    /** @return array{0: int, 1: string, 2: string} estado, código, mensaje */
    public static function traducir(Throwable $e): array
    {
        return match (true) {
            $e instanceof PacienteNoEncontrado => [404, 'PACIENTE_NO_ENCONTRADO', $e->getMessage()],
            $e instanceof ReglaDominioViolada && in_array($e->codigo, ['ALERGIA_DUPLICADA', 'ALERTA_CAMBIO'], true)
                => [409, $e->codigo, $e->getMessage()],
            $e instanceof ReglaDominioViolada => [422, $e->codigo, $e->getMessage()],
            $e instanceof ErrorPersistencia => [503, 'PERSISTENCIA_NO_DISPONIBLE', 'El servicio no está disponible. No se guardó nada; intente de nuevo.'],
            default => [500, 'ERROR_INTERNO', 'Ocurrió un error inesperado.'],
        };
    }
}
