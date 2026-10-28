<?php

declare(strict_types=1);

/*
 * Semana 15 · Defectos ABIERTOS (semana-15/registro-defectos.md). Cada prueba describe el comportamiento CORRECTO y por eso
 * FALLA mientras el defecto exista. No corren en la suite por defecto: php tests/ejecutar.php --defectos --solo=DEF
 * Al corregirse un defecto, su prueba pasa y se mueve a su grupo (como DEF-02, que quedó en CB-01 C4 y CB-02).
 */

use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Presentacion\Http\Solicitud;

$defItem = static fn (array $c = []): array => $c + ['medicamento' => 'PARA500', 'dosis' => 500, 'unidad' => 'mg', 'frecuencia_horas' => 8, 'via' => 'oral', 'dias' => 5];

prueba('DEF-01', 'AR-01 · Fuera de desarrollo, crearKernel NO acepta los tokens de demostración (debería responder 401)', function () use ($defItem): void {
    $config = Escenario::config();
    $config['persistencia'] = 'memoria';
    $config['registro_acceso'] = 'ninguno';
    putenv('PRESCRIPCIONES_ENTORNO=produccion');
    try {
        $r = crearKernel($config)->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$defItem()]]));
    } finally {
        putenv('PRESCRIPCIONES_ENTORNO');
    }
    igual(401, $r->estado, 'token-dr-lopez con PRESCRIPCIONES_ENTORNO=produccion');
});

prueba('DEF-03', 'AR-03 · Una alergia GRAVE registrada como «Amoxicilina trihidrato» advierte o bloquea la Amoxicilina', function () use ($defItem): void {
    $fuente = Escenario::fuenteContada();
    $fuente->agregar('P-005', new Alergia(null, 'Amoxicilina trihidrato', 'allergy', 'severe', 'active', 'anafilaxia'));
    $r = (new Escenario($fuente))->kernel()->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$defItem(['medicamento' => 'AMOX500'])]]));
    afirmar(in_array($r->cuerpo['codigo'] ?? null, ['ADVERTENCIA', 'BLOQUEO'], true), 'resultado ' . ($r->cuerpo['codigo'] ?? '?') . ': falso negativo');
});

prueba('DEF-04', 'AR-04 · «dias»: 7.0 es un entero válido según el contrato (JSON Schema) y debería aceptarse', function (): void {
    $k = (new Escenario())->kernel();
    $cuerpo = '{"paciente": "P-005", "items": [{"medicamento": "PARA500", "dosis": 500, "unidad": "mg", "frecuencia_horas": 8, "via": "oral", "dias": 7.0}]}';
    $r = $k->manejar(new Solicitud('POST', '/api/v1/prescripciones/verificaciones', ['authorization' => 'Bearer token-dr-lopez', 'x-tenant-id' => 'hosp-norte'], $cuerpo));
    igual(200, $r->estado, 'dias 7.0');
});

prueba('DEF-05', 'AR-05 · El reintento de una anulación que SÍ llegó dice quién anuló (200 idempotente, o 409 con anulada_por)', function () use ($defItem): void {
    $k = (new Escenario())->kernel();
    $id = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$defItem()]], cabeceras: ['idempotency-key' => 'def5']))->cuerpo['id'];
    $motivo = ['motivo' => 'Pauta duplicada con la de urgencias'];
    $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", $motivo));
    $r = $k->manejar(Escenario::http('POST', "/api/v1/prescripciones/$id/anulacion", $motivo));
    afirmar($r->estado === 200 || isset($r->cuerpo['anulada_por']), "reintento → {$r->estado} con las claves [" . implode(', ', array_keys($r->cuerpo)) . ']');
});

prueba('DEF-06', 'AR-06 · El hallazgo informa el NOMBRE del alérgeno, no solo su código', function () use ($defItem): void {
    $r = (new Escenario())->kernel()->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-001', 'items' => [$defItem(['medicamento' => 'CEFA500'])]]));
    igual('Amoxicilina', $r->cuerpo['hallazgos'][0]['alergeno_nombre'] ?? null, 'alergeno_nombre (alergeno = ' . ($r->cuerpo['hallazgos'][0]['alergeno'] ?? '?') . ')');
});

prueba('DEF-07', 'SQA M6 · Cada código de error del contrato lo emite alguna parte del servidor (ninguno documentado en vano)', function (): void {
    $raiz = dirname(__DIR__, 2);
    preg_match('/\n        error:\n          type: string\n          enum: \[([A-Z_,\s]+)\]/', (string) file_get_contents("$raiz/api/openapi.yaml"), $m);
    $codigo = '';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$raiz/src", FilesystemIterator::SKIP_DOTS)) as $f) {
        $codigo .= (string) file_get_contents((string) $f);
    }
    $sinEmisor = array_values(array_filter(array_map('trim', explode(',', $m[1] ?? '')), static fn (string $c): bool => $c !== '' && !str_contains($codigo, "'$c'")));
    igual([], $sinEmisor, 'códigos del contrato que nada en src/ emite');
});

prueba('DEF-08', 'NUEVO (caja negra, límites) · La dosis máxima diaria se aplica al TOTAL del medicamento: dos ítems de Paracetamol 1000 mg c/6 h (8000 mg/día) → 422', function () use ($defItem): void {
    $item = $defItem(['dosis' => 1000, 'frecuencia_horas' => 6]);
    $r = (new Escenario())->kernel()->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => 'P-005', 'items' => [$item, $item]]));
    igual([422, 'DOSIS_EXCEDE_MAXIMO'], [$r->estado, $r->cuerpo['error'] ?? $r->cuerpo['codigo'] ?? null], 'dos ítems de 4000 mg/día cada uno');
});
