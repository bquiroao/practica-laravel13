<?php
declare(strict_types=1);

// Prueba de contrato: los MISMOS casos contra RepositorioMedicamentosPdo y RepositorioMedicamentosEnMemoria.
// Con --sin-bd solo se ejecuta contra InMemory.

use Catalogo\Application\MedicamentoVista;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Modelo\ClaveEquivalencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Repositorio\RepositorioMedicamentos;
use Catalogo\Persistence\DatosEjemplo;
use Catalogo\Persistence\GeneradorUuidV4;
use Catalogo\Persistence\RepositorioMedicamentosEnMemoria;

$adaptadores = ['InMemory' => function (): RepositorioMedicamentos {
    $repo = new RepositorioMedicamentosEnMemoria();
    DatosEjemplo::cargar($repo, new GeneradorUuidV4());
    return $repo;
}];
if (getenv('CATALOGO_SIN_BD') !== '1') {
    $adaptadores = ['PDO' => fn (): RepositorioMedicamentos => BaseDePrueba::reiniciada()] + $adaptadores;
}

$hoy = Fabrica::fecha(Fabrica::HOY);
$codigo = fn (string $c) => CodigoMedicamento::desdeTexto($c);
$buscar = fn (RepositorioMedicamentos $r, array $f, string $h = Fabrica::NORTE) => array_map(
    fn (Medicamento $m) => $m->codigo->valor . 'v' . $m->version,
    $r->buscarVigentes($h, CriterioBusqueda::desdeFiltros($f, $hoy)),
);

// Cada caso recibe un repositorio recién cargado con los datos ficticios.
$casos = [
    'búsqueda por texto, mayúsculas y acentos' => function (RepositorioMedicamentos $r) use ($buscar) {
        Afirmar::igual(['MED-000103v1', 'MED-000102v1'], $buscar($r, ['q' => 'AMOXI']), 'AMOXI');
        Afirmar::igual(['MED-000102v1'], $buscar($r, ['q' => 'CÁPSULA']), 'CÁPSULA (minúscula multibyte)');
        Afirmar::igual([], $buscar($r, ['q' => 'capsula']), 'sin acento no coincide (limitación documentada)');
    },
    '% _ y ! son texto literal' => function (RepositorioMedicamentos $r) use ($buscar) {
        Afirmar::igual(['MED-000106v1'], $buscar($r, ['q' => '% s']), '% s');
        Afirmar::igual([], $buscar($r, ['q' => 'a_o']), 'a_o');
        Afirmar::igual([], $buscar($r, ['q' => '!%']), '!%');
    },
    'vigencia a una fecha y por forma' => function (RepositorioMedicamentos $r) use ($buscar) {
        Afirmar::igual(['MED-000104v1'], $buscar($r, ['q' => 'ibupro', 'fecha' => '2026-06-30']), 'ibuprofeno en junio');
        Afirmar::igual(['MED-000104v2'], $buscar($r, ['q' => 'ibupro', 'fecha' => '2026-07-01']), 'ibuprofeno en julio');
        Afirmar::igual(['MED-000105v1'], $buscar($r, ['q' => 'omepra', 'fecha' => '2026-08-31']), 'omeprazol antes de la baja');
        Afirmar::igual(['MED-000104v2', 'MED-000101v1'], $buscar($r, ['forma' => 'TABLETA']), 'tabletas por nombre');
        Afirmar::igual(['MED-000101v1'], $buscar($r, ['q' => 'para', 'forma' => 'TABLETA'], Fabrica::SUR), 'Sur');
    },
    'límite de 50 resultados con el mismo orden' => function (RepositorioMedicamentos $r) use ($buscar) {
        for ($i = 1; $i <= 55; $i++) {
            $r->agregar(Fabrica::medicamento(sprintf('MED-%06d', 500 + $i), '2026-01-01', ['nombre' => sprintf('Zeta prueba %02d', 56 - $i), 'principio_activo' => "Principio ficticio $i"]));
        }
        $lista = $buscar($r, ['q' => 'zeta prueba']);
        Afirmar::igual(50, count($lista), 'cantidad');
        Afirmar::igual(['MED-000555v1', 'MED-000554v1'], array_slice($lista, 0, 2), 'primeros por nombre');
    },
    'consulta por código, historial y equivalencia' => function (RepositorioMedicamentos $r) use ($codigo, $hoy) {
        Afirmar::igual(2, $r->vigentePorCodigo(Fabrica::NORTE, $codigo('MED-000104'), $hoy)?->version, 'vigente hoy');
        Afirmar::igual(null, $r->vigentePorCodigo(Fabrica::NORTE, $codigo('MED-000105'), $hoy), 'dado de baja');
        $vista = MedicamentoVista::lista($r->historial(Fabrica::NORTE, $codigo('MED-000104')));
        Afirmar::igual([[1, '2026-01-01', '2026-07-01', 'Reemplazada por la versión 2'], [2, '2026-07-01', null, null]],
            array_map(fn ($v) => [$v['version'], $v['vigente_desde'], $v['vigente_hasta'], $v['motivo_cierre']], $vista), 'historial');
        $clave = Fabrica::ficha()->clave();
        Afirmar::igual('MED-000102', $r->equivalenteVigente(Fabrica::NORTE, $clave, new Vigencia($hoy))?->valor, 'equivalente');
        Afirmar::igual(null, $r->equivalenteVigente(Fabrica::NORTE, $clave, new Vigencia($hoy), $codigo('MED-000102')), 'excluido');
        Afirmar::igual(null, $r->equivalenteVigente(Fabrica::SUR, $clave, new Vigencia($hoy)), 'otro hospital');
        Afirmar::igual('MED-000107', $r->siguienteCodigo(Fabrica::NORTE)->valor, 'siguiente Norte');
        Afirmar::igual('MED-000102', $r->siguienteCodigo(Fabrica::SUR)->valor, 'siguiente Sur');
    },
    'restricciones con los mismos códigos de error' => function (RepositorioMedicamentos $r) use ($codigo) {
        $v1 = $r->historial(Fabrica::NORTE, $codigo('MED-000103'))[0];
        [$cerrada, $igualA102] = $v1->nuevaVersion(Fabrica::uuid(), Fabrica::ficha(), Fabrica::fecha('2026-11-01'), 'qf.norte');
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $r->reemplazarVigente($cerrada, $igualA102), 'DUPLICADO');
        Afirmar::igual(null, $r->historial(Fabrica::NORTE, $codigo('MED-000103'))[0]->vigencia->hasta, 'atómico: sigue abierta');
        $otra = Fabrica::medicamento('MED-000103', '2026-03-01', ['principio_activo' => 'Cefalexina']);
        $solape = new Medicamento($otra->id, Fabrica::NORTE, $otra->codigo, 2, $otra->ficha, $otra->vigencia, 'qf.norte');
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $r->agregar($solape), 'SOLAPE_VIGENCIA');
        $repetida = new Medicamento(Fabrica::uuid(), Fabrica::NORTE, $codigo('MED-000104'), 2, $otra->ficha, new Vigencia(Fabrica::fecha('2030-01-01')), 'qf.norte');
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $r->agregar($repetida), 'CONFLICTO_CONCURRENTE');
        $baja = $r->historial(Fabrica::NORTE, $codigo('MED-000105'))[0];
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $r->cerrar($baja), 'CONFLICTO_CONCURRENTE');
    },
    'eliminar borra solo ese código de ese hospital' => function (RepositorioMedicamentos $r) use ($codigo, $hoy) {
        $r->eliminar(Fabrica::SUR, $codigo('MED-000101'));
        Afirmar::igual([], $r->historial(Fabrica::SUR, $codigo('MED-000101')), 'Sur borrado');
        Afirmar::igual(1, count($r->historial(Fabrica::NORTE, $codigo('MED-000101'))), 'Norte intacto');
    },
];

$pruebas = [];
foreach ($casos as $nombre => $caso) {
    foreach ($adaptadores as $adaptador => $crear) {
        $pruebas["[$adaptador] $nombre"] = fn () => $caso($crear());
    }
}
return $pruebas;
