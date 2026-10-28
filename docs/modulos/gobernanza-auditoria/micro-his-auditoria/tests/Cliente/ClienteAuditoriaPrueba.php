<?php

declare(strict_types=1);

/*
 * Semana 5 — Cliente-servidor: la librería que usan los módulos EMISORES, contra el kernel real
 * (en proceso) con fallas de red guionadas. Lo central: el emisor nunca pierde un movimiento y
 * nunca lo duplica, aunque se caiga la red o el servicio.
 */

use Auditoria\Cliente\BandejaArchivo;
use Auditoria\Cliente\BandejaEnMemoria;
use Auditoria\Cliente\CircuitoInterruptor;
use Auditoria\Cliente\ClienteAuditoria;
use Auditoria\Cliente\FallaTransporte;
use Auditoria\Cliente\PeticionHttp;
use Auditoria\Cliente\ResultadoCliente;
use Auditoria\Cliente\TransporteHttpStream;
use Auditoria\Pruebas\Dobles\Escenario;
use Auditoria\Pruebas\Dobles\TransporteEnProceso;

/** Cliente de svc-pacientes en hosp-oriente sobre el kernel real; reloj del circuito controlable. */
$cliArmar = static function (?object $e = null, ?BandejaEnMemoria $bandeja = null, float &$segundos = 0.0): object {
    $e ??= Escenario::crear();
    $transporte = new TransporteEnProceso($e->kernel);
    $bandeja ??= new BandejaEnMemoria();
    $circuito = new CircuitoInterruptor(3, 30.0, static function () use (&$segundos): float {
        return $segundos;
    });
    $n = 0;
    $cliente = new ClienteAuditoria($transporte, $circuito, $bandeja, 'token-svc-pacientes-oriente', Escenario::H_ORIENTE,
        dormir: static function (int $ms): void {
        }, generarId: static function () use (&$n): string {
            return sprintf('req-%04d', ++$n);
        });

    return (object) ['e' => $e, 'transporte' => $transporte, 'bandeja' => $bandeja, 'cliente' => $cliente];
};

prueba('CLI-01', 'Comunicación: token, tenant y el MISMO X-Request-ID en cada reintento; el servidor lo devuelve', function () use ($cliArmar): void {
    $c = $cliArmar();
    $c->transporte->fallas = ['red', '503'];
    $r = $c->cliente->registrar(Escenario::mov());
    igual([201, 3], [$r->estado, $r->intentos], 'éxito al tercer intento');
    $ids = array_unique(array_map(static fn (PeticionHttp $p): string => $p->cabeceras['X-Request-ID'], $c->transporte->peticiones));
    igual(['req-0001'], array_values($ids), 'un solo X-Request-ID');
    igual(['Bearer token-svc-pacientes-oriente', Escenario::H_ORIENTE], [$c->transporte->peticiones[0]->cabeceras['Authorization'],
        $c->transporte->peticiones[0]->cabeceras['X-Tenant-ID']], 'credenciales');
    $directa = $c->e->kernel->manejar(Escenario::http('GET', '/api/v1/auditoria/movimientos', 'token-auditor-oriente', Escenario::H_ORIENTE, null,
        ['desde' => '2026-10-21', 'hasta' => '2026-10-21']));
    afirmar(preg_match('/^[0-9a-f]{32}$/', $directa->cabeceras['X-Request-ID'] ?? '') === 1, 'el servidor no genera X-Request-ID cuando falta');
});

prueba('CLI-02', 'Reintento seguro: la respuesta del 201 se pierde, el cliente reintenta y recibe 200 YA_REGISTRADO; hay UN solo movimiento', function () use ($cliArmar): void {
    $c = $cliArmar();
    $c->transporte->fallas = ['perder'];
    $r = $c->cliente->registrar(Escenario::mov());
    igual([200, 'YA_REGISTRADO', 2], [$r->estado, $r->cuerpo['codigo'] ?? null, $c->transporte->llegaronAlServidor], 'reintento');
    igual(1, count($c->e->bitacora->todos()), 'movimientos en la bitácora');
});

prueba('CLI-03', 'Servicio caído: el emisor NO falla; el movimiento va a la bandeja local; el reenvío respeta el circuito y entrega al volver', function () use ($cliArmar): void {
    $segundos = 0.0;
    $c = $cliArmar(null, null, $segundos);
    $c->transporte->fallas = ['red', 'red', 'red'];
    $r = $c->cliente->registrar(Escenario::mov());
    igual([ResultadoCliente::EN_BANDEJA_LOCAL, ['ev-1'], 0], [$r->origen, array_keys($c->bandeja->pendientes()), count($c->e->bitacora->todos())], 'caída');
    igual(['registrados' => 0, 'rechazados' => [], 'pendientes' => 1], $c->cliente->reenviarPendientes(), 'reenvío inmediato: circuito abierto, no insiste');
    $segundos = 31.0;
    igual(['registrados' => 1, 'rechazados' => [], 'pendientes' => 0], $c->cliente->reenviarPendientes(), 'reenvío tras 30 s');
    igual(['ev-1'], array_map(static fn ($m): string => $m->eventId, $c->e->bitacora->todos()), 'bitácora');
    igual(['registrados' => 0, 'rechazados' => [], 'pendientes' => 0], $c->cliente->reenviarPendientes(), 'nada más que reenviar');
});

prueba('CLI-04', 'Un 4xx no se reintenta ni se guarda (error del emisor); en el reenvío, un 4xx sale de la bandeja como rechazado', function () use ($cliArmar): void {
    $c = $cliArmar();
    $r = $c->cliente->registrar(Escenario::mov(['accion' => 'INVENTADA']));
    igual([422, true, 1, []], [$r->estado, $r->rechazado(), count($c->transporte->peticiones), $c->bandeja->pendientes()], '422 directo');
    $c->bandeja->guardar('ev-malo', Escenario::mov(['event_id' => 'ev-malo', 'modulo' => 'rbac', 'accion' => 'ROL_ASIGNADO']));
    $c->bandeja->guardar('ev-bueno', Escenario::mov(['event_id' => 'ev-bueno']));
    igual(['registrados' => 1, 'rechazados' => ['ev-malo' => 403], 'pendientes' => 0], $c->cliente->reenviarPendientes(), 'reenvío mixto');
});

prueba('CLI-05', 'Circuit breaker: 3 fallas → ABIERTO (ya no llama al servidor; todo va a la bandeja) → 30 s → SEMIABIERTO → éxito → CERRADO', function () use ($cliArmar): void {
    $segundos = 0.0;
    $c = $cliArmar(null, null, $segundos);
    $c->transporte->fallas = ['red', 'red', 'red'];
    $c->cliente->registrar(Escenario::mov(['event_id' => 'a']));
    igual(CircuitoInterruptor::ABIERTO, $c->cliente->estadoCircuito(), 'tras 3 fallas');
    $antes = count($c->transporte->peticiones);
    $r = $c->cliente->registrar(Escenario::mov(['event_id' => 'b']));
    igual([ResultadoCliente::EN_BANDEJA_LOCAL, $antes, ['a', 'b']], [$r->origen, count($c->transporte->peticiones), array_keys($c->bandeja->pendientes())],
        'abierto: no llama y guarda');
    $segundos = 31.0;
    igual(['registrados' => 2, 'rechazados' => [], 'pendientes' => 0], $c->cliente->reenviarPendientes(), 'semiabierto: la prueba pasa y se vacía');
    igual(CircuitoInterruptor::CERRADO, $c->cliente->estadoCircuito(), 'cerrado');
});

prueba('CLI-06', 'BandejaArchivo: sobrevive a reiniciar el proceso del emisor y no duplica un event_id', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'bandeja-');
    try {
        $b1 = new BandejaArchivo($ruta);
        $b1->guardar('ev-1', Escenario::mov());
        $b1->guardar('ev-1', Escenario::mov());
        $b1->guardar('ev-2', Escenario::mov(['event_id' => 'ev-2']));
        $b2 = new BandejaArchivo($ruta);                       // «otro proceso» tras el reinicio
        igual(['ev-1', 'ev-2'], array_keys($b2->pendientes()), 'pendientes leídos de nuevo');
        $b2->quitar('ev-1');
        igual(['ev-2'], array_keys((new BandejaArchivo($ruta))->pendientes()), 'tras quitar');
    } finally {
        @unlink($ruta);
    }
});

prueba('CLI-07', 'El emisor debe asignar el event_id antes de enviar (sin él no hay reintento seguro); la consulta usa el mismo cliente', function () use ($cliArmar): void {
    $c = $cliArmar();
    lanza(InvalidArgumentException::class, fn () => $c->cliente->registrar(array_diff_key(Escenario::mov(), ['event_id' => 1])), 'sin event_id');
    $c->cliente->registrar(Escenario::mov());
    $auditor = new ClienteAuditoria(new TransporteEnProceso($c->e->kernel), new CircuitoInterruptor(), new BandejaEnMemoria(), 'token-auditor-oriente',
        Escenario::H_ORIENTE, dormir: static function (int $ms): void {
        });
    $r = $auditor->consultar(['desde' => '2026-10-21', 'hasta' => '2026-10-21']);
    igual([200, 1], [$r->estado, count($r->cuerpo['movimientos'])], 'consulta');
});

prueba('CLI-08', 'Red real: puerto cerrado → FallaTransporte en el transporte HTTP de streams, y el cliente guarda en la bandeja en menos de 5 s', function (): void {
    $inicio = microtime(true);
    lanza(FallaTransporte::class, fn () => (new TransporteHttpStream('http://127.0.0.1:9'))->enviar(new PeticionHttp('GET', '/'), 1.0), 'puerto 9');
    $cliente = new ClienteAuditoria(new TransporteHttpStream('http://127.0.0.1:9'), new CircuitoInterruptor(), $bandeja = new BandejaEnMemoria(),
        't', Escenario::H_ORIENTE, timeoutSegundos: 1.0, reintentos: 1, esperaMs: 10);
    $r = $cliente->registrar(Escenario::mov());
    igual([ResultadoCliente::EN_BANDEJA_LOCAL, ['ev-1']], [$r->origen, array_keys($bandeja->pendientes())], 'en la bandeja');
    afirmar(microtime(true) - $inicio < 5.0, 'tardó más de 5 s');
});
