<?php

declare(strict_types=1);

// Semana 15 — Defectos abiertos: cada prueba afirma lo CORRECTO y hoy FALLA (php tests/ejecutar.php --defectos).
// Al corregir un defecto, su prueba pasa a la suite normal como prueba de regresión.

use NotasSoap\Persistencia\EnMemoria\AlmacenesEnMemoria;
use NotasSoap\Pruebas\Dobles\{Escenario, GeneradorSecuencial, RelojFijo};

prueba('DEF-01', 'Crítico · un médico de otro hospital no puede leer notas ni listados de hosp-oriente (R-01; AR-01 de la S13)', function (): void {
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $config['tokens_demo']['token-central'] = ['dr-central', 'hosp-central', ['medico']];
    $k = crearKernelEnMemoria($config, new AlmacenesEnMemoria(require dirname(__DIR__, 2) . '/config/datos-demo-memoria.php'), new GeneradorSecuencial(), new RelojFijo());
    $id = $k->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $otro = ['authorization' => 'Bearer token-central', 'x-tenant-id' => 'hosp-central'];
    $estados = [$k->manejar(Escenario::peticion([], $otro, 'GET', "/api/v1/notas-soap/$id"))->estado,
        $k->manejar(Escenario::peticion([], $otro, 'GET', '/api/v1/expedientes/exp-carlos/notas-soap'))->estado];
    afirmar(!array_intersect($estados, [200]), 'leer y listar desde otro hospital deben dar 403 o 404; obtenido ' . implode(' y ', $estados));
});

prueba('DEF-02', 'Mayor · el punto de entrada real no acepta tokens de demostración fuera de desarrollo (seguridad; AR-04 de la S13)', function (): void {
    $contenedor = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/contenedor.php');
    $cuerpo = substr($contenedor, (int) strpos($contenedor, 'function crearKernel('));
    afirmar(!preg_match('/^\s*return construirKernel\(new AutenticadorTokenDemo\(/m', $cuerpo), 'crearKernel() (modo PDO) arma el kernel con AutenticadorTokenDemo sin mirar el entorno');
});

prueba('DEF-03', 'Menor · nuevo · las secciones y la descripción tienen un tamaño máximo (una sección de 1 MB debe dar 422)', function (): void {
    $k = (new Escenario())->kernel;
    $estados = [$k->manejar(Escenario::peticion(['subjetivo' => str_repeat('a', 1_000_000)]))->estado,
        $k->manejar(Escenario::peticion(['diagnostico' => ['code' => 'J18.9', 'description' => str_repeat('a', 20_000), 'type' => 'primary']]))->estado];
    igual([422, 422], $estados, 'sección de 1 MB y descripción de 20 000 caracteres');
});

prueba('DEF-04', 'Menor · reintentar el registro sin Idempotency-Key no duplica la nota (B-6; AR-03 de la S13)', function (): void {
    $e = new Escenario();
    $e->kernel->manejar(Escenario::peticion());
    $e->kernel->manejar(Escenario::peticion());
    afirmar(count($e->notas->todas()) <= 1, 'dos envíos idénticos sin clave dejaron ' . count($e->notas->todas()) . ' notas (se acepta 1, o rechazar el envío sin clave)');
});
