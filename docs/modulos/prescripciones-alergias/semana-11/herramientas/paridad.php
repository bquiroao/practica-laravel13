<?php

declare(strict_types=1);

/**
 * Semana 11 · Ejecuta paridad-secuencia.json contra el kernel REAL del Micro-HIS (adaptadores InMemory de producción,
 * con un Expediente que se puede «cortar», como en semana-10/herramientas/reintento.php) e imprime JSON:
 * los datos de demostración y la configuración (para comprobar que el prototipo usa los mismos), una huella por paso
 * y el estado final (prescripciones y trazas). paridad.cjs compara todo con la API simulada.
 */
$micro = dirname(__DIR__, 2) . '/micro-his-prescripciones';
require $micro . '/bootstrap/contenedor.php';

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Prescripciones\Presentacion\Http\Solicitud;

$config = require $micro . '/config/config.php';
$datos = require $micro . '/database/datos-demo.php';
$alergiaNueva = json_decode((string) stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);

$puertos = FabricaAdaptadores::memoria($datos, $config);
$memoria = $puertos['alergias'];
$expediente = new class ($memoria) implements FuenteAlergias {
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

$salida = [
    'datos' => ['pacientes' => $datos['pacientes'], 'alergias' => $datos['alergias'], 'medicamentos' => $datos['medicamentos'],
        'grupos' => $datos['grupos'], 'tokens' => $config['tokens_demo'], 'permisos' => $config['permisos']],
    'pasos' => [],
];
foreach (json_decode((string) file_get_contents(__DIR__ . '/paridad-secuencia.json'), true, flags: JSON_THROW_ON_ERROR) as $p) {
    if (isset($p['antes']['agregar_alergia'])) {
        $memoria->agregar($alergiaNueva['paciente'], new Alergia(...$alergiaNueva['alergia']));
    }
    if (isset($p['antes']['expediente'])) {
        $expediente->caido = $p['antes']['expediente'] === 'caido';
    }
    $cabeceras = [];
    if ($p['token'] !== '') {
        $cabeceras['authorization'] = 'Bearer ' . $p['token'];
    }
    $tenant = $p['tenant'] ?? 'hosp-norte';
    if ($tenant !== '') {
        $cabeceras['x-tenant-id'] = $tenant;
    }
    if (isset($p['clave'])) {
        $cabeceras['idempotency-key'] = $p['clave'];
    }
    $cuerpo = $p['cuerpoTexto'] ?? (isset($p['cuerpo']) ? (string) json_encode($p['cuerpo'], JSON_UNESCAPED_UNICODE) : '');
    $r = $kernel->manejar(new Solicitud($p['metodo'], '/api/v1/prescripciones' . $p['ruta'], $cabeceras, $cuerpo));
    $salida['pasos'][] = huella($r->estado, $r->cuerpo, isset($r->cabeceras['Location']));
}
$salida['final'] = [
    'prescripciones' => array_map(static fn ($p): array => [$p->id, $p->paciente, $p->estado], $puertos['repositorio']->todas()),
    'trazas' => array_map(static fn (array $t): string => "{$t['accion']} {$t['usuario']} {$t['recurso']} {$t['resultado']}", $puertos['auditoria']->movimientos()),
];
echo json_encode($salida, JSON_UNESCAPED_UNICODE);

/** El mismo cálculo que huella() en paridad.cjs. Sin fechas ni ids de traza: cambian en cada ejecución. */
function huella(int $estado, array $c, bool $location): array
{
    $hallazgo = static fn (array $h): string => implode(' ', [$h['tipo'], $h['medicamento'], $h['alergeno'], $h['severidad'], $h['bloquea'] ? 'bloquea' : 'no-bloquea', $h['reaccion'] ?? '-']);
    $item = static fn (array $i): string => implode(' ', [$i['medicamento'], (string) (float) $i['dosis'], $i['unidad'], $i['frecuencia_horas'], $i['via'], $i['dias']]);
    $v = is_array($c['verificacion'] ?? null) ? $c['verificacion'] : null;

    return [
        'estado' => $estado,
        'codigo' => $c['error'] ?? $c['codigo'] ?? null,
        'location' => $location,
        'campos' => $c['campos'] ?? null,
        'items_dosis' => isset($c['items']) && is_string($c['items'][0] ?? null) ? $c['items'] : null,
        'hallazgos' => isset($c['hallazgos']) ? array_map($hallazgo, $c['hallazgos']) : null,
        'id' => $c['id'] ?? null,
        'verificacion' => is_string($c['verificacion'] ?? null) ? $c['verificacion'] : null,
        'detalle' => $v === null ? null : [
            $c['estado'], $c['paciente'], $c['prescriptor'], array_map($item, $c['items']), $v['resultado'], array_map($hallazgo, $v['hallazgos']),
            $v['alergenos_evaluados'], $v['justificacion'], $v['confirma_sin_alergias'],
        ],
        'anulacion' => isset($c['anulacion']) ? [$c['anulacion']['motivo'], $c['anulacion']['anulada_por']] : null,
    ];
}
