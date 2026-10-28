<?php

declare(strict_types=1);

/*
 * Semana 5 — Cliente-servidor y resiliencia:
 *  CLI-*  cliente de la API (reintentos idempotentes, sin bandeja local, estados CONFIRMADO / RECHAZADO / INCIERTO);
 *  HFA-*  adaptador HTTP del Expediente (fail-closed, límite de 2 s, circuit breaker);
 *  OBS-*  observabilidad (X-Request-ID y registro de acceso sin datos clínicos).
 */

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Cliente\ClientePrescripciones;
use Prescripciones\Cliente\FallaTransporte;
use Prescripciones\Cliente\ResultadoCliente;
use Prescripciones\Cliente\TransporteHttpStream;
use Prescripciones\Dominio\Puertos\Reloj;
use Prescripciones\Persistencia\Http\CircuitoInterruptor;
use Prescripciones\Persistencia\Http\HttpFuenteAlergias;
use Prescripciones\Presentacion\Http\Observabilidad\RegistroAcceso;

$cliente = static function (Escenario $e, array $guion = [], string $token = 'token-dr-lopez'): array {
    $t = new TransporteEnProceso($e->kernel(), $guion);

    return [new ClientePrescripciones($t, $token, 'hosp-norte', 2, static function (int $ms): void {
    }), $t];
};

// --- Cliente ------------------------------------------------------------------------------------------------------
prueba('CLI-01', 'Los reintentos llevan la MISMA Idempotency-Key y el mismo X-Request-ID; la red cae dos veces y al tercero emite', function () use ($cliente): void {
    $e = new Escenario();
    [$c, $t] = $cliente($e, ['caida', 'http502']);
    $r = $c->emitir('P-005', [Escenario::item('PARA500')]);
    igual([ResultadoCliente::CONFIRMADO, 201, 3], [$r->tipo, $r->estado, $r->intentos], 'resultado');
    igual(1, count(array_unique(array_column(array_column($t->llamadas, 'cabeceras'), 'Idempotency-Key'))), 'una sola clave');
    igual(1, count(array_unique(array_column(array_column($t->llamadas, 'cabeceras'), 'X-Request-ID'))), 'un solo X-Request-ID');
});

prueba('CLI-02', 'Respuesta perdida: el servidor emitió, el cliente reintenta y recibe 200 YA_EMITIDA; hay UNA prescripción', function () use ($cliente): void {
    $e = new Escenario();
    [$c] = $cliente($e, ['perdida']);
    $r = $c->emitir('P-005', [Escenario::item('PARA500')]);
    igual([ResultadoCliente::CONFIRMADO, 200, 'YA_EMITIDA'], [$r->tipo, $r->estado, $r->cuerpo['codigo']], 'resultado');
    igual(1, count($e->repositorio->todas()), 'prescripciones');
});

prueba('CLI-03', 'Sin respuesta en todos los intentos → INCIERTO (nada encolado); reintentar luego con la misma clave lo resuelve', function () use ($cliente): void {
    $e = new Escenario();
    [$c] = $cliente($e, ['perdida', 'caida', 'caida']);
    $r = $c->emitir('P-005', [Escenario::item('PARA500')]);
    igual([ResultadoCliente::INCIERTO, null], [$r->tipo, $r->estado], 'incierto');
    afirmar($r->clave !== null, 'el resultado incierto devuelve la clave para resolverlo');
    $despues = $c->emitir('P-005', [Escenario::item('PARA500')], clave: $r->clave);
    igual([ResultadoCliente::CONFIRMADO, 'YA_EMITIDA', 1], [$despues->tipo, $despues->cuerpo['codigo'], count($e->repositorio->todas())], 'resuelto');
});

prueba('CLI-04', 'Un 4xx no se reintenta: bloqueo por alergia, una sola llamada, RECHAZADO con los hallazgos', function () use ($cliente): void {
    $e = new Escenario();
    [$c, $t] = $cliente($e);
    $r = $c->emitir('P-001', [Escenario::item('AMOX500')]);
    igual([ResultadoCliente::RECHAZADO, 422, 'BLOQUEO_ALERGIA', 1], [$r->tipo, $r->estado, $r->cuerpo['error'], count($t->llamadas)], 'resultado');
});

prueba('CLI-05', 'Expediente caído: el cliente reintenta y termina RECHAZADO (no INCIERTO): el servidor afirmó que no emitió', function () use ($cliente): void {
    $e = new Escenario(new FuenteAlergiasCaida());
    [$c, $t] = $cliente($e);
    $r = $c->emitir('P-005', [Escenario::item('PARA500')]);
    igual([ResultadoCliente::RECHAZADO, 503, 'VERIFICACION_NO_DISPONIBLE', 3, 0], [$r->tipo, $r->estado, $r->cuerpo['error'], count($t->llamadas),
        count($e->repositorio->todas())], 'resultado');
});

prueba('CLI-06', 'TransporteHttpStream contra un puerto cerrado: FallaTransporte en menos de 5 s', function (): void {
    $inicio = microtime(true);
    lanza(FallaTransporte::class, static fn () => (new TransporteHttpStream('http://127.0.0.1:1', 2.0))->enviar('GET', '/x', [], null), 'puerto cerrado');
    afirmar(microtime(true) - $inicio < 5, 'tardó demasiado');
});

// --- Adaptador HTTP del Expediente --------------------------------------------------------------------------------
$expedienteJson = static function (string $url): array {
    preg_match('#/pacientes/([^/]+)/alergias$#', $url, $m);
    $datos = Escenario::datos()['alergias'][rawurldecode($m[1])] ?? [];
    $claves = ['codigo', 'nombre', 'tipo', 'severidad', 'estado', 'reaccion'];

    return [200, (string) json_encode(['evaluado' => $datos !== [], 'alergias' => array_map(static fn (array $a) => array_combine($claves, $a), $datos)])];
};
$relojMovil = static fn (): object => new class implements Reloj {
    public int $segundos = 0;

    public function ahora(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-10-27T15:00:00Z'))->modify("+{$this->segundos} seconds");
    }
};

prueba('HFA-01', 'HttpFuenteAlergias traduce el JSON del Expediente al mismo expediente que el adaptador en memoria', function () use ($expedienteJson): void {
    $fuente = new HttpFuenteAlergias('http://expediente', null, static fn (string $url) => $expedienteJson($url));
    foreach (Escenario::alergias() + ['P-003' => []] as $paciente => $esperadas) {
        $exp = $fuente->alergiasDe($paciente, 'hosp-norte');
        igual([$esperadas !== [], serialize($esperadas)], [$exp->evaluado, serialize($exp->alergias)], "expediente de $paciente");
    }
});

prueba('HFA-02', 'Fail-closed: 500, JSON inválido, campo faltante, severidad fuera del esquema, contradicción o excepción → no disponible, nunca vacío',
    function (): void {
        $respuestas = [
            '500' => [500, '{}'],
            'JSON inválido' => [200, '{"evaluado": tr'],
            'sin «alergias»' => [200, '{"evaluado": true}'],
            'severidad inventada' => [200, '{"evaluado": true, "alergias": [{"codigo": "X", "tipo": "allergy", "severidad": "grave", "estado": "active"}]}'],
            'evaluado=false con alergias' => [200, '{"evaluado": false, "alergias": [{"codigo": "X", "tipo": "allergy", "severidad": "mild", "estado": "active"}]}'],
            'lista vacía con evaluado=true es válida' => null,
        ];
        foreach ($respuestas as $caso => $respuesta) {
            if ($respuesta === null) {
                continue;
            }
            lanza(FuenteAlergiasNoDisponible::class, static fn () => (new HttpFuenteAlergias('http://e', null, static fn () => $respuesta))
                ->alergiasDe('P-001', 'hosp-norte'), (string) $caso);
        }
        lanza(FuenteAlergiasNoDisponible::class, static fn () => (new HttpFuenteAlergias('http://e', null, static fn () => throw new \RuntimeException('timeout')))
            ->alergiasDe('P-001', 'hosp-norte'), 'excepción del transporte');
        $vacia = (new HttpFuenteAlergias('http://e', null, static fn () => [200, '{"evaluado": true, "alergias": []}']))->alergiasDe('P-001', 'hosp-norte');
        igual([true, []], [$vacia->evaluado, $vacia->alergias], 'evaluado sin alergias activas es un dato válido');
    });

prueba('HFA-03', 'Circuit breaker: tras 3 fallas no llama durante 30 s (sigue fail-closed); luego un intento; si funciona, se cierra',
    function () use ($expedienteJson, $relojMovil): void {
        $reloj = $relojMovil();
        $circuito = new CircuitoInterruptor($reloj, 3, 30);
        $llamadas = 0;
        $sano = false;
        $fuente = new HttpFuenteAlergias('http://e', $circuito, static function (string $url) use (&$llamadas, &$sano, $expedienteJson): array {
            $llamadas++;

            return $sano ? $expedienteJson($url) : [500, '{}'];
        });
        for ($i = 0; $i < 5; $i++) {
            lanza(FuenteAlergiasNoDisponible::class, static fn () => $fuente->alergiasDe('P-001', 'hosp-norte'), "intento $i");
        }
        igual([3, CircuitoInterruptor::ABIERTO], [$llamadas, $circuito->estado()], 'con el circuito abierto no se llama');
        $reloj->segundos = 31;
        $sano = true;
        igual(true, $fuente->alergiasDe('P-001', 'hosp-norte')->evaluado, 'intento de prueba');
        igual([4, CircuitoInterruptor::CERRADO], [$llamadas, $circuito->estado()], 'cerrado');
    });

/** Arranca un php -S con el Expediente simulado; devuelve [proceso, url]. Omite la prueba si no puede. */
$expedienteReal = static function (array $entorno): array {
    $puerto = 18000 + random_int(0, 999);
    $proceso = proc_open([PHP_BINARY, '-S', "127.0.0.1:$puerto", dirname(__DIR__, 2) . '/bin/expediente-simulado.php'],
        [['pipe', 'r'], ['file', 'NUL', 'w'], ['file', 'NUL', 'w']], $tuberias, null, $entorno + getenv());
    for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
        usleep(100_000);
    }
    if ($i === 50) {
        proc_terminate($proceso);
        omitir('no se pudo arrancar php -S');
    }

    return [$proceso, "http://127.0.0.1:$puerto"];
};

prueba('HFA-04', 'Con un Expediente HTTP real: responde → mismas alergias; tarda 3 s → no disponible en ≈ 2 s (RNF-08)', function () use ($expedienteReal): void {
    [$p1, $url] = $expedienteReal([]);
    try {
        $exp = (new HttpFuenteAlergias($url))->alergiasDe('P-001', 'hosp-norte');
        igual('J01CA04', $exp->alergias[0]->codigo, 'alergia leída por HTTP');
    } finally {
        proc_terminate($p1);
    }
    [$p2, $urlLento] = $expedienteReal(['EXPEDIENTE_RETRASO_MS' => '3000']);
    try {
        $inicio = microtime(true);
        lanza(FuenteAlergiasNoDisponible::class, static fn () => (new HttpFuenteAlergias($urlLento))->alergiasDe('P-001', 'hosp-norte'), 'lento');
        $duracion = microtime(true) - $inicio;
        afirmar($duracion >= 1.8 && $duracion < 2.9, sprintf('debía cortar en ≈ 2 s y tardó %.2f s', $duracion));
    } finally {
        proc_terminate($p2);
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
    $k = (new Escenario())->kernel();
    $valido = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500')]],
        cabeceras: ['idempotency-key' => 'o1', 'x-request-id' => 'abc-12345678']));
    $inseguro = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', cabeceras: ['x-request-id' => "x\r\nSet-Cookie: a=b"]));
    $sinToken = $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: 'falso'));
    $ruta = $k->manejar(Escenario::http('GET', '/api/v1/otra'));
    igual('abc-12345678', $valido->cabeceras['X-Request-ID'], 'respetado');
    afirmar(preg_match('/^[a-f0-9]{16}$/', $inseguro->cabeceras['X-Request-ID']) === 1, 'reemplazado por uno generado');
    afirmar(isset($sinToken->cabeceras['X-Request-ID'], $ruta->cabeceras['X-Request-ID']), 'también en los errores');
});

prueba('OBS-02', 'Registro de acceso: una línea por petición, sin token, cuerpo, paciente ni medicamento', function () use ($registroEnMemoria): void {
    $registro = $registroEnMemoria();
    $e = new Escenario();
    $config = Escenario::config();
    $k = construirKernel($e->casos, new \Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo($config['tokens_demo']), $registro);
    $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [Escenario::item('CEFA500')],
        'justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas'], cabeceras: ['idempotency-key' => 'o2']));
    $k->manejar(Escenario::http('GET', '/api/v1/prescripciones/1', token: 'falso'));
    igual(2, count($registro->lineas), 'líneas');
    igual(['request_id', 'metodo', 'ruta', 'estado', 'duracion_ms', 'usuario', 'tenant'], array_keys($registro->lineas[0]), 'campos');
    igual([201, 'dr-lopez', 'hosp-norte', 401, null], [$registro->lineas[0]['estado'], $registro->lineas[0]['usuario'], $registro->lineas[0]['tenant'],
        $registro->lineas[1]['estado'], $registro->lineas[1]['usuario']], 'contenido');
    $texto = json_encode($registro->lineas);
    foreach (['token-dr-lopez', 'P-002', 'CEFA500', 'cefalosporinas', 'Penicilina'] as $prohibido) {
        afirmar(!str_contains($texto, $prohibido), "el registro contiene «{$prohibido}»");
    }
});

prueba('OBS-03', 'Si el registro de acceso falla, la respuesta no cambia', function (): void {
    $e = new Escenario();
    $config = Escenario::config();
    $roto = new class implements RegistroAcceso {
        public function registrar(array $linea): void
        {
            throw new \RuntimeException('disco lleno');
        }
    };
    $k = construirKernel($e->casos, new \Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo($config['tokens_demo']), $roto);
    $r = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [Escenario::item('PARA500')]],
        cabeceras: ['idempotency-key' => 'o3']));
    igual(201, $r->estado, 'estado');
});
