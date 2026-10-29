<?php
declare(strict_types=1);

// Composition Root: el ÚNICO lugar donde se eligen las implementaciones concretas.
// Las capas Application y Domain solo conocen interfaces (Repository, Reloj, GeneradorId, criterios).

require_once __DIR__ . '/autoload.php';

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Application\ConsultarCamasDisponibles;
use Admision\Application\Puertos\GeneradorUuidV4;
use Admision\Application\Puertos\RelojSistema;
use Admision\Domain\Elegibilidad\CriterioAislamiento;
use Admision\Domain\Elegibilidad\CriterioCamaOperativa;
use Admision\Domain\Elegibilidad\CriterioServicio;
use Admision\Domain\Elegibilidad\CriterioTipoCama;
use Admision\Domain\Elegibilidad\EvaluadorElegibilidad;
use Admision\Persistence\Pdo\ConexionPdo;
use Admision\Persistence\Pdo\PdoAdmisionRepository;
use Admision\Persistence\Pdo\PdoCatalogoCamas;
use Admision\Persistence\Pdo\PdoDirectorioPacientes;
use Admision\Presentation\Servicios;

return static function (?PDO $pdo = null): Servicios {
    $pdo ??= ConexionPdo::desdeEntorno();
    // OCP: para un criterio nuevo se agrega aquí su clase; EvaluadorElegibilidad no cambia.
    $evaluador = new EvaluadorElegibilidad(
        new CriterioCamaOperativa(), new CriterioServicio(), new CriterioTipoCama(), new CriterioAislamiento(),
    );
    $admisiones = new PdoAdmisionRepository($pdo);
    $catalogo = new PdoCatalogoCamas($pdo);

    return new Servicios(
        new AdmitirPacienteYAsignarCama(new PdoDirectorioPacientes($pdo), $catalogo, $admisiones, $evaluador, new RelojSistema(), new GeneradorUuidV4()),
        new ConsultarCamasDisponibles($catalogo, $admisiones, $evaluador),
    );
};
