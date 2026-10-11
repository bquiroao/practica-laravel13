<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

use NotasSoap\Presentacion\Controladores\NotasSoapController;

/** Tabla de rutas de la API v1. Cada ruta declara su permiso; no hay rutas públicas (ARQ-14). */
final class Rutas
{
    /** @return list<Ruta> */
    public static function definir(NotasSoapController $c): array
    {
        return [
            new Ruta('POST', '/api/v1/notas-soap', 'notas_soap.registrar',
                static fn (Solicitud $s, Identidad $q, string $h, array $p): Respuesta => $c->registrar($s, $q, $h)),
            new Ruta('GET', '/api/v1/notas-soap/{notaId}', 'notas_soap.consultar',
                static fn (Solicitud $s, Identidad $q, string $h, array $p): Respuesta => $c->consultar($s, $q, $h, $p['notaId'])),
            new Ruta('POST', '/api/v1/notas-soap/{notaId}/firma', 'notas_soap.firmar',
                static fn (Solicitud $s, Identidad $q, string $h, array $p): Respuesta => $c->firmar($s, $q, $h, $p['notaId'])),
            new Ruta('GET', '/api/v1/expedientes/{expedienteId}/notas-soap', 'notas_soap.consultar',
                static fn (Solicitud $s, Identidad $q, string $h, array $p): Respuesta => $c->listarDeExpediente($s, $q, $h, $p['expedienteId'])),
        ];
    }
}
