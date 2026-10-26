<?php

declare(strict_types=1);

// Semana 15 — Caja negra: desde el contrato (openapi.yaml 1.2.0), solo por HTTP, sistema nuevo en cada caso y sin módulos ajenos.

use NotasSoap\Pruebas\Dobles\Escenario;

$http = static fn (NotasSoap\Presentacion\Http\Solicitud $s): array => [($r = (new Escenario())->kernel->manejar($s))->estado, $r->cuerpo['error']['code'] ?? null];
$dx = static fn (string $codigo, string $tipo = 'primary'): array => ['diagnostico' => ['code' => $codigo, 'description' => 'Neumonía', 'type' => $tipo]];

prueba('CN-01', 'Particiones de equivalencia del registro: 1 clase válida y 6 inválidas, cada una con su código del contrato', function () use ($http, $dx): void {
    $clases = [
        'válida' => [Escenario::peticion(), [201, null]],
        'sección vacía' => [Escenario::peticion(['analisis' => '']), [422, 'nota_incompleta']],
        'CIE-10 sin formato' => [Escenario::peticion($dx('NEUMONIA')), [422, 'diagnostico_invalido']],
        'tipo fuera del enum' => [Escenario::peticion($dx('J18.9', 'principal')), [422, 'diagnostico_invalido']],
        'campo que no es texto' => [Escenario::peticion(['subjetivo' => 5]), [400, 'cuerpo_invalido']],
        'expediente cerrado' => [Escenario::peticion(['medical_record_id' => 'exp-rosa', 'care_episode_id' => 'epi-200']), [409, 'expediente_no_disponible']],
        'episodio de otro paciente' => [Escenario::peticion(['care_episode_id' => 'epi-200']), [409, 'expediente_no_disponible']],
    ];
    foreach ($clases as $clase => [$peticion, $esperado]) {
        igual($esperado, $http($peticion), $clase);
    }
});

prueba('CN-02', 'Valores límite: CIE-10 de 2 decimales sí y 3 no; Idempotency-Key de 7, 8, 64 y 65 caracteres; motivo de 9 y 10 caracteres', function () use ($http, $dx): void {
    igual([[201, null], [422, 'diagnostico_invalido']], [$http(Escenario::peticion($dx('J18.99'))), $http(Escenario::peticion($dx('J18.999')))], 'CIE-10');
    $claves = array_map(static fn (int $n): int => $http(Escenario::peticion([], ['idempotency-key' => str_repeat('k', $n)]))[0], [7, 8, 64, 65]);
    igual([400, 201, 201, 400], $claves, 'longitud de la clave');
    $motivo = static function (string $m): array { $k = (new Escenario())->kernel; $r = $k->manejar(Escenario::peticionCorreccion(Escenario::notaFirmada($k), ['correction_reason' => $m]));
        return [$r->estado, $r->cuerpo['error']['code'] ?? null]; };
    igual([[422, 'motivo_insuficiente'], [201, null]], [$motivo('123456789'), $motivo('1234567890')], 'motivo 9 / 10');
});

prueba('CN-03', 'Permisos (tabla de decisión): 4 identidades × 5 operaciones; solo la autora asignada escribe, enfermería solo lee y un token inválido no pasa', function (): void {
    $operaciones = static function (Escenario $e): array {
        $borrador = $e->kernel->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
        $firmada = Escenario::notaFirmada($e->kernel);
        return ['registrar' => fn (array $c) => Escenario::peticion([], $c), 'consultar' => fn (array $c) => Escenario::peticion([], $c, 'GET', "/api/v1/notas-soap/$borrador"),
            'listar' => fn (array $c) => Escenario::peticion([], $c, 'GET', '/api/v1/expedientes/exp-carlos/notas-soap'),
            'firmar' => fn (array $c) => Escenario::peticion([], $c, 'POST', "/api/v1/notas-soap/$borrador/firma"), 'corregir' => fn (array $c) => Escenario::peticionCorreccion($firmada, [], $c)];
    };
    $tabla = [ // identidad => estados esperados en el orden registrar, consultar, listar, firmar, corregir
        'dra-mendez (autora, asignada)' => [[], [201, 200, 200, 200, 201]],
        'dr-lopez (médico sin asignación)' => [['authorization' => 'Bearer token-lopez'], [403, 200, 200, 403, 403]],
        'enf-ana (enfermería)' => [['authorization' => 'Bearer token-ana'], [403, 200, 200, 403, 403]],
        'token inválido' => [['authorization' => 'Bearer token-falso'], [401, 401, 401, 401, 401]],
    ];
    foreach ($tabla as $identidad => [$cabeceras, $esperado]) {
        $e = new Escenario();
        igual($esperado, array_values(array_map(static fn (callable $op): int => $e->kernel->manejar($op($cabeceras))->estado, $operaciones($e))), $identidad);
    }
});

prueba('CN-04', 'Errores: cada código del contrato que faltaba afirmar (M2 de la semana 14) aparece con su estado HTTP', function () use ($http): void {
    $id = ($k = (new Escenario())->kernel)->manejar(Escenario::peticion())->cuerpo['clinical_note_id'];
    $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma"));
    igual([
        [400, 'tenant_requerido'], [401, 'token_invalido'], [404, 'ruta_no_encontrada'], [405, 'metodo_no_permitido'], [404, 'nota_no_encontrada'], [409, 'nota_firmada'],
    ], [
        $http(Escenario::peticion([], ['x-tenant-id' => ''])), $http(Escenario::peticion([], ['authorization' => ''])), $http(Escenario::peticion([], [], 'GET', '/api/v1/notas')),
        $http(Escenario::peticion([], [], 'DELETE', "/api/v1/notas-soap/$id")), $http(Escenario::peticion([], [], 'GET', '/api/v1/notas-soap/no-existe')),
        [($r = $k->manejar(Escenario::peticion([], [], 'POST', "/api/v1/notas-soap/$id/firma")))->estado, $r->cuerpo['error']['code'] ?? null],
    ], 'códigos de error');
    // 503: el punto de entrada real sin base de datos, en un proceso aparte (proc_open con lista: sin shell, igual en Windows)
    $guion = tempnam(sys_get_temp_dir(), 'cn04');
    file_put_contents($guion, '<?php putenv("NOTASSOAP_ALMACEN=pdo"); putenv("NOTASSOAP_DB_DSN=sqlite:/no/existe/base.db"); chdir(' . var_export(dirname(__DIR__, 2), true)
        . '); $_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["REQUEST_URI"] = "/api/v1/notas-soap/x"; require "public/index.php";');
    $proceso = proc_open([PHP_BINARY, '-d', 'display_errors=0', $guion], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
    $salida = (string) stream_get_contents($tubos[1]);
    proc_close($proceso);
    unlink($guion);
    afirmar(str_contains($salida, 'servicio_no_disponible'), 'sin base de datos: 503 servicio_no_disponible; salida: ' . substr($salida, 0, 120));
});

prueba('CN-05', 'RNF-07: el registro responde en menos de 2 s (p95) sin red; medido en memoria sobre 200 registros por HTTP', function (): void {
    $k = (new Escenario())->kernel;
    $tiempos = [];
    for ($i = 0; $i < 200; $i++) {
        $t = hrtime(true);
        $k->manejar(Escenario::peticion([], ['idempotency-key' => "rnf07-clave-$i"]));
        $tiempos[] = (hrtime(true) - $t) / 1e6;
    }
    sort($tiempos);
    $p95 = $tiempos[(int) ceil(0.95 * count($tiempos)) - 1];
    afirmar($p95 < 2000, sprintf('p95 = %.3f ms', $p95));
});
