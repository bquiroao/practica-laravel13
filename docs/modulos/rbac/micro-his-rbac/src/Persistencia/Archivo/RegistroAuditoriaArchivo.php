<?php

declare(strict_types=1);

namespace Rbac\Persistencia\Archivo;

use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Puertos\RegistroAuditoria;

/** Bitácora de respaldo (E-07): una línea JSON por evento, solo se agrega al final del archivo. */
final class RegistroAuditoriaArchivo implements RegistroAuditoria
{
    public function __construct(private readonly string $ruta)
    {
    }

    public function registrar(EventoAuditoria $evento): void
    {
        $linea = json_encode([
            'correlation_id' => $evento->correlationId,
            'tipo' => $evento->tipo,
            'actor' => $evento->actorId,
            'hospital' => $evento->hospitalId,
            'datos' => $evento->datos,
            'ocurrido_en' => $evento->ocurridoEn->format(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE);

        if ($linea === false || @file_put_contents($this->ruta, $linea . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new ErrorPersistencia('No se pudo escribir la bitácora de respaldo');
        }
    }

    public function buscarPorCorrelacion(string $correlationId): ?EventoAuditoria
    {
        if (!is_file($this->ruta)) {
            return null;
        }

        foreach (file($this->ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $e = json_decode($linea, true);
            if (is_array($e) && ($e['correlation_id'] ?? null) === $correlationId) {
                return new EventoAuditoria(
                    $e['tipo'],
                    $e['correlation_id'],
                    $e['actor'],
                    $e['hospital'],
                    $e['datos'],
                    new \DateTimeImmutable($e['ocurrido_en']),
                );
            }
        }

        return null;
    }
}
