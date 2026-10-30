<?php
declare(strict_types=1);

/*
 * Composition Root: el ÚNICO lugar que conoce las clases concretas y las conecta.
 * Presentation pide aquí los casos de uso; Application y Domain nunca ven PDO.
 */

use Catalogo\Application\BuscarMedicamentosVigentes;
use Catalogo\Application\ConsultarHistorial;
use Catalogo\Application\ConsultarVigentePorCodigo;
use Catalogo\Application\DarDeBajaMedicamento;
use Catalogo\Application\EliminarRegistroSinUso;
use Catalogo\Application\RegistrarMedicamento;
use Catalogo\Application\RegistrarNuevaVersion;
use Catalogo\Domain\Puertos\Reloj;
use Catalogo\Persistence\ConexionPdo;
use Catalogo\Persistence\GeneradorUuidV4;
use Catalogo\Persistence\RelojSistema;
use Catalogo\Persistence\RepositorioMedicamentosPdo;
use Catalogo\Persistence\UsoPrescripcionesPdo;

require_once __DIR__ . '/autoload.php';

/**
 * @return array{registrar: RegistrarMedicamento, buscar: BuscarMedicamentosVigentes, consultar: ConsultarVigentePorCodigo,
 *   nueva_version: RegistrarNuevaVersion, baja: DarDeBajaMedicamento, historial: ConsultarHistorial,
 *   eliminar: EliminarRegistroSinUso, reloj: Reloj}
 */
function crearAplicacion(?array $config = null, ?Reloj $reloj = null): array
{
    $config ??= require __DIR__ . '/config.php';
    $pdo = ConexionPdo::crear($config['bd'], $config['bd']['base']);
    $repo = new RepositorioMedicamentosPdo($pdo);
    $uso = new UsoPrescripcionesPdo($pdo);
    $ids = new GeneradorUuidV4();
    $reloj ??= new RelojSistema($config['zona_horaria']);

    return [
        'registrar' => new RegistrarMedicamento($repo, $repo, $ids, $reloj),
        'buscar' => new BuscarMedicamentosVigentes($repo, $reloj),
        'consultar' => new ConsultarVigentePorCodigo($repo, $reloj),
        'nueva_version' => new RegistrarNuevaVersion($repo, $repo, $repo, $ids, $reloj),
        'baja' => new DarDeBajaMedicamento($repo, $repo, $reloj),
        'historial' => new ConsultarHistorial($repo),
        'eliminar' => new EliminarRegistroSinUso($repo, $uso, $repo),
        'reloj' => $reloj,
    ];
}
