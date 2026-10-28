<?php

declare(strict_types=1);

/*
 * Semana 6 · Demo del cambio práctico «anular con motivo», de punta a punta y SIN base (adaptadores InMemory).
 * Historia: se emite cefalexina a P-002 con advertencia justificada; después el Expediente registra una alergia nueva
 * a cefalosporinas y el médico anula. Al final el Expediente cae: emitir se bloquea, pero anular sigue funcionando.
 *   php bin/demo-anulacion.php
 */

require dirname(__DIR__) . '/bootstrap/contenedor.php';

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Solicitud;

$config = require dirname(__DIR__) . '/config/config.php';
$puertos = FabricaAdaptadores::memoria(require dirname(__DIR__) . '/database/datos-demo.php', $config);
$expediente = new class ($puertos['alergias']) implements FuenteAlergias {
    public bool $caido = false;

    public function __construct(private readonly FuenteAlergias $interna)
    {
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        return $this->caido ? throw new FuenteAlergiasNoDisponible('caído') : $this->interna->alergiasDe($paciente, $hospital);
    }
};
$puertos['alergias'] = $expediente;
$kernel = construirKernel(construirCasosDeUso($puertos), new AutenticadorTokenDemo($config['tokens_demo']));

$item = static fn (string $m, int $dosis = 500): array => ['medicamento' => $m, 'dosis' => $dosis, 'unidad' => 'mg', 'frecuencia_horas' => 6, 'via' => 'oral', 'dias' => 7];
$pedir = static function (string $titulo, string $metodo, string $ruta, ?array $cuerpo, string $token = 'token-dr-lopez', ?string $clave = null) use ($kernel): array {
    $cabeceras = ['authorization' => "Bearer $token", 'x-tenant-id' => $token === 'token-dr-sur' ? 'hosp-sur' : 'hosp-norte'] + ($clave !== null ? ['idempotency-key' => $clave] : []);
    $r = $kernel->manejar(new Solicitud($metodo, $ruta, $cabeceras, $cuerpo === null ? '' : (string) json_encode($cuerpo)));
    $cuerpoVisible = $r->cuerpo;
    unset($cuerpoVisible['emitida_en'], $cuerpoVisible['items'], $cuerpoVisible['hallazgos']);
    if (is_array($cuerpoVisible['verificacion'] ?? null)) {
        unset($cuerpoVisible['verificacion']['hallazgos']);
    }
    if (isset($cuerpoVisible['anulacion']['anulada_en'])) {
        $cuerpoVisible['anulacion']['anulada_en'] = '…';
    }
    printf("\n%s\n   %s %s → %s\n   %s\n", $titulo, $metodo, $ruta, $r->lineaDeEstado(), json_encode($cuerpoVisible, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $r->cuerpo;
};

echo 'Micro-HIS Prescripciones · demo del cambio práctico de la semana 6 (anular con motivo)' . PHP_EOL;
$id = $pedir('1. Se emite cefalexina a P-002 (alérgico a penicilina) con advertencia justificada', 'POST', '/api/v1/prescripciones',
    ['paciente' => 'P-002', 'items' => [$item('CEFA500')], 'justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas'], clave: 'demo-6')['id'];
$pedir('2. Motivo demasiado corto', 'POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'reacción']);
$pedir('3. Farmacia no puede anular', 'POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Se registró una alergia nueva'], 'token-farmacia-norte');
$pedir('4. Otro hospital: igual que inexistente', 'POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Se registró una alergia nueva'], 'token-dr-sur');
$pedir('5. El médico anula con motivo', 'POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Se registró una alergia a cefalosporinas después de emitir']);
$pedir('6. Farmacia consulta: ANULADA, con la anulación; lo emitido no cambió', 'GET', "/api/v1/prescripciones/$id", null, 'token-farmacia-norte');
$pedir('7. Una segunda anulación se rechaza', 'POST', "/api/v1/prescripciones/$id/anulacion", ['motivo' => 'Otra vez la misma anulación']);
$id2 = $pedir('8. Se emite paracetamol a P-005', 'POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('PARA500')]], clave: 'demo-6b')['id'];
$expediente->caido = true;
$pedir('9. Cae el Expediente: emitir se BLOQUEA (fail-closed)', 'POST', '/api/v1/prescripciones', ['paciente' => 'P-005', 'items' => [$item('IBU400', 400)]], clave: 'demo-6c');
$pedir('10. … pero anular SIGUE funcionando: no depende del Expediente', 'POST', "/api/v1/prescripciones/$id2/anulacion", ['motivo' => 'Paciente refiere reacción previa al paracetamol']);
