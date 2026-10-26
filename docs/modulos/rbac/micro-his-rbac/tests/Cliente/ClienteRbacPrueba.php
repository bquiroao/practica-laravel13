<?php

declare(strict_types=1);

// Cliente-servidor: la librería que usarían otros módulos del HIS para consultar a RBAC.

use Rbac\Cliente\CircuitoInterruptor;
use Rbac\Cliente\ClienteRbac;
use Rbac\Cliente\DecisionRemota;
use Rbac\Cliente\RespuestaHttp;
use Rbac\Cliente\TransporteHttpStream;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Dobles\TransporteEnProceso;
use Rbac\Pruebas\Dobles\TransporteGuionado;

$sinEspera = static function (int $ms): void {
};

prueba('CLI-01', 'Cliente ↔ servidor por HTTP (en proceso): permite a la médica, deniega a la enfermera y propaga X-Request-ID', function () use ($sinEspera): void {
    $e = new Escenario();
    $transporte = new TransporteEnProceso($e->kernel);
    $cliente = new ClienteRbac('http://rbac.local', $transporte, new CircuitoInterruptor(), esperar: $sinEspera);

    $permitida = $cliente->verificar('token-marta', Escenario::H_ORIENTE, 'prescripciones.crear', 'POST /prescripciones', 'traza-modulo-0001');
    afirmar($permitida->permitido && $permitida->origen === DecisionRemota::ORIGEN_SERVICIO, 'médica debió ser permitida');

    $denegada = $cliente->verificar('token-ana', Escenario::H_ORIENTE, 'prescripciones.crear', 'POST /prescripciones', 'traza-modulo-0002');
    afirmar(!$denegada->permitido && $denegada->correlationId !== null, 'enfermera debió ser denegada con correlation_id');
    igual('PERMISO_NO_ASIGNADO', $e->auditoria->buscarPorCorrelacion((string) $denegada->correlationId)?->datos['motivo'] ?? null, 'denegación auditada en el servidor');

    igual(['traza-modulo-0001', 'traza-modulo-0002'], array_column($e->accesos, 'request_id'), 'el servidor registra el mismo X-Request-ID del cliente');
    igual('http://rbac.local/api/v1/autorizaciones/decisiones', $transporte->enviadas[0]['url'], 'URL del contrato');
});

prueba('CLI-02', 'Reintento: una falla de red transitoria se recupera en el segundo intento', function (): void {
    $esperas = [];
    $transporte = new TransporteGuionado([TransporteGuionado::caida(), TransporteGuionado::decision('PERMITIDO')]);
    $cliente = new ClienteRbac('http://rbac', $transporte, new CircuitoInterruptor(), esperar: function (int $ms) use (&$esperas): void {
        $esperas[] = $ms;
    });
    $d = $cliente->verificar('t', 'h', 'a.b', 'r');
    afirmar($d->permitido, 'debió permitirse tras el reintento');
    igual(2, $d->intentos, 'intentos');
    igual([100], $esperas, 'espera antes del reintento (ms)');
});

prueba('CLI-03', 'Fail-closed: si el servicio falla (5xx o red) en todos los intentos, se deniega sin lanzar excepción', function () use ($sinEspera): void {
    $transporte = new TransporteGuionado([new RespuestaHttp(503, '{}'), new RespuestaHttp(500, '{}')]);
    $d = (new ClienteRbac('http://rbac', $transporte, new CircuitoInterruptor(), esperar: $sinEspera))->verificar('t', 'h', 'a.b', 'r');
    afirmar(!$d->permitido, 'debió denegarse');
    igual(DecisionRemota::ORIGEN_FALLA, $d->origen, 'origen');
    igual(2, $transporte->llamadas, 'llamadas (1 + 1 reintento)');
});

prueba('CLI-04', 'Circuit breaker: tras 2 fallas deja de llamar (ABIERTO), prueba de nuevo pasado el tiempo y se cierra al responder', function () use ($sinEspera): void {
    $ahora = 1000.0;
    $circuito = new CircuitoInterruptor(umbralFallas: 2, segundosAbierto: 30, reloj: function () use (&$ahora): float {
        return $ahora;
    });
    $transporte = new TransporteGuionado([
        TransporteGuionado::caida(), TransporteGuionado::caida(),   // verificación 1: 2 intentos fallan
        TransporteGuionado::caida(), TransporteGuionado::caida(),   // verificación 2: 2 intentos fallan → ABIERTO
        TransporteGuionado::decision('PERMITIDO'),                   // tras 30 s: intento de prueba
    ]);
    $cliente = new ClienteRbac('http://rbac', $transporte, $circuito, esperar: $sinEspera);

    $cliente->verificar('t', 'h', 'a.b', 'r');
    $cliente->verificar('t', 'h', 'a.b', 'r');
    igual(CircuitoInterruptor::ABIERTO, $circuito->estado(), 'estado tras 2 fallas');

    $llamadasAntes = $transporte->llamadas;
    $d = $cliente->verificar('t', 'h', 'a.b', 'r');
    igual(DecisionRemota::ORIGEN_CIRCUITO, $d->origen, 'con el circuito abierto no se llama');
    igual($llamadasAntes, $transporte->llamadas, 'llamadas con circuito abierto');

    $ahora += 31;
    afirmar($cliente->verificar('t', 'h', 'a.b', 'r')->permitido, 'intento de prueba exitoso');
    igual(CircuitoInterruptor::CERRADO, $circuito->estado(), 'el circuito se cierra');
});

prueba('CLI-05', 'Un 4xx es respuesta del servicio: deniega sin reintentar y sin abrir el circuito', function () use ($sinEspera): void {
    $circuito = new CircuitoInterruptor(umbralFallas: 1);
    $transporte = new TransporteGuionado([new RespuestaHttp(401, '{"mensaje":"Token inválido o expirado."}')]);
    $d = (new ClienteRbac('http://rbac', $transporte, $circuito, esperar: $sinEspera))->verificar('malo', 'h', 'a.b', 'r');
    afirmar(!$d->permitido && $d->origen === DecisionRemota::ORIGEN_SERVICIO, 'denegado por el servicio');
    igual(1, $transporte->llamadas, 'sin reintento');
    igual(CircuitoInterruptor::CERRADO, $circuito->estado(), 'circuito');
});

prueba('CLI-06', 'Red real: servidor inalcanzable → FallaTransporte → DENEGADO (fail-closed) en poco tiempo', function () use ($sinEspera): void {
    $inicio = microtime(true);
    $d = (new ClienteRbac('http://127.0.0.1:1', new TransporteHttpStream(), new CircuitoInterruptor(), timeoutSegundos: 0.5, esperar: $sinEspera))
        ->verificar('t', 'hosp-oriente', 'prescripciones.crear', 'r');
    afirmar(!$d->permitido, 'debió denegarse');
    igual(DecisionRemota::ORIGEN_FALLA, $d->origen, 'origen');
    afirmar(microtime(true) - $inicio < 5.0, 'no debe quedarse esperando indefinidamente');
});

prueba('CLI-07', 'Endpoint de decisión: 200 con PERMITIDO/DENEGADO, sin motivo en el cuerpo, 422 si el permiso es inválido', function (): void {
    $e = new Escenario();
    $ruta = '/api/v1/autorizaciones/decisiones';
    $permitida = $e->kernel->manejar(Escenario::http('POST', $ruta, 'token-ana', cuerpo: ['permiso' => 'signos_vitales.registrar', 'recurso' => 'r']));
    igual([200, ['decision' => 'PERMITIDO']], [$permitida->estado, $permitida->cuerpo], 'permitida');

    $denegada = $e->kernel->manejar(Escenario::http('POST', $ruta, 'token-ana', cuerpo: ['permiso' => 'prescripciones.crear', 'recurso' => 'r']));
    igual(200, $denegada->estado, 'HTTP de la denegación');
    igual(['decision', 'correlation_id'], array_keys($denegada->cuerpo), 'cuerpo sin motivo');
    igual(1, count(array_filter($e->auditoria->eventos(), static fn (EventoAuditoria $ev): bool => $ev->tipo === 'ACCESO_DENEGADO')), 'denegación auditada');

    $otroHospital = $e->kernel->manejar(Escenario::http('POST', $ruta, 'token-ana', Escenario::H_NORTE, ['permiso' => 'signos_vitales.registrar', 'recurso' => 'r']));
    igual('DENEGADO', $otroHospital->cuerpo['decision'] ?? null, 'no se puede preguntar por otro hospital');

    igual(422, $e->kernel->manejar(Escenario::http('POST', $ruta, 'token-ana', cuerpo: ['permiso' => 'DROP TABLE', 'recurso' => 'r']))->estado, 'permiso inválido');
});
