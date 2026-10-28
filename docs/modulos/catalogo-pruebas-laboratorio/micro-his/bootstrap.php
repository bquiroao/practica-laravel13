<?php
declare(strict_types=1);

use Lab\Application\ConsultarPruebasVigentes;
use Lab\Application\RegistrarPrueba;
use Lab\Application\RelojSistema;
use Lab\Application\VerDetallePrueba;
use Lab\Persistence\Conexion;
use Lab\Domain\LabTestRepository;
use Lab\Persistence\InMemoryLabTestRepository;
use Lab\Persistence\PdoLabTestRepository;
use Lab\Presentation\Cli\ConsolaCatalogo;
use Lab\Presentation\Http\LabTestController;
use Lab\Presentation\Http\Router;

spl_autoload_register(static function (string $clase): void {
    if (str_starts_with($clase, 'Lab\\')) {
        require __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    }
});

/** Composition Root: único lugar donde se eligen las implementaciones concretas. */
function crearServicios(?array $config = null, ?LabTestRepository $repositorio = null): array
{
    $config ??= require __DIR__ . '/config.php';
    $repositorio ??= ($config['repositorio'] ?? 'pdo') === 'memoria'
        ? new InMemoryLabTestRepository()
        : new PdoLabTestRepository(Conexion::crear($config));
    $reloj = new RelojSistema();
    return [
        'tenant' => $config['tenant'],
        'registrar' => new RegistrarPrueba($repositorio, $reloj),
        'consultar' => new ConsultarPruebasVigentes($repositorio, $reloj),
        'detalle' => new VerDetallePrueba($repositorio, $reloj),
    ];
}

function crearRouter(?array $config = null, ?LabTestRepository $repositorio = null): Router
{
    $s = crearServicios($config, $repositorio);
    return new Router(new LabTestController($s['registrar'], $s['consultar'], $s['detalle'], $s['tenant'], 'jefe.lab'));
}

function crearConsola(): ConsolaCatalogo
{
    $s = crearServicios();
    return new ConsolaCatalogo($s['registrar'], $s['consultar'], $s['detalle'], $s['tenant']);
}

/** Ejecuta sql/esquema.sql sentencia por sentencia con prepare() + execute(). */
function crearEsquema(PDO $pdo): void
{
    $lineas = array_filter(
        file(__DIR__ . '/sql/esquema.sql', FILE_IGNORE_NEW_LINES),
        static fn (string $l): bool => !str_starts_with(trim($l), '--'),
    );
    foreach (array_filter(array_map('trim', explode(';', implode("\n", $lineas)))) as $sentencia) {
        $pdo->prepare($sentencia)->execute();
    }
}
