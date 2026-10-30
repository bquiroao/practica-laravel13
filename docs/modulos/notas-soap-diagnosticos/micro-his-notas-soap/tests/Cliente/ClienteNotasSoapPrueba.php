<?php

declare(strict_types=1);

/*
 * Cliente ↔ servidor. El cliente se prueba contra el Kernel real (TransporteEnProceso), contra un
 * guion de fallas (TransporteGuionado) y contra la red de verdad (TransporteHttpStream).
 */

use NotasSoap\Cliente\CircuitoInterruptor;
use NotasSoap\Cliente\ClienteNotasSoap;
use NotasSoap\Cliente\ResultadoCliente;
use NotasSoap\Cliente\TransporteHttpStream;
use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\TransporteEnProceso;
use NotasSoap\Pruebas\Dobles\TransporteGuionado;

$nota = [
    'medical_record_id' => 'exp-carlos', 'care_episode_id' => 'epi-100',
    'subjetivo' => 'Tos productiva y fiebre de 3 días', 'objetivo' => 'T 38.6 °C, estertores en base derecha',
    'analisis' => 'Probable neumonía adquirida en la comunidad', 'plan' => 'Radiografía de tórax y antibiótico',
    'diagnostico' => ['code' => 'J18.9', 'description' => 'Neumonía no especificada', 'type' => 'primary'],
];
$esperas = [];
$cliente = static function ($transporte, ?CircuitoInterruptor $circuito = null) use (&$esperas): ClienteNotasSoap {
    $n = 0;

    return new ClienteNotasSoap('http://notas-soap.local', $transporte, $circuito ?? new CircuitoInterruptor(),
        esperar: static function (int $ms) use (&$esperas): void {
            $esperas[] = $ms;
        },
        nuevaClave: static function () use (&$n): string {
            return sprintf('clave-cliente-%04d', ++$n);
        });
};

prueba('CLI-01', 'Camino feliz cliente → servidor: 201 OK, y el cliente envía X-Request-ID e Idempotency-Key', function () use ($cliente, $nota): void {
    $t = new TransporteEnProceso((new Escenario())->kernel);
    $r = $cliente($t)->registrar('token-mendez', 'hosp-oriente', $nota);
    igual([ResultadoCliente::OK, 201, 1], [$r->tipo, $r->estadoHttp, $r->intentos], 'resultado');
    $cab = $t->enviadas[0]['cabeceras'];
    afirmar(isset($cab['X-Request-ID'], $cab['Idempotency-Key']) && $cab['X-Request-ID'] !== $cab['Idempotency-Key'], 'cabeceras de trazabilidad e idempotencia');
});

prueba('CLI-02', 'Respuesta perdida en la red: el cliente reintenta con la MISMA clave y el servidor NO duplica la nota', function () use ($cliente, $nota): void {
    $e = new Escenario();
    $t = new TransporteEnProceso($e->kernel, perderRespuestas: 1);
    $r = $cliente($t)->registrar('token-mendez', 'hosp-oriente', $nota);
    igual([ResultadoCliente::OK, 201, 2, true], [$r->tipo, $r->estadoHttp, $r->intentos, $r->repetida], 'el reintento devolvió la nota ya creada');
    igual($t->enviadas[0]['cabeceras']['Idempotency-Key'], $t->enviadas[1]['cabeceras']['Idempotency-Key'], 'misma clave en los dos intentos');
    igual(1, count($e->notas->todas()), 'una sola nota guardada');
    igual(['NOTA_REGISTRADA'], array_column($e->auditoria->eventos(), 'evento'), 'un solo evento de auditoría');
});

prueba('CLI-03', 'Falla de red y luego éxito: un reintento con espera corta', function () use ($cliente, $nota, &$esperas): void {
    $esperas = [];
    $t = new TransporteGuionado(['falla', 201]);
    $r = $cliente($t)->registrar('token-mendez', 'hosp-oriente', $nota);
    igual([ResultadoCliente::OK, 2, 2, [100]], [$r->tipo, $r->intentos, $t->llamadas, $esperas], 'reintento');
});

prueba('CLI-04', 'Un 5xx se reintenta; un 4xx no (es una respuesta válida del servidor)', function () use ($cliente): void {
    $t = new TransporteGuionado([503, 200]);
    igual([ResultadoCliente::OK, 2], [($r = $cliente($t)->consultar('t', 'h', 'n-1'))->tipo, $r->intentos], 'GET con 503 y luego 200');
    $t = new TransporteGuionado([422, 201]);
    $r = $cliente($t)->registrar('t', 'h', []);
    igual([ResultadoCliente::RECHAZADO, 422, 1, 1], [$r->tipo, $r->estadoHttp, $r->intentos, $t->llamadas], '422 sin reintento');
});

prueba('CLI-05', 'La firma no se reintenta sola: una falla de red da NO_DISPONIBLE después de un solo intento', function () use ($cliente): void {
    $t = new TransporteGuionado(['falla', 200]);
    $r = $cliente($t)->firmar('t', 'h', 'n-1');
    igual([ResultadoCliente::NO_DISPONIBLE, 'FALLA_RED', 1, 1], [$r->tipo, $r->origen, $r->intentos, $t->llamadas], 'firma');
});

prueba('CLI-06', 'Circuit breaker: 3 fallas abren el circuito; mientras está abierto no se llama al servidor; después del enfriamiento prueba de nuevo', function () use ($cliente): void {
    $ahora = 1000.0;
    $circuito = new CircuitoInterruptor(3, 30.0, static function () use (&$ahora): float {
        return $ahora;
    });
    $t = new TransporteGuionado(['falla', 'falla', 'falla', 200]);
    $c = $cliente($t, $circuito);
    $c->consultar('t', 'h', 'n-1'); // 2 fallas (intento + reintento)
    $c->consultar('t', 'h', 'n-1'); // 3.ª falla: abre el circuito y ya no reintenta
    igual([CircuitoInterruptor::ABIERTO, 3], [$circuito->estado(), $t->llamadas], 'circuito abierto tras 3 fallas');
    $r = $c->consultar('t', 'h', 'n-1');
    igual([ResultadoCliente::NO_DISPONIBLE, 'CIRCUITO_ABIERTO', 0, 3], [$r->tipo, $r->origen, $r->intentos, $t->llamadas], 'sin llamar al servidor');
    $ahora += 31;
    igual(CircuitoInterruptor::SEMIABIERTO, $circuito->estado(), 'semiabierto tras el enfriamiento');
    igual(ResultadoCliente::OK, $c->consultar('t', 'h', 'n-1')->tipo, 'una prueba exitosa');
    igual(CircuitoInterruptor::CERRADO, $circuito->estado(), 'vuelve a cerrado');
});

prueba('CLI-07', 'Red real: servidor inalcanzable → NO_DISPONIBLE rápido, sin excepción', function () use ($nota): void {
    $inicio = microtime(true);
    $c = new ClienteNotasSoap('http://127.0.0.1:9', new TransporteHttpStream(), new CircuitoInterruptor(), 0.5, 1, static function (int $ms): void {
    });
    $r = $c->registrar('token-mendez', 'hosp-oriente', $nota);
    igual([ResultadoCliente::NO_DISPONIBLE, 'FALLA_RED', 2], [$r->tipo, $r->origen, $r->intentos], 'resultado');
    afirmar(microtime(true) - $inicio < 3.0, 'debe responder en menos de 3 s');
});

prueba('CLI-08', 'El listado por expediente viaja con su filtro y devuelve solo resúmenes (sin texto clínico)', function () use ($cliente, $nota): void {
    $e = new Escenario();
    $t = new TransporteEnProceso($e->kernel);
    $c = $cliente($t);
    $c->registrar('token-mendez', 'hosp-oriente', $nota);
    $r = $c->notasDeExpediente('token-ana', 'hosp-oriente', 'exp-carlos', 'draft');
    igual([ResultadoCliente::OK, 1], [$r->tipo, count($r->cuerpo['items'] ?? [])], 'una nota en borrador');
    afirmar(str_ends_with($t->enviadas[1]['url'], '/api/v1/expedientes/exp-carlos/notas-soap?estado=draft'), 'URL con filtro');
    afirmar(!str_contains((string) json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE), 'Tos productiva'), 'sin texto de las secciones');
});
