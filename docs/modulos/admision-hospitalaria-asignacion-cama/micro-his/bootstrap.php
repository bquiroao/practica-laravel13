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
use Admision\Persistence\InMemory\InMemoryAdmisionRepository;
use Admision\Persistence\InMemory\InMemoryCatalogoCamas;
use Admision\Persistence\InMemory\InMemoryDirectorioPacientes;
use Admision\Persistence\Pdo\ConexionPdo;
use Admision\Persistence\Pdo\PdoAdmisionRepository;
use Admision\Persistence\Pdo\PdoCatalogoCamas;
use Admision\Persistence\Pdo\PdoDirectorioPacientes;
use Admision\Presentation\Servicios;

// Almacén: ADMISION_ALMACEN=pdo (por defecto) o memoria. Ambos cumplen el mismo contrato (semana 4).
return static function (?PDO $pdo = null, ?string $almacen = null): Servicios {
    $almacen ??= getenv('ADMISION_ALMACEN') ?: 'pdo';
    // OCP: para un criterio nuevo se agrega aquí su clase; EvaluadorElegibilidad no cambia.
    $evaluador = new EvaluadorElegibilidad(
        new CriterioCamaOperativa(), new CriterioServicio(), new CriterioTipoCama(), new CriterioAislamiento(),
    );
    if ($almacen === 'memoria') {
        $datos = require __DIR__ . '/datos-ficticios.php';
        $admisiones = new InMemoryAdmisionRepository();
        $catalogo = new InMemoryCatalogoCamas($datos['camas']);
        $pacientes = new InMemoryDirectorioPacientes($datos['pacientes']);
    } else {
        $pdo ??= ConexionPdo::desdeEntorno();
        $admisiones = new PdoAdmisionRepository($pdo);
        $catalogo = new PdoCatalogoCamas($pdo);
        $pacientes = new PdoDirectorioPacientes($pdo);
    }

    return new Servicios(
        new AdmitirPacienteYAsignarCama($pacientes, $catalogo, $admisiones, $evaluador, new RelojSistema(), new GeneradorUuidV4()),
        new ConsultarCamasDisponibles($catalogo, $admisiones, $evaluador),
    );
};
