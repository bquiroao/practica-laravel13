<?php

declare(strict_types=1);

/**
 * Semana 10 · ¿Qué pasa si el teléfono reintenta después de un corte de red?
 * Repite la MISMA petición contra el kernel real del Micro-HIS (adaptadores InMemory de producción),
 * como lo haría un teléfono que no recibió la primera respuesta, y mide qué cambia en la bitácora.
 *
 * Uso: php herramientas/reintento.php   (desde docs/modulos/gobernanza-auditoria/semana-10)
 * El registro de acceso va a stderr; para ver solo la tabla: php herramientas/reintento.php 2>/dev/null
 */
$micro = dirname(__DIR__, 2) . '/micro-his-auditoria';
require $micro . '/bootstrap/contenedor.php';

use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Presentacion\Http\Solicitud;

$config = require $micro . '/config/config.php';
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Guatemala')))->format('Y-m-d');

/** Un sistema nuevo con 60 movimientos de hoy en Oriente (más de una página de 50). */
$preparar = static function () use ($config, $hoy): array {
    $adaptadores = FabricaAdaptadores::enMemoria();
    $kernel = crearKernel($config, $adaptadores);
    for ($i = 1; $i <= 60; $i++) {
        $kernel->manejar(new Solicitud('POST', '/api/v1/auditoria/movimientos',
            ['Authorization' => 'Bearer token-svc-pacientes-oriente', 'X-Tenant-ID' => 'hosp-oriente'], [], [
                'event_id' => "ev-$i", 'hospital' => 'hosp-oriente', 'usuario' => 'u-' . ($i % 7), 'modulo' => 'pacientes',
                'accion' => 'PACIENTE_CONSULTADO', 'ocurrido_en' => sprintf('%sT%02d:%02d:00-06:00', $hoy, intdiv($i, 60), $i % 60),
                'resultado' => $i % 5 === 0 ? 'denegado' : 'exito', 'recurso' => 'paciente:P-' . sprintf('%06d', $i)]));
    }

    return [$kernel, $adaptadores->bitacora];
};
$pedir = static fn ($kernel, string $metodo, string $ruta, string $token, string $tenant, array $consulta = [], ?array $cuerpo = null) =>
    $kernel->manejar(new Solicitud($metodo, "/api/v1/auditoria$ruta", ['Authorization' => "Bearer $token", 'X-Tenant-ID' => $tenant], $consulta, $cuerpo));
/** [movimientos de los emisores, meta-auditorías] */
$contar = static function ($bitacora): array {
    $todos = $bitacora->todos();
    $meta = count(array_filter($todos, static fn ($m): bool => $m->emisor === 'auditoria'));

    return [count($todos) - $meta, $meta];
};
$rango = ['desde' => $hoy, 'hasta' => $hoy];

$casos = [
    'Repetir la consulta (primera página)' => static function () use ($preparar, $pedir, $contar, $rango): array {
        [$k, $b] = $preparar();
        $r1 = $pedir($k, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', $rango);
        $r2 = $pedir($k, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', $rango);
        $nuevas = array_values(array_diff(array_column($r2->cuerpo['movimientos'], 'id'), array_column($r1->cuerpo['movimientos'], 'id')));
        // La 1.ª consulta dejó su AUDITORIA_CONSULTADA en la cadena del hospital, con fecha de hoy: la 2.ª la ve primero.
        $primera = $r2->cuerpo['movimientos'][0];
        $texto = $nuevas === [] ? 'mismas 50 filas' : sprintf('%d fila nueva arriba: #%d %s', count($nuevas), $primera['id'], $primera['accion']);
        return [$r1->estado, $r2->estado, $texto, ...$contar($b)];
    },
    'Repetir «Cargar más» (mismo cursor)' => static function () use ($preparar, $pedir, $contar, $rango): array {
        [$k, $b] = $preparar();
        $cursor = $pedir($k, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', $rango)->cuerpo['cursor_siguiente'];
        $r1 = $pedir($k, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', $rango + ['cursor' => $cursor]);
        $r2 = $pedir($k, 'GET', '/movimientos', 'token-auditor-oriente', 'hosp-oriente', $rango + ['cursor' => $cursor]);
        $iguales = array_column($r1->cuerpo['movimientos'], 'id') === array_column($r2->cuerpo['movimientos'], 'id');
        return [$r1->estado, $r2->estado, ($iguales ? 'mismas ' : 'DISTINTAS ') . count($r2->cuerpo['movimientos']) . ' filas, sin duplicar ni saltar', ...$contar($b)];
    },
    'Repetir el detalle' => static function () use ($preparar, $pedir, $contar): array {
        [$k, $b] = $preparar();
        $r1 = $pedir($k, 'GET', '/movimientos/7', 'token-auditor-oriente', 'hosp-oriente');
        $r2 = $pedir($k, 'GET', '/movimientos/7', 'token-auditor-oriente', 'hosp-oriente');
        return [$r1->estado, $r2->estado, $r1->cuerpo === $r2->cuerpo ? 'mismo detalle' : 'DISTINTO', ...$contar($b)];
    },
    'Repetir la verificación de integridad' => static function () use ($preparar, $pedir, $contar): array {
        [$k, $b] = $preparar();
        $r1 = $pedir($k, 'POST', '/verificaciones', 'token-superadmin', '*', [], ['hospital' => 'hosp-oriente']);
        $r2 = $pedir($k, 'POST', '/verificaciones', 'token-superadmin', '*', [], ['hospital' => 'hosp-oriente']);
        return [$r1->estado, $r2->estado, $r1->cuerpo['estado'] . ' / ' . $r2->cuerpo['estado'], ...$contar($b)];
    },
];

printf("%-40s %4s %4s  %-44s %9s %6s\n", 'Caso (la misma petición dos veces)', '1.ª', '2.ª', 'Respuesta', 'Emisores', 'Meta');
echo str_repeat('-', 114) . PHP_EOL;
foreach ($casos as $nombre => $caso) {
    [$e1, $e2, $respuesta, $emisores, $meta] = $caso();
    printf("%-40s %4d %4d  %-44s %9d %6d\n", $nombre, $e1, $e2, $respuesta, $emisores, $meta);
}
echo str_repeat('-', 114) . PHP_EOL;
echo "Emisores = movimientos de los módulos (60 al empezar): un reintento nunca los cambia.\n";
echo "Meta = AUDITORIA_CONSULTADA / INTEGRIDAD_VERIFICADA: cada lectura que LLEGA al servidor deja la suya (R-08).\n";
