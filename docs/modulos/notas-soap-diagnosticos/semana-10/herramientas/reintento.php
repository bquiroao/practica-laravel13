<?php

declare(strict_types=1);

/**
 * Semana 10 · ¿Es seguro reintentar cuando el teléfono no recibió la respuesta?
 * Envía dos veces la MISMA solicitud al kernel del Micro-HIS (adaptadores en memoria),
 * como lo haría la app después de un corte de red, y cuenta las notas que quedaron guardadas.
 *
 * Uso: php herramientas/reintento.php   (desde docs/modulos/notas-soap-diagnosticos/semana-10)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-notas-soap';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use NotasSoap\Pruebas\Dobles\Escenario;

$clave = ['idempotency-key' => 'movil-7f3c2a91-0001'];
$casos = [
    // nombre => función que recibe el escenario y devuelve la solicitud que se repite
    'Registrar sin Idempotency-Key' => static fn (Escenario $e) => Escenario::peticion(),
    'Registrar con Idempotency-Key' => static fn (Escenario $e) => Escenario::peticion([], $clave),
    'Firmar' => static function (Escenario $e) {
        $id = $e->kernel->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
        return Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma");
    },
    'Corregir nota firmada' => static fn (Escenario $e) => Escenario::peticionCorreccion(Escenario::notaFirmada($e->kernel)),
];

$inseguros = 0;
foreach ($casos as $nombre => $preparar) {
    $e = new Escenario();
    $solicitud = $preparar($e);
    $antes = count($e->notas->todas());
    $r1 = $e->kernel->manejar($solicitud);
    $r2 = $e->kernel->manejar($solicitud);
    $nuevas = count($e->notas->todas()) - $antes;
    $id1 = $r1->cuerpo['clinical_note_id'] ?? null;
    $codigo = $r2->cuerpo['error']['code'] ?? (($r2->cuerpo['clinical_note_id'] ?? null) === $id1 ? 'misma nota' : 'otra nota');
    $seguro = $nuevas <= 1;
    $inseguros += $seguro ? 0 : 1;
    printf("%-30s 1.er envío: %d  reintento: %d %-18s notas nuevas: %d  → %s\n",
        $nombre, $r1->estado, $r2->estado, $codigo, $nuevas, $seguro ? 'SEGURO' : 'NO SEGURO (duplica la nota)');
}
echo PHP_EOL . 'Casos: ' . count($casos) . "  No seguros: $inseguros" . PHP_EOL;
echo 'Por eso la app móvil SIEMPRE manda Idempotency-Key al registrar (regla R-M5).' . PHP_EOL;
