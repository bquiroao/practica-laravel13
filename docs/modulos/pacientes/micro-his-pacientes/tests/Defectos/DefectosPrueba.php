<?php

declare(strict_types=1);

/*
 * Semana 15 · Defectos ABIERTOS. Cada prueba afirma el comportamiento CORRECTO y hoy FALLA: es la
 * reproducción del defecto. Solo corren con  php tests/ejecutar.php --defectos  (no bloquean la suite).
 * Cuando un defecto se corrige, su prueba pasa y se mueve a la suite normal (regresión).
 * Registro: docs/modulos/pacientes/semana-15/registro-defectos.md
 */

use Pacientes\Presentacion\Http\Solicitud;
use Pacientes\Pruebas\Dobles\Escenario;

const DEF_RUTA = '/api/v1/pacientes';

$defPedir = static fn (object $e, string $metodo, string $token, string $hospital, ?array $cuerpo = null, array $consulta = []) =>
    $e->kernel->manejar(Escenario::http($metodo, DEF_RUTA, $token, $cuerpo, $consulta, [], $hospital));

/** Alta en un sistema vacío con el DPI y el teléfono indicados → estado HTTP. */
$defAlta = static fn (array $cambios): int => $defPedir(Escenario::crear(), 'POST', 'token-admision-oriente', Escenario::H_ORIENTE,
    array_merge(Escenario::json(Escenario::datosAna()), $cambios))->estado;

prueba('DEF-01', '[Crítico · AR-02] El 409 por un DPI registrado en OTRO hospital no debe revelar su código ni su documento; hoy devuelve P-000001 y *********0101', function () use ($defPedir): void {
    $e = Escenario::crear();
    $defPedir($e, 'POST', 'token-admision-oriente', Escenario::H_ORIENTE, Escenario::json(Escenario::datosAna()));
    $r = $defPedir($e, 'POST', 'token-admision-norte', Escenario::H_NORTE, Escenario::json(Escenario::datosAna()));
    igual([409, ['error' => 'PACIENTE_EXISTENTE', 'codigo' => 'EN_OTRO_HOSPITAL']], [$r->estado, $r->cuerpo], 'respuesta a hosp-norte');
});

prueba('DEF-02', '[Mayor · AR-03] Una homónima de OTRO hospital no es candidata de POSIBLE_DUPLICADO; hoy hosp-norte recibe 409 con el código de hosp-oriente', function () use ($defPedir): void {
    $e = Escenario::crear();
    $sinDoc = Escenario::json(Escenario::datosAna(['tipo_documento' => null, 'numero_documento' => null]));
    $defPedir($e, 'POST', 'token-admision-oriente', Escenario::H_ORIENTE, Escenario::json(Escenario::datosAna()));
    $r = $defPedir($e, 'POST', 'token-admision-norte', Escenario::H_NORTE, $sinDoc);
    igual([201, false], [$r->estado, str_contains((string) json_encode($r->cuerpo), 'P-000001')], 'registro en hosp-norte y fuga del código');
});

prueba('DEF-03', '[Mayor · AR-01] Fuera de desarrollo, el punto de entrada real NO debe aceptar tokens de demostración (401); hoy los acepta', function (): void {
    $anterior = getenv('MICROHIS_ENTORNO');
    putenv('MICROHIS_ENTORNO');                       // sin entorno declarado = no es desarrollo
    try {
        $kernel = crearKernel(['persistencia' => 'memoria'] + (require dirname(__DIR__, 2) . '/config/config.php'));
        $r = $kernel->manejar(new Solicitud('GET', DEF_RUTA, ['Authorization' => 'Bearer token-supervisor-oriente', 'X-Tenant-ID' => 'hosp-oriente'],
            ['nombre' => 'lopez']));
        igual(401, $r->estado, 'token de demostración en un arranque sin MICROHIS_ENTORNO=desarrollo');
    } finally {
        $anterior === false ? putenv('MICROHIS_ENTORNO') : putenv("MICROHIS_ENTORNO=$anterior");
    }
});

prueba('DEF-04', '[Mayor · AR-04 · requiere decisión] Sin leer_protegido, buscar el DPI de una protegida no debe distinguirse de un DPI inexistente; hoy da 1 frente a 0', function () use ($defPedir): void {
    $e = Escenario::crear();
    $defPedir($e, 'POST', 'token-supervisor-oriente', Escenario::H_ORIENTE,
        Escenario::json(Escenario::datosAna(), ['identidad_protegida' => true, 'motivo' => 'Orden judicial de protección']));
    $total = static fn (string $dpi): int => $defPedir($e, 'GET', 'token-clinico-oriente', Escenario::H_ORIENTE, null,
        ['tipo_documento' => 'DPI', 'numero_documento' => $dpi])->cuerpo['total'];
    igual($total(Escenario::DPI_NUEVO), $total(Escenario::DPI_ANA), 'total para el DPI de la protegida frente a un DPI que no existe');
});

prueba('DEF-05', '[Menor · AR-07] Un DPI con dígito verificador incorrecto o municipio inexistente debe dar 422; hoy se acepta y esquiva el bloqueo por documento', function () use ($defAlta): void {
    $aceptados = [];
    // 12345678|0|0101: el verificador debería ser 9 · 12345678|9|0199: verificador correcto, pero el departamento 01 tiene 17 municipios
    foreach (['1234567800101' => 'verificador', '1234567890199' => 'municipio 99'] as $dpi => $caso) {
        if ($defAlta(['documento' => ['tipo' => 'DPI', 'numero' => (string) $dpi]]) !== 422) {
            $aceptados[] = "$dpi ($caso)";
        }
    }
    igual([], $aceptados, 'DPI inválidos aceptados');
});

prueba('DEF-06', '[Menor · nuevo S15] El teléfono debe tener de 8 a 15 DÍGITOS; hoy «1      2» (2 dígitos y 6 espacios) se acepta', function () use ($defAlta): void {
    $aceptados = [];
    foreach (['1      2', '+5       0', '12 34 56 7'] as $telefono) {
        if ($defAlta(['telefono' => $telefono]) !== 422) {
            $aceptados[] = $telefono;
        }
    }
    igual([], $aceptados, 'teléfonos con menos de 8 dígitos aceptados');
});
