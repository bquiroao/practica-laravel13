<?php
declare(strict_types=1);

// Repositorio PDO de admisiones contra la base de prueba micro_admision_test:
// camino feliz, RN-01 y RN-02 en la base, concurrencia con dos conexiones y error de persistencia.

use Admision\Application\AdmitirPacienteYAsignarCama;
use Admision\Domain\Excepciones\CamaYaAsignada;
use Admision\Domain\Excepciones\ConflictoConcurrencia;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Domain\Excepciones\PacienteConAdmisionActiva;
use Admision\Domain\Modelo\Admision;
use Admision\Domain\Modelo\CamaDisponible;
use Admision\Domain\Modelo\CodigoPaciente;
use Admision\Domain\Modelo\OrigenAdmision;
use Admision\Domain\Modelo\Requerimiento;
use Admision\Domain\Modelo\TenantId;
use Admision\Persistence\Pdo\PdoAdmisionRepository;
use Admision\Persistence\Pdo\PdoCatalogoCamas;
use Admision\Persistence\Pdo\PdoDirectorioPacientes;
use Pruebas\Afirmar;
use Pruebas\BaseDePrueba;
use Pruebas\Datos;
use Pruebas\Dobles\GeneradorIdSecuencial;
use Pruebas\Dobles\RelojFijo;

$hosp01 = TenantId::desde('HOSP-01');
$contar = function (PDO $pdo, string $tabla): int {
    $s = $pdo->prepare("SELECT count(*) FROM {$tabla}"); // nombre fijo de la prueba, no viene del usuario
    $s->execute();
    return (int) $s->fetchColumn();
};
// Admisión ya validada por el caso de uso: sirve para simular que dos solicitudes pasaron la verificación previa.
$admisionPara = fn (string $id, string $paciente, CamaDisponible $cama) => Admision::abrir(
    $id, TenantId::desde('HOSP-01'), CodigoPaciente::desde($paciente), Requerimiento::desde('MEDICINA_INTERNA', 'GENERAL', false),
    OrigenAdmision::EMERGENCIA, $cama, Datos::evaluador(), Datos::contexto('usr-adm-01')->responsable(), new DateTimeImmutable('2026-03-02T08:15:00+00:00'),
);

return [
    'Camino feliz con PostgreSQL: admisión, asignación y dos eventos (CA-01)' => function () use ($hosp01, $contar) {
        $pdo = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdo);
        $repo = new PdoAdmisionRepository($pdo);
        $casoDeUso = new AdmitirPacienteYAsignarCama(new PdoDirectorioPacientes($pdo), new PdoCatalogoCamas($pdo), $repo, Datos::evaluador(), new RelojFijo(), new GeneradorIdSecuencial());
        $r = $casoDeUso->ejecutar(Datos::solicitud(), Datos::contexto('usr-adm-01', 'sol-0001'));
        Afirmar::igual([1, 1, 2], [$contar($pdo, 'admision'), $contar($pdo, 'asignacion_cama'), $contar($pdo, 'evento_admision')]);
        Afirmar::igual([Datos::B201], $repo->camasOcupadas($hosp01));
        Afirmar::igual('B-201', $repo->buscarPorId($hosp01, $r->admisionId)?->camaCodigo);
        $eventos = $repo->eventos($hosp01, $r->admisionId);
        Afirmar::igual(['ADMISION_CREADA', 'CAMA_ASIGNADA'], array_map(fn ($e) => $e->tipo->value, $eventos));
        Afirmar::igual('sol-0001', $eventos[0]->responsable->solicitudId);
        Afirmar::verdadero($repo->tieneAdmisionActiva($hosp01, CodigoPaciente::desde('PAC-00451')), 'PAC-00451 queda con admisión activa');
    },
    'RN-02 en la base: segunda admisión activa del mismo paciente se rechaza' => function () use ($contar, $admisionPara) {
        $pdo = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdo);
        $repo = new PdoAdmisionRepository($pdo);
        $repo->registrar($admisionPara('a0000000-0000-4000-8000-000000000001', 'PAC-00451', Datos::cama('B-201')));
        Afirmar::lanza(PacienteConAdmisionActiva::class, fn () => $repo->registrar($admisionPara('a0000000-0000-4000-8000-000000000002', 'PAC-00451', Datos::cama('B-202'))));
        Afirmar::igual(1, $contar($pdo, 'admision'));
    },
    'RN-01: dos admisiones que pasaron la verificación previa no se quedan con la misma cama (CA-07)' => function () use ($contar, $admisionPara) {
        $pdoA = BaseDePrueba::conexion();
        $pdoB = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdoA);
        (new PdoAdmisionRepository($pdoA))->registrar($admisionPara('a0000000-0000-4000-8000-000000000001', 'PAC-00451', Datos::cama('B-202')));
        Afirmar::lanza(CamaYaAsignada::class, fn () => (new PdoAdmisionRepository($pdoB))->registrar($admisionPara('a0000000-0000-4000-8000-000000000002', 'PAC-00452', Datos::cama('B-202'))));
        Afirmar::igual([1, 1, 2], [$contar($pdoA, 'admision'), $contar($pdoA, 'asignacion_cama'), $contar($pdoA, 'evento_admision')]);
    },
    'RN-01 simultáneo: con la cama bloqueada por otra transacción se responde conflicto, y al confirmar ella, cama ya asignada' => function () use ($contar, $admisionPara) {
        $pdoA = BaseDePrueba::conexion();
        $pdoB = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdoA);
        $pdoA->beginTransaction(); // la transacción A inserta B-202 y todavía no confirma
        $pdoA->prepare("INSERT INTO admision VALUES (:id, 'HOSP-01', 'PAC-00453', 'MEDICINA_INTERNA', 'GENERAL', false, 'EMERGENCIA', 'ACTIVA', 'usr-sup-01', 'SUPERVISOR_CAMAS', 'sol-a', now())")
            ->execute([':id' => 'a0000000-0000-4000-8000-000000000009']);
        $pdoA->prepare("INSERT INTO asignacion_cama (admision_id, tenant_id, cama_id, cama_codigo, asignada_por, asignada_en) VALUES (:id, 'HOSP-01', :cama, 'B-202', 'usr-sup-01', now())")
            ->execute([':id' => 'a0000000-0000-4000-8000-000000000009', ':cama' => Datos::B202]);
        $repoB = new PdoAdmisionRepository($pdoB, 300);
        $intentoB = fn () => $repoB->registrar($admisionPara('a0000000-0000-4000-8000-000000000002', 'PAC-00452', Datos::cama('B-202')));
        Afirmar::lanza(ConflictoConcurrencia::class, $intentoB);
        $pdoA->commit();
        Afirmar::lanza(CamaYaAsignada::class, $intentoB);
        Afirmar::igual(1, $contar($pdoB, 'asignacion_cama'));
    },
    'Error de persistencia a mitad de la transacción revierte todo (CA-12, RNF-08)' => function () use ($contar, $admisionPara) {
        $pdo = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdo);
        $b201 = Datos::cama('B-201');
        // Código de cama de 25 caracteres: la fila de admision entra, la de asignacion_cama falla (varchar(20)).
        $camaRara = new CamaDisponible($b201->id, $b201->tenantId, str_repeat('X', 25), $b201->servicio, $b201->tipo, false, true);
        $e = Afirmar::lanza(ErrorPersistencia::class, fn () => (new PdoAdmisionRepository($pdo))->registrar($admisionPara('a0000000-0000-4000-8000-000000000001', 'PAC-00451', $camaRara)));
        Afirmar::igual('22001', $e->getPrevious()?->errorInfo[0]);
        Afirmar::igual([0, 0, 0], [$contar($pdo, 'admision'), $contar($pdo, 'asignacion_cama'), $contar($pdo, 'evento_admision')]);
    },
    'RN-05: los eventos de trazabilidad no se pueden modificar' => function () use ($admisionPara) {
        $pdo = BaseDePrueba::conexion();
        BaseDePrueba::limpiar($pdo);
        (new PdoAdmisionRepository($pdo))->registrar($admisionPara('a0000000-0000-4000-8000-000000000001', 'PAC-00451', Datos::cama('B-201')));
        $e = Afirmar::lanza(PDOException::class, fn () => $pdo->prepare("UPDATE evento_admision SET usuario_id = 'otro'")->execute());
        Afirmar::verdadero(str_contains($e->getMessage(), 'solo inserción'), 'el trigger explica la regla');
    },
    'Directorio PDO responde solo si el código existe y está activo (CA-03)' => function () use ($hosp01) {
        $directorio = new PdoDirectorioPacientes(BaseDePrueba::conexion());
        Afirmar::verdadero($directorio->existeActivo($hosp01, CodigoPaciente::desde('PAC-00451')), 'PAC-00451 activo');
        Afirmar::verdadero(!$directorio->existeActivo($hosp01, CodigoPaciente::desde('PAC-00460')), 'PAC-00460 inactivo');
        Afirmar::verdadero(!$directorio->existeActivo($hosp01, CodigoPaciente::desde('PAC-00900')), 'PAC-00900 es de HOSP-02');
    },
];
