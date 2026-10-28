<?php

declare(strict_types=1);

use Pacientes\Cliente\CircuitoInterruptor;
use Pacientes\Cliente\ClientePacientes;
use Pacientes\Cliente\ResultadoCliente;
use Pacientes\Cliente\TransporteHttpStream;
use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\TransporteEnProceso;

/** Cliente sobre el Kernel real en proceso, con reloj y esperas falsos. */
function clienteDePrueba(?object $e = null, string $token = 'token-admision-oriente', ?float &$reloj = null): array
{
    $e ??= Escenario::crear();
    $transporte = new TransporteEnProceso($e->kernel);
    $reloj = 1000.0;
    $contador = 0;
    $cliente = new ClientePacientes($transporte, new CircuitoInterruptor(3, 30.0, static function () use (&$reloj): float {
        return $reloj;
    }), $token, Escenario::H_ORIENTE, dormir: static fn (int $ms) => null,
        generarId: static function () use (&$contador): string {
            return sprintf('traza-prueba-%04d', ++$contador);
        });

    return [$cliente, $transporte, $e];
}

prueba('CLI-01', 'Comunicación: token, tenant y el MISMO X-Request-ID en cada reintento; el servidor lo devuelve', function (): void {
    [$cliente, $transporte] = clienteDePrueba();
    $transporte->fallas = ['503'];
    $r = $cliente->buscar(['nombre' => 'lopez'], 'traza-modulo-admision-01');
    igual([ResultadoCliente::SERVIDOR, 200, 2], [$r->origen, $r->estado, $r->intentos], 'resultado');
    igual(['traza-modulo-admision-01', 'traza-modulo-admision-01'], array_map(static fn ($p) => $p->cabeceras['X-Request-ID'], $transporte->peticiones), 'mismo id');
    igual(['Bearer token-admision-oriente', Escenario::H_ORIENTE], [$transporte->peticiones[0]->cabeceras['Authorization'], $transporte->peticiones[0]->cabeceras['X-Tenant-ID']], 'identidad');
});

prueba('CLI-02', 'Reintento acotado: 1 reintento ante red o 5xx; ninguno ante 4xx (es una respuesta válida)', function (): void {
    [$cliente, $transporte] = clienteDePrueba();
    $transporte->fallas = ['red'];
    igual([ResultadoCliente::SERVIDOR, 2], [($r = $cliente->buscar(['nombre' => 'lopez']))->origen, $r->intentos], 'red y luego éxito');

    [$cliente, $transporte] = clienteDePrueba();
    $transporte->fallas = ['red', 'red'];
    igual([ResultadoCliente::FALLA_SERVICIO, 2], [($r = $cliente->buscar(['nombre' => 'lopez']))->origen, $r->intentos], 'dos fallas: no hay tercer intento');

    [$cliente, $transporte] = clienteDePrueba();
    $r = $cliente->buscar(['nombre' => 'lo']);
    igual([422, 1, 1], [$r->estado, $r->intentos, count($transporte->peticiones)], '4xx sin reintento');
});

prueba('CLI-03', 'POST seguro: la respuesta se pierde, el cliente reintenta con la misma Idempotency-Key y hay UN solo paciente', function (): void {
    [$cliente, $transporte, $e] = clienteDePrueba();
    $transporte->fallas = ['perder'];
    $sinDocumento = Escenario::json(['nombres' => 'Juan', 'apellidos' => 'Desconocido', 'fecha_nacimiento' => '2000-01-01', 'sexo' => 'male']);
    $r = $cliente->registrar($sinDocumento);
    igual([201, 2, 2], [$r->estado, $r->intentos, $transporte->llegaronAlServidor], 'reintento procesado');
    igual($transporte->peticiones[0]->cabeceras['Idempotency-Key'], $transporte->peticiones[1]->cabeceras['Idempotency-Key'], 'misma clave');
    igual(1, count($e->repositorio->todos()), 'un solo paciente');
    igual('P-000001', $r->cuerpo['codigo'], 'código original');
});

prueba('CLI-04', 'Circuit breaker: 3 fallas → ABIERTO (no llama al servidor) → 30 s → SEMIABIERTO → éxito → CERRADO', function (): void {
    [$cliente, $transporte, $e] = clienteDePrueba(reloj: $reloj);
    $transporte->fallas = ['red', 'red', 'red'];
    $cliente->buscar(['nombre' => 'lopez']); // 2 fallas
    $cliente->buscar(['nombre' => 'lopez']); // 3.ª falla → abre
    igual(CircuitoInterruptor::ABIERTO, $cliente->estadoCircuito(), 'abierto');
    $llamadas = count($transporte->peticiones);
    igual(ResultadoCliente::CIRCUITO_ABIERTO, $cliente->buscar(['nombre' => 'lopez'])->origen, 'corta sin llamar');
    igual($llamadas, count($transporte->peticiones), 'no hubo llamada');
    $reloj += 31;
    igual(ResultadoCliente::SERVIDOR, $cliente->buscar(['nombre' => 'lopez'])->origen, 'prueba en semiabierto');
    igual(CircuitoInterruptor::CERRADO, $cliente->estadoCircuito(), 'cerrado');
});

prueba('CLI-05', 'Caché con ETag: el segundo GET envía If-None-Match y recibe 304; tras una edición vuelve a 200 con la versión nueva', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    [$cliente, $transporte] = clienteDePrueba($e, 'token-clinico-oriente');
    igual([ResultadoCliente::SERVIDOR, 1], [($r = $cliente->consultar('P-000001'))->origen, $r->cuerpo['paciente']['version']], 'primera lectura');
    igual(ResultadoCliente::CACHE_VALIDADA, ($r = $cliente->consultar('p-000001'))->origen, 'segunda lectura');
    igual('"1"', end($transporte->peticiones)->cabeceras['If-None-Match'] ?? null, 'If-None-Match');
    igual('Ana Lucía López Pérez', $r->cuerpo['paciente']['nombre'], 'cuerpo desde la caché');
    Escenario::actualizar($e, 'P-000001', 1, Escenario::datosAna(['telefono' => '+502 4444 0001']));
    igual([ResultadoCliente::SERVIDOR, 2], [($r = $cliente->consultar('P-000001'))->origen, $r->cuerpo['paciente']['version']], 'después de editar');
});

prueba('CLI-06', 'Degradación (stale-if-error): servidor caído → última copia marcada CACHE_DEGRADADA; sin copia → no disponible', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    [$cliente, $transporte] = clienteDePrueba($e, 'token-clinico-oriente');
    $cliente->consultar('P-000001');
    $transporte->fallas = ['red', 'red', 'red', 'red'];
    $r = $cliente->consultar('P-000001');
    igual([ResultadoCliente::CACHE_DEGRADADA, true, 'P-000001'], [$r->origen, $r->disponible(), $r->cuerpo['paciente']['codigo']], 'copia degradada');
    $sinCopia = $cliente->consultar('P-000002'); // 3.ª falla seguida: el circuito se abre en esta llamada
    igual([ResultadoCliente::CIRCUITO_ABIERTO, false, []], [$sinCopia->origen, $sinCopia->disponible(), $sinCopia->cuerpo], 'sin copia: no inventa datos');
});

prueba('CLI-07', 'PUT sin reintento: si la respuesta se pierde el resultado es INCIERTO (1 sola llamada); un 409 llega tal cual', function (): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    [$cliente, $transporte] = clienteDePrueba($e);
    $transporte->fallas = ['perder'];
    $r = $cliente->actualizar('P-000001', 1, Escenario::json(Escenario::datosAna(['telefono' => '+502 4444 0001'])));
    igual([ResultadoCliente::RESULTADO_INCIERTO, 1], [$r->origen, count($transporte->peticiones)], 'incierto');
    igual(2, $e->repositorio->porCodigo(Escenario::H_ORIENTE, 'P-000001')?->version, 'en realidad sí se aplicó');
    $conflicto = $cliente->actualizar('P-000001', 1, Escenario::json(Escenario::datosAna()));
    igual([409, 'VERSION_DESACTUALIZADA'], [$conflicto->estado, $conflicto->cuerpo['error'] ?? null], 'conflicto real');
});

prueba('CLI-08', 'Timeout real por red: puerto cerrado → FALLA_SERVICIO en 2 intentos, sin excepción y en menos de 5 s', function (): void {
    $cliente = new ClientePacientes(new TransporteHttpStream('http://127.0.0.1:9'), new CircuitoInterruptor(), 't', Escenario::H_ORIENTE,
        timeoutSegundos: 0.5, dormir: static fn (int $ms) => null);
    $inicio = microtime(true);
    $r = $cliente->consultar('P-000001');
    $segundos = microtime(true) - $inicio;
    igual([ResultadoCliente::FALLA_SERVICIO, 2], [$r->origen, $r->intentos], 'resultado');
    afirmar($segundos < 5, sprintf('tardó %.2f s', $segundos));
});
