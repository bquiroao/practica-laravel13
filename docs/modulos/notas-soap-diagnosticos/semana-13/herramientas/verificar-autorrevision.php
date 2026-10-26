<?php

declare(strict_types=1);

/**
 * Semana 13 · Reproduce los hallazgos AR-01, AR-02 y AR-04 de la autorrevisión contra el Micro-HIS real
 * (adaptadores en memoria). No corrige nada: la revisión decide. Uso: php herramientas/verificar-autorrevision.php
 */
$micro = dirname(__DIR__, 2) . '/micro-his-notas-soap';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Pruebas\Dobles\{Escenario, GeneradorSecuencial, RelojFijo};

$config = require $micro . '/config/config.php';
$config['tokens_demo']['token-central'] = ['dr-central', 'hosp-central', ['medico']]; // médico de OTRO hospital
$almacenes = new AlmacenesEnMemoria(require $micro . '/config/datos-demo-memoria.php');
$k = crearKernelEnMemoria($config, $almacenes, new GeneradorSecuencial(), new RelojFijo());

// AR-01: un médico de hosp-central lee una nota y el listado de hosp-oriente
$id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
$central = ['authorization' => 'Bearer token-central', 'x-tenant-id' => 'hosp-central'];
$nota = $k->manejar(Escenario::peticion([], $central, 'GET', "/api/v1/notas-soap/$id"));
$lista = $k->manejar(Escenario::peticion([], $central, 'GET', '/api/v1/expedientes/exp-carlos/notas-soap'));
printf("AR-01 %s  otro hospital: GET nota → %d (%s) · GET listado → %d con %d notas\n", $nota->estado === 200 ? 'REPRODUCIDO' : 'no se reproduce',
    $nota->estado, isset($nota->cuerpo['soap']) ? 'con texto clínico' : 'sin texto', $lista->estado, count($lista->cuerpo['items'] ?? []));

// AR-02: firmar un borrador de un expediente cerrado (exp-rosa); registrar o corregir ahí da 409 expediente_no_disponible
$borrador = NotaSoap::registrar('nota-rosa', 'exp-rosa', 'epi-200', 'dra-mendez',
    new SeccionesSoap('Cefalea', 'PA 120/80', 'Cefalea tensional', 'Analgésico'), new DateTimeImmutable('2026-10-01T08:00:00-06:00'));
$almacenes->notas->guardar($borrador);
$firma = $k->manejar(Escenario::peticion([], [], 'POST', '/api/v1/notas-soap/nota-rosa/firma'));
$registro = $k->manejar(Escenario::peticion(['medical_record_id' => 'exp-rosa', 'care_episode_id' => 'epi-200']));
printf("AR-02 %s  expediente cerrado: firmar → %d · registrar → %d %s\n", $firma->estado === 200 ? 'REPRODUCIDO' : 'no se reproduce',
    $firma->estado, $registro->estado, $registro->cuerpo['error']['code'] ?? '');

// AR-04: el punto de entrada real (modo PDO) usa siempre la autenticación de demostración
$contenedor = (string) file_get_contents($micro . '/bootstrap/contenedor.php');
$siempre = str_contains($contenedor, 'construirKernel(new AutenticadorTokenDemo($config[\'tokens_demo\']), adaptadoresPdo(');
printf("AR-04 %s  crearKernel() (public/index.php) usa AutenticadorTokenDemo con %d tokens fijos de config.php\n",
    $siempre ? 'REPRODUCIDO' : 'no se reproduce', count((require $micro . '/config/config.php')['tokens_demo']));
