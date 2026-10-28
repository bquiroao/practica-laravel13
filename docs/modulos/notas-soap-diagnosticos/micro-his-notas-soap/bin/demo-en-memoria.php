<?php

declare(strict_types=1);

/*
 * Demostración sin base de datos: el MISMO Kernel, controlador, casos de uso y entidades,
 * pero con los adaptadores en memoria. Solo cambia la función de composición.
 *   php bin/demo-en-memoria.php
 */

use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Persistencia\Sistema\GeneradorUuid;
use NotasSoap\Persistencia\Sistema\RelojSistema;
use NotasSoap\Presentacion\Http\Respuesta;
use NotasSoap\Presentacion\Http\Solicitud;

require dirname(__DIR__) . '/bootstrap/contenedor.php';

$config = require dirname(__DIR__) . '/config/config.php';
$almacenes = new AlmacenesEnMemoria(require dirname(__DIR__) . '/config/datos-demo-memoria.php');
$kernel = crearKernelEnMemoria($config, $almacenes, new GeneradorUuid(), new RelojSistema());
$nota = json_decode((string) file_get_contents(dirname(__DIR__) . '/ejemplos/nota.json'), true);

$paso = static function (string $titulo, Solicitud $s) use ($kernel): Respuesta {
    $r = $kernel->manejar($s);
    echo "## $titulo" . PHP_EOL . "{$s->metodo} {$s->ruta}" . PHP_EOL;
    echo ($r->esHtml() ? substr((string) $r->html, 0, 60) . '...' : json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . PHP_EOL;
    echo "[HTTP {$r->estado}]" . PHP_EOL . PHP_EOL;

    return $r;
};
$cab = static fn (string $token, array $extra = []): array => ['x-tenant-id' => 'hosp-oriente', 'authorization' => "Bearer $token"] + $extra;

echo 'Micro-HIS Notas SOAP - demostración con adaptadores EN MEMORIA (PHP ' . PHP_VERSION . ')' . PHP_EOL . PHP_EOL;
$id = $paso('1. Registrar nota (Dra. Méndez)', new Solicitud('POST', '/api/v1/notas-soap', $cab('token-mendez'), $nota))->cuerpo['clinical_note_id'];
$paso('2. Médico no asignado (Dr. López)', new Solicitud('POST', '/api/v1/notas-soap', $cab('token-lopez'), $nota));
$paso('3. Firmar', new Solicitud('POST', "/api/v1/notas-soap/$id/firma", $cab('token-mendez')));
$paso('4. Firmar otra vez', new Solicitud('POST', "/api/v1/notas-soap/$id/firma", $cab('token-mendez')));
$paso('5. Consultar con Accept: text/html (vista HTML)', new Solicitud('GET', "/api/v1/notas-soap/$id", $cab('token-mendez', ['accept' => 'text/html'])));

echo '## 6. Lo que quedó en los almacenes en memoria' . PHP_EOL;
echo 'Notas: ' . count($almacenes->notas->todas()) . ' (estado: ' . $almacenes->notas->buscar($id)?->estado . ')' . PHP_EOL;
echo 'Diagnósticos de la nota: ' . count($almacenes->diagnosticos->deNota($id)) . PHP_EOL;
echo 'Auditoría: ' . implode(', ', array_column($almacenes->auditoria->eventos(), 'evento')) . PHP_EOL;
foreach ($almacenes->bandeja->pendientes() as $ev) {
    echo "Bandeja de salida: {$ev->tipo} " . json_encode($ev->datos, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
