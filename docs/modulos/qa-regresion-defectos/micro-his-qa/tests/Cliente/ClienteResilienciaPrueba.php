<?php

declare(strict_types=1);

/*
 * Semana 5 — Cliente-servidor: el cliente con su bandeja de pendientes, el almacén de evidencias como servicio remoto
 * (con circuito) y la observabilidad del kernel.
 */

use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Cliente\ClienteQa;
use Qa\Cliente\FallaTransporte;
use Qa\Cliente\ResultadoCliente;
use Qa\Cliente\TransporteHttpStream;
use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\FabricaEvidencias;
use Qa\Dominio\Puertos\Reloj;
use Qa\Persistencia\Http\AlmacenEvidenciasHttp;
use Qa\Persistencia\Http\CircuitoInterruptor;
use Qa\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Qa\Presentacion\Http\Observabilidad\RegistroAcceso;
use Qa\Presentacion\Http\Solicitud;

/** @return array{0: ClienteQa, 1: TransporteEnProceso} */
$cliente = static function (Escenario $e, array $guion = [], string $token = 'token-tito-tester'): array {
    $t = new TransporteEnProceso($e->kernel(), $guion);

    return [new ClienteQa($t, $token, 'hosp-norte', esperar: static function (int $ms): void {
    }), $t];
};
$falla = static fn (): array => [['tipo' => 'captura', 'bytes' => Escenario::PNG . 'cli']];
$defecto = ['titulo' => 'Nota firmada editable', 'pasos' => ['Abrir', 'Editar'], 'severidad' => 'CRITICA'];

prueba('CLI-01', 'Los reintentos llevan la MISMA Idempotency-Key y el mismo X-Request-ID; la red cae dos veces y al tercero registra', function () use ($cliente, $falla, $defecto): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    [$c, $t] = $cliente($e, ['caida', 'caida']);
    $r = $c->registrarResultado($id, 'TC-107', 'FALLA', $falla(), $defecto);
    igual([ResultadoCliente::CONFIRMADO, 201, 3, 1], [$r->tipo, $r->estado, $r->intentos, count($e->intentos())], 'resultado');
    igual(1, count(array_unique(array_map(static fn (array $l): string => $l['cabeceras']['Idempotency-Key'] . '|' . $l['cabeceras']['X-Request-ID'], $t->llamadas))), 'misma clave y request id');
});

prueba('CLI-02', 'Respuesta perdida: el servidor registró, el cliente reintenta y recibe 200 RESULTADO_YA_REGISTRADO; hay UN intento y UN defecto', function () use ($cliente, $falla, $defecto): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    [$c] = $cliente($e, ['perdida']);
    $r = $c->registrarResultado($id, 'TC-107', 'FALLA', $falla(), $defecto);
    igual([ResultadoCliente::CONFIRMADO, 200, 'RESULTADO_YA_REGISTRADO'], [$r->tipo, $r->estado, $r->cuerpo['codigo']], 'repetición');
    igual([1, 1], [count($e->intentos()), count($e->defectos->deCampana('hosp-norte', $id))], 'sin duplicados');
});

prueba('CLI-03', 'Sin respuesta en ningún intento → PENDIENTE en la bandeja; reenviarPendientes() lo registra UNA vez y vacía la bandeja', function () use ($cliente): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    [$c, $t] = $cliente($e, ['caida', 'perdida', 'caida']);   // el segundo intento SÍ llegó: el servidor registró
    $r = $c->registrarResultado($id, 'TC-108', 'PASA');
    igual([ResultadoCliente::PENDIENTE, 1, 1], [$r->tipo, count($c->pendientes()), count($e->intentos())], 'pendiente, aunque ya está');
    $reenvio = $c->reenviarPendientes()[$r->clave];
    igual([ResultadoCliente::CONFIRMADO, 'RESULTADO_YA_REGISTRADO', 0, 1], [$reenvio->tipo, $reenvio->cuerpo['codigo'], count($c->pendientes()),
        count($e->intentos())], 'resuelto sin duplicar');
    igual(4, count($t->llamadas), 'llamadas');
});

prueba('CLI-04', 'Un 4xx no se reintenta ni se encola: FALLA sin evidencia → RECHAZADO en una llamada', function () use ($cliente, $defecto): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    [$c, $t] = $cliente($e);
    $r = $c->registrarResultado($id, 'TC-107', 'FALLA', [], $defecto);
    igual([ResultadoCliente::RECHAZADO, 422, 'EVIDENCIA_REQUERIDA', 1, []], [$r->tipo, $r->estado, $r->cuerpo['error'], count($t->llamadas), $c->pendientes()], 'rechazo');
});

prueba('CLI-05', 'Almacén caído (503): PENDIENTE sin nada escrito; cuando vuelve, el reenvío registra el resultado y su defecto una sola vez', function () use ($cliente, $falla, $defecto): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    [$c, $t] = $cliente($e);
    $e->almacen->caido = true;
    $r = $c->registrarResultado($id, 'TC-107', 'FALLA', $falla(), $defecto);
    igual([ResultadoCliente::PENDIENTE, 'EVIDENCIA_NO_DISPONIBLE', 3, 0], [$r->tipo, $r->cuerpo['error'], count($t->llamadas), count($e->intentos())], 'pendiente');
    $e->almacen->caido = false;
    $reenvio = $c->reenviarPendientes()[$r->clave];
    igual([ResultadoCliente::CONFIRMADO, 201, 1, 1], [$reenvio->tipo, $reenvio->estado, count($e->intentos()), count($e->defectos->deCampana('hosp-norte', $id))], 'registrado');
});

prueba('CLI-06', 'Un pendiente que llega después del cierre se RECHAZA (409) y sale de la bandeja: el tester se entera, no se pierde en silencio', function () use ($cliente): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    [$c] = $cliente($e, ['caida', 'caida', 'caida']);
    $r = $c->registrarResultado($id, 'TC-108', 'PASA');
    $e->registrar($id, 'TC-107', 'PASA');
    $e->registrar($id, 'TC-108', 'PASA');
    $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
    $reenvio = $c->reenviarPendientes()[$r->clave];
    igual([ResultadoCliente::RECHAZADO, 409, 'CAMPANA_NO_ADMITE_RESULTADOS', []], [$reenvio->tipo, $reenvio->estado, $reenvio->cuerpo['error'], $c->pendientes()], 'informado');
});

prueba('CLI-07', 'Una operación SIN clave (cerrar) que pierde la respuesta → INCIERTO tras UNA llamada, sin reintento ni bandeja', function () use ($cliente): void {
    $e = new Escenario();
    $id = $e->campanaEnCurso(['TC-107', 'TC-108']);
    $e->registrar($id, 'TC-107', 'PASA');
    $e->registrar($id, 'TC-108', 'PASA');
    [$c, $t] = $cliente($e, ['perdida'], 'token-lider-qa');
    $r = $c->cerrar($id);
    igual([ResultadoCliente::INCIERTO, 1, [], 'CERRADA'], [$r->tipo, count($t->llamadas), $c->pendientes(),
        $c->trazabilidad($id)->cuerpo['estado']], 'incierto; consultar resuelve');
});

prueba('CLI-08', 'TransporteHttpStream contra un puerto cerrado: FallaTransporte en menos de 5 s', function (): void {
    $inicio = microtime(true);
    lanza(FallaTransporte::class, static fn () => (new TransporteHttpStream('http://127.0.0.1:1', 2.0))->enviar('GET', '/x', [], null), 'puerto cerrado');
    afirmar(microtime(true) - $inicio < 5, 'tardó demasiado');
});

// --- Almacén de evidencias como servicio remoto -------------------------------------------------------------------
final class RelojMovil implements Reloj
{
    public int $segundos = 0;

    public function ahora(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-10-28T15:00:00Z'))->modify("+{$this->segundos} seconds");
    }
}

$evidencia = static fn (): \Qa\Dominio\Evidencia\EvidenciaVerificable => FabricaEvidencias::crear('captura', Escenario::PNG, Clasificacion::GENERAL);

prueba('AHT-01', 'AlmacenEvidenciasHttp: PUT con la huella en la ruta; confirma con la huella; GET verifica el contenido', function () use ($evidencia): void {
    $guardado = [];
    $http = static function (string $m, string $url, float $l, array $h, ?string $c) use (&$guardado): array {
        if ($m === 'PUT') {
            $guardado[$url] = $c;

            return [201, json_encode(['huella' => hash('sha256', (string) $c)])];
        }

        return [200, $guardado[$url] ?? ''];
    };
    $a = new AlmacenEvidenciasHttp('http://almacen', null, $http);
    $u = $a->guardar('hosp-norte', $evidencia());
    igual(['hosp-norte/' . hash('sha256', Escenario::PNG), ['http://almacen/almacen/v1/hosp-norte/' . hash('sha256', Escenario::PNG)], Escenario::PNG],
        [$u, array_keys($guardado), $a->leer($u)], 'ida y vuelta');
});

prueba('AHT-02', 'Fail-closed: 500, otra huella en la confirmación, excepción de red o bytes alterados al leer → AlmacenNoDisponible', function () use ($evidencia): void {
    $casos = [
        'estado 500' => static fn (): array => [500, '{"error":"x"}'],
        'otra huella' => static fn (): array => [201, json_encode(['huella' => str_repeat('0', 64)])],
        'sin JSON' => static fn (): array => [201, 'ok'],
        'red' => static fn (): array => throw new \RuntimeException('timeout'),
    ];
    foreach ($casos as $que => $http) {
        lanza(AlmacenNoDisponible::class, static fn () => (new AlmacenEvidenciasHttp('http://a', null, $http))->guardar('hosp-norte', $evidencia()), $que);
    }
    $alterado = new AlmacenEvidenciasHttp('http://a', null, static fn (): array => [200, 'otros bytes']);
    lanza(AlmacenNoDisponible::class, static fn () => $alterado->leer('hosp-norte/' . hash('sha256', Escenario::PNG)), 'alterado');
});

prueba('AHT-03', 'Circuito: tras 3 fallas no llama durante 30 s (sigue fail-closed); luego permite UN intento y, si funciona, se cierra', function () use ($evidencia): void {
    $reloj = new RelojMovil();
    $circuito = new CircuitoInterruptor($reloj);
    $llamadas = 0;
    $sano = false;
    $http = static function (string $m, string $u, float $l, array $h, ?string $c) use (&$llamadas, &$sano): array {
        $llamadas++;

        return $sano ? [201, json_encode(['huella' => hash('sha256', (string) $c)])] : [503, ''];
    };
    $a = new AlmacenEvidenciasHttp('http://a', $circuito, $http);
    for ($i = 0; $i < 5; $i++) {
        lanza(AlmacenNoDisponible::class, static fn () => $a->guardar('hosp-norte', $evidencia()), "falla $i");
    }
    igual([3, CircuitoInterruptor::ABIERTO], [$llamadas, $circuito->estado()], 'abierto tras 3');
    $reloj->segundos = 31;
    $sano = true;
    $a->guardar('hosp-norte', $evidencia());
    igual([4, CircuitoInterruptor::CERRADO], [$llamadas, $circuito->estado()], 'semiabierto → cerrado');
});

/** Arranca un php -S con el almacén simulado; devuelve [proceso, url]. Omite la prueba si no puede. */
$almacenReal = static function (array $entorno): array {
    $puerto = 18000 + random_int(0, 999);
    $proceso = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", dirname(__DIR__, 2) . '/bin/almacen-simulado.php'],
        [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $tuberias, null,
        $entorno + ['ALMACEN_DIR' => sys_get_temp_dir() . '/qa-almacen-' . $puerto] + getenv());
    for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
        usleep(100_000);
    }
    if ($i === 50) {
        proc_terminate($proceso);
        omitir('no se pudo arrancar php -S');
    }

    return [$proceso, "http://127.0.0.1:$puerto"];
};

prueba('AHT-04', 'Con un almacén HTTP real: guarda y relee; si confirma otra huella → no disponible; si tarda 5 s → no disponible en ≈ 3 s', function () use ($almacenReal, $evidencia): void {
    [$p1, $url] = $almacenReal([]);
    try {
        $a = new AlmacenEvidenciasHttp($url);
        igual(Escenario::PNG, $a->leer($a->guardar('hosp-norte', $evidencia())), 'ida y vuelta por HTTP');
    } finally {
        proc_terminate($p1);
    }
    [$p2, $urlCorrupto] = $almacenReal(['ALMACEN_CORROMPER' => '1']);
    try {
        lanza(AlmacenNoDisponible::class, static fn () => (new AlmacenEvidenciasHttp($urlCorrupto))->guardar('hosp-norte', $evidencia()), 'corrupto');
    } finally {
        proc_terminate($p2);
    }
    [$p3, $urlLento] = $almacenReal(['ALMACEN_RETRASO_MS' => '5000']);
    try {
        $inicio = microtime(true);
        lanza(AlmacenNoDisponible::class, static fn () => (new AlmacenEvidenciasHttp($urlLento))->guardar('hosp-norte', $evidencia()), 'lento');
        $duracion = microtime(true) - $inicio;
        afirmar($duracion >= 2.7 && $duracion < 4.5, sprintf('debía cortar en ≈ 3 s y tardó %.2f s', $duracion));
    } finally {
        proc_terminate($p3);
    }
});

// --- Observabilidad -----------------------------------------------------------------------------------------------
$registroEnMemoria = static fn (): RegistroAcceso => new class implements RegistroAcceso {
    public array $lineas = [];

    public function registrar(array $linea): void
    {
        $this->lineas[] = $linea;
    }
};

prueba('OBS-01', 'X-Request-ID en TODA respuesta (201, 401, 404, 500): se respeta el válido y se reemplaza el inseguro', function (): void {
    $e = new Escenario();
    $k = $e->kernel();
    $h = ['authorization' => 'Bearer token-lider-qa', 'x-tenant-id' => 'hosp-norte'];
    $valido = $k->manejar(new Solicitud('POST', '/api/v1/qa/campanas', $h + ['x-request-id' => 'abc-12345678'],
        json_encode(['nombre' => 'X', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107']])));
    $inseguro = $k->manejar(new Solicitud('GET', '/api/v1/qa/campanas/1/trazabilidad', $h + ['x-request-id' => "x\r\nSet-Cookie: a=b"]));
    $sinToken = $k->manejar(new Solicitud('GET', '/api/v1/qa/campanas/1/trazabilidad'));
    igual([201, 'abc-12345678'], [$valido->estado, $valido->cabeceras['X-Request-ID']], 'respetado');
    afirmar(preg_match('/^[a-f0-9]{16}$/', $inseguro->cabeceras['X-Request-ID']) === 1, 'reemplazado por uno generado');
    afirmar(isset($sinToken->cabeceras['X-Request-ID']), 'también en los errores');
});

prueba('OBS-02', 'Registro de acceso: una línea por petición con el código de la salida, sin token, cuerpo ni contenido de evidencias', function () use ($registroEnMemoria): void {
    $registro = $registroEnMemoria();
    $e = new Escenario();
    $id = $e->campanaEnCurso();
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $k = construirKernel($e->uc, new AutenticadorTokenDemo($config['tokens_demo']), $registro);
    $k->manejar(new Solicitud('POST', "/api/v1/qa/campanas/$id/resultados", ['authorization' => 'Bearer token-tito-tester', 'x-tenant-id' => 'hosp-norte',
        'idempotency-key' => 'obs-0000001'], json_encode(['caso' => 'TC-107', 'resultado' => 'FALLA', 'evidencias' => [['tipo' => 'captura',
        'contenido_base64' => base64_encode(Escenario::PNG)]], 'defecto' => ['titulo' => 'Paciente Juan Pérez visible', 'pasos' => ['a'], 'severidad' => 'MAYOR']])));
    $k->manejar(new Solicitud('GET', '/api/v1/qa/campanas/1/trazabilidad', ['authorization' => 'Bearer falso']));
    igual(2, count($registro->lineas), 'líneas');
    igual(['request_id', 'metodo', 'ruta', 'estado', 'codigo', 'duracion_ms', 'usuario', 'tenant'], array_keys($registro->lineas[0]), 'campos');
    igual([201, 'RESULTADO_REGISTRADO', 'tito-tester', 401, 'NO_AUTENTICADO', null], [$registro->lineas[0]['estado'], $registro->lineas[0]['codigo'],
        $registro->lineas[0]['usuario'], $registro->lineas[1]['estado'], $registro->lineas[1]['codigo'], $registro->lineas[1]['usuario']], 'contenido');
    $texto = json_encode($registro->lineas);
    foreach (['token-tito-tester', 'captura-de-prueba', base64_encode(Escenario::PNG), 'Juan', 'obs-0000001'] as $prohibido) {
        afirmar(!str_contains($texto, $prohibido), "el registro contiene «{$prohibido}»");
    }
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta no cambia', function (): void {
    $roto = new class implements RegistroAcceso {
        public function registrar(array $linea): void
        {
            throw new \RuntimeException('disco lleno');
        }
    };
    $e = new Escenario();
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $k = construirKernel($e->uc, new AutenticadorTokenDemo($config['tokens_demo']), $roto);
    $r = $k->manejar(new Solicitud('POST', '/api/v1/qa/campanas', ['authorization' => 'Bearer token-lider-qa', 'x-tenant-id' => 'hosp-norte'],
        json_encode(['nombre' => 'X', 'funcionalidades' => ['expediente'], 'casos' => ['TC-107']])));
    igual(201, $r->estado, 'estado');
});
