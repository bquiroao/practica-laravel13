<?php
declare(strict_types=1);

// Adaptadores PDO de solo lectura contra la base de prueba micro_admision_test.

use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Domain\Modelo\TenantId;
use Admision\Persistence\Pdo\PdoCatalogoCamas;
use Pruebas\Afirmar;
use Pruebas\BaseDePrueba;
use Pruebas\Datos;

$hosp01 = TenantId::desde('HOSP-01');

return [
    'Catálogo PDO lista solo las camas del hospital, ordenadas (RN-04)' => function () use ($hosp01) {
        $camas = (new PdoCatalogoCamas(BaseDePrueba::conexion()))->listar($hosp01);
        Afirmar::igual(['B-201', 'B-202', 'B-203', 'B-210', 'C-101', 'U-01'], array_map(fn ($c) => $c->codigo, $camas));
        Afirmar::verdadero(!$camas[2]->operativa, 'B-203 está en mantenimiento');
        Afirmar::verdadero($camas[3]->aislamiento, 'B-210 es de aislamiento');
    },
    'Catálogo PDO: id mal formado o de otro hospital no existe (CA-09)' => function () use ($hosp01) {
        $catalogo = new PdoCatalogoCamas(BaseDePrueba::conexion());
        Afirmar::igual(null, $catalogo->buscar($hosp01, "x' OR '1'='1"));
        Afirmar::igual(null, $catalogo->buscar($hosp01, Datos::B201_HOSP02));
        Afirmar::igual('B-201', $catalogo->buscar($hosp01, Datos::B201)?->codigo);
    },
    'Error real de PostgreSQL al leer se convierte en ErrorPersistencia' => function () use ($hosp01) {
        $e = Afirmar::lanza(ErrorPersistencia::class, fn () => (new PdoCatalogoCamas(BaseDePrueba::conexionRota()))->listar($hosp01));
        Afirmar::verdadero($e->getPrevious() instanceof PDOException, 'conserva la causa para el registro técnico');
    },
];
