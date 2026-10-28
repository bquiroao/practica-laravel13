<?php

declare(strict_types=1);

/**
 * Semana 10 · ¿Qué pasa si el teléfono reintenta después de un corte de red?
 * Repite la petición contra el kernel real del Micro-HIS (adaptadores InMemory de producción), como lo haría
 * un teléfono que no recibió la primera respuesta, y mide qué cambia: prescripciones, anulaciones y trazas.
 *
 * Uso: php herramientas/reintento.php   (desde docs/modulos/prescripciones-alergias/semana-10)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-prescripciones';
require $micro . '/bootstrap/contenedor.php';

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Solicitud;

$config = require $micro . '/config/config.php';
$config['registro_acceso'] = 'ninguno';

/** Un sistema nuevo por caso, con un Expediente que se puede «cortar». */
$preparar = static function () use ($config, $micro): array {
    $puertos = FabricaAdaptadores::memoria(require $micro . '/database/datos-demo.php', $config);
    $expediente = new class ($puertos['alergias']) implements FuenteAlergias {
        public bool $caido = false;

        public function __construct(private readonly FuenteAlergias $interna)
        {
        }

        public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
        {
            return $this->caido ? throw new FuenteAlergiasNoDisponible('corte') : $this->interna->alergiasDe($paciente, $hospital);
        }
    };
    $puertos['alergias'] = $expediente;
    $kernel = construirKernel(construirCasosDeUso($puertos), new AutenticadorTokenDemo($config['tokens_demo']));

    return [$kernel, $puertos['repositorio'], $puertos['auditoria'], $expediente];
};
$pedir = static function ($kernel, string $metodo, string $ruta, ?array $cuerpo = null, ?string $clave = null): string {
    $cabeceras = ['authorization' => 'Bearer token-dr-lopez', 'x-tenant-id' => 'hosp-norte'] + ($clave !== null ? ['idempotency-key' => $clave] : []);
    $r = $kernel->manejar(new Solicitud($metodo, "/api/v1/prescripciones$ruta", $cabeceras, $cuerpo === null ? '' : (string) json_encode($cuerpo)));

    return $r->estado . ' ' . ($r->cuerpo['codigo'] ?? $r->cuerpo['error'] ?? '');
};
$contar = static fn ($repositorio, $auditoria): array => [
    count($repositorio->todas()),
    count(array_filter($repositorio->todas(), static fn ($p): bool => $p->aArreglo()['estado'] === 'ANULADA')),
    count($auditoria->movimientos()),
];

$item = static fn (int $dosis = 500): array => ['medicamento' => 'PARA500', 'dosis' => $dosis, 'unidad' => 'mg', 'frecuencia_horas' => 6, 'via' => 'oral', 'dias' => 5];
$emision = static fn (int $dosis = 500): array => ['paciente' => 'P-005', 'items' => [$item($dosis)]];

$casos = [];

[$k, $repo, $aud] = $preparar();
$casos[] = ['Repetir la verificación previa', $pedir($k, 'POST', '/verificaciones', $emision()), $pedir($k, 'POST', '/verificaciones', $emision()), ...$contar($repo, $aud)];

[$k, $repo, $aud] = $preparar();
$casos[] = ['Repetir «Emitir» con la MISMA clave', $pedir($k, 'POST', '', $emision(), 'm-1'), $pedir($k, 'POST', '', $emision(), 'm-1'), ...$contar($repo, $aud)];

[$k, $repo, $aud] = $preparar();
$casos[] = ['Repetir «Emitir» con una clave NUEVA (anti-patrón)', $pedir($k, 'POST', '', $emision(), 'm-1'), $pedir($k, 'POST', '', $emision(), 'm-2'), ...$contar($repo, $aud)];

[$k, $repo, $aud, $exp] = $preparar();
$exp->caido = true;
$primera = $pedir($k, 'POST', '', $emision(), 'm-1');
$exp->caido = false;
$casos[] = ['Expediente caído, luego vuelve (misma clave)', $primera, $pedir($k, 'POST', '', $emision(), 'm-1'), ...$contar($repo, $aud)];

[$k, $repo, $aud] = $preparar();
$casos[] = ['Misma clave, pero el médico cambió la dosis', $pedir($k, 'POST', '', $emision(), 'm-1'), $pedir($k, 'POST', '', $emision(1000), 'm-1'), ...$contar($repo, $aud)];

[$k, $repo, $aud] = $preparar();
$pedir($k, 'POST', '', $emision(), 'm-1');
$casos[] = ['Repetir «Anular» con el mismo motivo', $pedir($k, 'POST', '/1/anulacion', ['motivo' => 'Se registró una alergia nueva']),
    $pedir($k, 'POST', '/1/anulacion', ['motivo' => 'Se registró una alergia nueva']), ...$contar($repo, $aud)];

[$k, $repo, $aud] = $preparar();
$pedir($k, 'POST', '', $emision(), 'm-1');
$casos[] = ['Repetir la consulta', $pedir($k, 'GET', '/1'), $pedir($k, 'GET', '/1'), ...$contar($repo, $aud)];

$columnas = static fn (string $a, string $b, string $c, string $d, string $e, string $f): string =>
    mb_str_pad($a, 52) . ' ' . mb_str_pad($b, 30) . ' ' . mb_str_pad($c, 34) . ' ' . mb_str_pad($d, 5, ' ', STR_PAD_LEFT) . ' '
    . mb_str_pad($e, 8, ' ', STR_PAD_LEFT) . ' ' . mb_str_pad($f, 6, ' ', STR_PAD_LEFT) . PHP_EOL;
echo $columnas('Caso (la misma intención, dos peticiones)', '1.ª respuesta', '2.ª respuesta', 'Presc', 'Anuladas', 'Trazas');
echo str_repeat('-', 140) . PHP_EOL;
foreach ($casos as [$caso, $r1, $r2, $presc, $anuladas, $trazas]) {
    echo $columnas($caso, $r1, $r2, (string) $presc, (string) $anuladas, (string) $trazas);
}
