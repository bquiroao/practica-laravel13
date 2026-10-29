<?php
declare(strict_types=1);

// Semana 3: las mismas reglas contra PostgreSQL 16 real (micro_alergias_test).
use Alergias\Aplicacion\SolicitudRegistro;
use Alergias\Dominio\Alergeno;
use Alergias\Dominio\Alergia;
use Alergias\Dominio\AlergiaDuplicada;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\NivelAlerta;
use Alergias\Dominio\PacienteRef;
use Alergias\Dominio\Severidad;
use Alergias\Dominio\TipoAlergia;
use Alergias\Persistencia\FabricaConexion;
use Alergias\Persistencia\PdoRepositorioAlergias;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/base.php';

$medico = '7a1e0000-0000-4000-8000-0000000000a1';
$penicilina = fn (string $sev = 'life_threatening') => new SolicitudRegistro('PAC-00451', null, 'Penicilina', 'allergy', $sev, 'Edema de glotis', '2019-03-02', $medico);
$alergiaDirecta = fn (string $id) => Alergia::registrar(
    $id, new PacienteRef('5a000000-0000-4000-8000-000000000451', 'PAC-00451', 'active'), new Alergeno(null, 'Látex'),
    TipoAlergia::Alergia, Severidad::Grave, null, null, $medico, new DateTimeImmutable('2026-10-28'),
);

return [
    'T-10 camino feliz con PostgreSQL: guarda en allergies y la consulta devuelve CRITICA con 2 alergias' => function () use ($penicilina, $medico) {
        $pdo = baseDePrueba();
        $casos = armarCasosDeUso($pdo);
        $casos['registrar']->ejecutar($penicilina());
        $alerta = $casos['consultar']->ejecutar('PAC-00451');
        Arnes::igual(NivelAlerta::Critica, $alerta->nivel, 'nivel');
        Arnes::igual(2, count($alerta->detalle), 'alergias activas');
        $fila = $pdo->prepare("SELECT verified_by_user_ref_id FROM allergies WHERE allergen_name = 'Penicilina'");
        $fila->execute();
        Arnes::igual($medico, $fila->fetchColumn(), 'verified_by_user_ref_id');
    },
    'T-11 índice único real: dos inserciones equivalentes simultáneas terminan en AlergiaDuplicada (SQLSTATE 23505)' => function () use ($alergiaDirecta) {
        $pdo = baseDePrueba();
        $repo = new PdoRepositorioAlergias($pdo);
        $repo->guardar($alergiaDirecta('1a000000-0000-4000-8000-000000000001'));
        // Se salta existeActivaEquivalente() a propósito, como si dos médicos guardaran a la vez.
        Arnes::lanza(fn () => $repo->guardar($alergiaDirecta('1a000000-0000-4000-8000-000000000002')), AlergiaDuplicada::class);
    },
    'T-12 error de persistencia con base de prueba: escritura rechazada → ErrorPersistencia y 0 filas nuevas' => function () use ($penicilina) {
        $pdo = baseDePrueba();
        $antes = contarAlergias($pdo);
        $pdo->prepare('SET default_transaction_read_only = on')->execute();
        Arnes::lanza(fn () => armarCasosDeUso($pdo)['registrar']->ejecutar($penicilina()), ErrorPersistencia::class);
        $pdo->prepare('SET default_transaction_read_only = off')->execute();
        Arnes::igual($antes, contarAlergias($pdo), 'filas en allergies');
    },
    'T-13 error de persistencia: base caída al conectar → ErrorPersistencia (no PDOException)' => function () {
        Arnes::lanza(fn () => FabricaConexion::crear('pgsql:host=127.0.0.1;port=1;dbname=x', 'postgres', 'x'), ErrorPersistencia::class);
    },
    'T-14 la base protege lo mismo que el dominio: CHECK de reacción en blanco y prohibición de borrar' => function () {
        $pdo = baseDePrueba();
        $blanco = fn () => $pdo->prepare("INSERT INTO allergies (local_patient_id, verified_by_user_ref_id, allergen_name, reaction, allergy_type, severity)
            VALUES ('5a000000-0000-4000-8000-000000000452', '7a1e0000-0000-4000-8000-0000000000a1', 'Polen', '   ', 'allergy', 'mild')")->execute();
        Arnes::lanza($blanco, PDOException::class);
        Arnes::lanza(fn () => $pdo->prepare('DELETE FROM allergies')->execute(), PDOException::class);
    },
];
