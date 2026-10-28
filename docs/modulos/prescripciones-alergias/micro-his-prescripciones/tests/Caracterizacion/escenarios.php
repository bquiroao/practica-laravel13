<?php

declare(strict_types=1);

/*
 * Semana 7 · Caracterización: ejecuta 32 escenarios contra el kernel y los casos de uso (adaptadores InMemory +
 * decoradores de fallas) y devuelve lo OBSERVABLE: estado, cuerpo, Location, trazas y cuántas prescripciones quedaron.
 * Se grabó en esperado.json ANTES del refactor (bin/caracterizar.php); CAR-01 exige que después sea idéntico.
 */

/** @return array<string, array<string, mixed>> */
function caracterizar(): array
{
    $item = static fn (string $m, float|int $d = 500, int $f = 8, string $via = 'oral', int $dias = 5): array => Escenario::item($m, $d, $f, $via, $dias);
    $just = 'Sin alternativa: cultivo sensible solo a cefalosporinas';
    $http = [
        'previa P-001 amoxicilina (directa)' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-001', 'items' => [$item('AMOX500')]]],
        'previa P-002 cefalexina (cruzada)' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-002', 'items' => [$item('CEFA500')]]],
        'previa P-003 sin alergias evaluadas' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-003', 'items' => [$item('AMOX500')]]],
        'previa P-004 ibuprofeno (cruzada grave)' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-004', 'items' => [$item('IBU400', 400)]]],
        'previa P-005 paracetamol' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$item('PARA500')]]],
        'previa dosis excedida' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$item('PARA500', 1000, 4)]]],
        'previa datos inválidos' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$item('RANI150'), $item('PARA500', 0, 5, 'iv', 0)]]],
        'previa paciente ajeno' => ['POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-900', 'items' => [$item('PARA500')]]],
        'emitir P-005 paracetamol' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'k1'],
        'reintento igual' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'k1'],
        'misma clave, otro contenido' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('IBU400', 400)]], 'k1'],
        'misma clave, datos inválidos' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('PARA500', 0)]], 'k1'],
        'emitir directa con justificación' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-001', 'items' => [$item('AMOX500')], 'justificacion' => $just], 'k2'],
        'emitir directa por nombre (P-007)' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-007', 'items' => [$item('AMOX500')]], 'k3'],
        'emitir cruzada sin justificación' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [$item('CEFA500')]], 'k4'],
        'emitir cruzada justificación 19' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => str_repeat('x', 19)], 'k5'],
        'emitir cruzada justificación 20 con espacios' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [$item('CEFA500')],
            'justificacion' => '  ' . str_repeat('x', 20) . '  '], 'k6'],
        'emitir cruzada sin override (dr-ruiz)' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => $just], 'k7', 'token-dr-ruiz'],
        'emitir cruzada grave con justificación' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-004', 'items' => [$item('IBU400', 400)], 'justificacion' => $just], 'k8'],
        'emitir cruzada unknown' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-006', 'items' => [$item('CEFA500')], 'justificacion' => $just], 'k9'],
        'emitir sin alergias evaluadas' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-003', 'items' => [$item('AMOX500')]], 'k10'],
        'emitir sin alergias evaluadas, confirmada' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-003', 'items' => [$item('AMOX500')], 'confirma_sin_alergias' => true], 'k11'],
        'emitir confirmada con alergias evaluadas' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('AMOX500')], 'confirma_sin_alergias' => true], 'k12'],
        'emitir justificación sin advertencia' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('PARA500', 1000, 6)], 'justificacion' => $just], 'k13'],
        'emitir dos ítems, uno cruzado' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [$item('PARA500'), $item('CEFA500')], 'justificacion' => $just], 'k14'],
        'emitir sin permiso (farmacia)' => ['POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], 'k15', 'token-farmacia-norte'],
        'consultar 1' => ['GET', '/api/v1/prescripciones/1', null, null, 'token-farmacia-norte'],
        'consultar 2 (advertencia justificada)' => ['GET', '/api/v1/prescripciones/2', null, null, 'token-farmacia-norte'],
        'consultar 3 (confirmada)' => ['GET', '/api/v1/prescripciones/3', null, null, 'token-farmacia-norte'],
    ];
    $e = new Escenario();
    $k = $e->kernel();
    $salida = [];
    foreach ($http as $nombre => $p) {
        [$metodo, $ruta, $cuerpo] = $p;
        $r = $k->manejar(Escenario::http($metodo, $ruta, $cuerpo, $p[4] ?? 'token-dr-lopez', 'hosp-norte', isset($p[3]) ? ['idempotency-key' => $p[3]] : []));
        $salida[$nombre] = ['estado' => $r->estado, 'cuerpo' => $r->cuerpo, 'location' => $r->cabeceras['Location'] ?? null,
            'trazas' => $e->auditoria->acciones(), 'prescripciones' => count($e->repositorio->todas())];
    }

    $casos = [
        'fuente caída' => static function (): array {
            $x = new Escenario(new FuenteAlergiasCaida());

            return [$x, $x->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')], confirma: true, clave: 'f1'))];
        },
        'traza falla en la transacción' => static function (): array {
            $x = new Escenario();
            $x->auditoria->fallar = true;

            return [$x, $x->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'f2'))];
        },
        'carrera de idempotencia' => static function (): array {
            $x = new Escenario();
            $s = Escenario::solicitud('P-005', [Escenario::item('PARA500')], clave: 'f3');
            $x->repositorio->ganadoraConcurrente = new \Prescripciones\Dominio\Modelo\Prescripcion(null, 'hosp-norte', 'P-005', 'dr-lopez', 'f3', $s->huella(),
                $s->items, new \Prescripciones\Dominio\Modelo\ResultadoVerificacion('SIN_HALLAZGOS', []), [], null, false, new \DateTimeImmutable('2026-10-27T15:00:00Z'));

            return [$x, $x->casos['emitir']->ejecutar($s)];
        },
    ];
    foreach ($casos as $nombre => $caso) {
        [$x, $r] = $caso();
        $salida[$nombre] = ['tipo' => $r->tipo, 'codigo' => $r->codigo, 'datos' => $r->datos, 'trazas' => $x->auditoria->acciones(),
            'prescripciones' => count($x->repositorio->todas())];
    }

    return $salida;
}
