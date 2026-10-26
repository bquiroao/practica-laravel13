<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\Pdo;

use NotasSoap\Dominio\Puertos\RegistroAuditoria;

/** audit_events (append-only en la base). */
final class PdoRegistroAuditoria extends RepositorioPdo implements RegistroAuditoria
{
    public function registrar(string $evento, array $datos): void
    {
        $this->ejecutar(
            'INSERT INTO audit_events (event, data, created_at) VALUES (:evento, :datos, :fecha)',
            ['evento' => $evento, 'datos' => (string) json_encode($datos, JSON_UNESCAPED_UNICODE), 'fecha' => self::fecha(new \DateTimeImmutable())],
        );
    }
}
