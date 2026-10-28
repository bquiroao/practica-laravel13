<?php

declare(strict_types=1);

/*
 * Semana 15 — Pruebas de DEFECTOS ABIERTOS: describen el comportamiento CORRECTO y por eso FALLAN hasta que el defecto se corrija.
 * No corren por defecto (la suite principal queda en verde); se ejecutan con: php tests/ejecutar.php --defectos --solo=DEF
 * Cuando un defecto se corrige, su prueba pasa y se mueve a su grupo (como UNI-08 con DEF-07-01).
 * Registro: semana-15/registro-defectos.md.
 */

use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Presentacion\Http\Solicitud;
use Auditoria\Pruebas\Dobles\Escenario;

prueba('DEF-01', '[Crítico · AR-01] Fuera de desarrollo, el punto de entrada real NO acepta tokens de demostración (401); hoy los acepta y «token-superadmin» lee la red', function (): void {
    putenv('AUDITORIA_ENTORNO=produccion');
    try {
        $kernel = crearKernel(Escenario::config(), FabricaAdaptadores::enMemoria());
        $r = $kernel->manejar(new Solicitud('GET', '/api/v1/auditoria/movimientos', ['Authorization' => 'Bearer token-superadmin', 'X-Tenant-ID' => '*'], ['desde' => '2026-10-21', 'hasta' => '2026-10-21']));
        igual(401, $r->estado, 'token demo con AUDITORIA_ENTORNO=produccion');
    } finally {
        putenv('AUDITORIA_ENTORNO');
    }
});

prueba('DEF-02', '[Mayor · AR-04] Un recurso fuera del formato «tipo:identificador» se rechaza (422); hoy «P-000001» se acepta y «¿quién vio la ficha?» no lo encuentra', function (): void {
    $e = Escenario::crear();
    $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/movimientos', 'token-svc-pacientes-oriente', Escenario::H_ORIENTE, Escenario::mov(['recurso' => 'P-000001'])));
    igual([422, ['recurso']], [$r->estado, isset($r->cuerpo['campos']) ? array_keys($r->cuerpo['campos']) : null], 'recurso sin tipo');
});

prueba('DEF-03', '[Menor · AR-06] El contrato documenta que event_id es único por emisor en TODA la red (no por hospital); hoy solo dice «uuid recomendado»', function (): void {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('/^\s+event_id: \{[^\n]*\}$/m', $yaml, $m);
    afirmar(preg_match('/toda la red|en la red|global/i', $m[0] ?? '') === 1, 'descripción de event_id: ' . trim($m[0] ?? '(no encontrada)'));
});

prueba('DEF-04', '[Menor · DEF-15-01, nuevo] Con X-Tenant-ID de un hospital, verificar OTRA cadena se rechaza (403); hoy la cabecera se ignora y verifica Norte', function (): void {
    $e = Escenario::crear();
    $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/auditoria/verificaciones', 'token-superadmin', Escenario::H_ORIENTE, ['hospital' => Escenario::H_NORTE]));
    $a = $e->kernel->manejar(Escenario::http('GET', '/api/v1/auditoria/anclas', 'token-superadmin', Escenario::H_ORIENTE, null, ['hospital' => Escenario::H_NORTE]));
    igual([403, 403], [$r->estado, $a->estado], 'verificar y ancla de Norte con X-Tenant-ID: hosp-oriente');
});
