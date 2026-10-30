<?php
declare(strict_types=1);

/*
 * Crea las bases micro_medicamentos y micro_medicamentos_test (si faltan), el esquema PROPUESTO
 * y los datos ficticios de ejemplo en la base principal.
 *   php scripts/crear-esquema.php            -> ambas bases, con datos en la principal
 *   php scripts/crear-esquema.php --reiniciar -> borra y vuelve a crear las tablas
 * Es PHP y no .sh a propósito: en Windows los saltos de línea CRLF rompen los scripts de shell.
 */

use Catalogo\Domain\Modelo\FormaFarmaceutica;
use Catalogo\Domain\Modelo\Limites as L;
use Catalogo\Domain\Modelo\UnidadConcentracion;
use Catalogo\Domain\Modelo\ViaAdministracion;
use Catalogo\Persistence\ConexionPdo;
use Catalogo\Persistence\DatosEjemplo;
use Catalogo\Persistence\GeneradorUuidV4;
use Catalogo\Persistence\RepositorioMedicamentosPdo;

require __DIR__ . '/../autoload.php';
$config = require __DIR__ . '/../config.php';
$reiniciar = in_array('--reiniciar', $argv, true);

/** DDL generado desde Limites y las enumeraciones: la base y el dominio no pueden divergir (RN-05). */
function ddlCatalogo(): array
{
    $lista = fn (array $casos) => implode(', ', array_map(fn (\BackedEnum $c) => "'" . $c->value . "'", $casos));
    $rango = "'" . L::FECHA_MIN . "' AND '" . L::FECHA_MAX . "'";
    $precision = L::CONCENTRACION_ENTEROS + L::CONCENTRACION_DECIMALES;
    return [
        'CREATE EXTENSION IF NOT EXISTS btree_gist',
        "CREATE TABLE IF NOT EXISTS medicamentos (
            id uuid PRIMARY KEY,
            hospital_id uuid NOT NULL,
            codigo varchar(" . L::CODIGO . ") NOT NULL CHECK (codigo ~ '^MED-[0-9]{6}$'),
            version smallint NOT NULL CHECK (version BETWEEN 1 AND " . L::VERSION_MAX . "),
            nombre varchar(" . L::NOMBRE_MAX . ") NOT NULL CHECK (char_length(nombre) >= " . L::NOMBRE_MIN . "),
            principio_activo varchar(" . L::PRINCIPIO_MAX . ") NOT NULL CHECK (char_length(principio_activo) >= " . L::PRINCIPIO_MIN . "),
            clave_equivalencia varchar(" . L::CLAVE_MAX . ") NOT NULL,
            concentracion_valor numeric($precision, " . L::CONCENTRACION_DECIMALES . ") NOT NULL CHECK (concentracion_valor > 0),
            concentracion_unidad varchar(" . L::UNIDAD_MAX . ") NOT NULL CHECK (concentracion_unidad IN (" . $lista(UnidadConcentracion::cases()) . ")),
            forma_farmaceutica varchar(" . L::FORMA_MAX . ") NOT NULL CHECK (forma_farmaceutica IN (" . $lista(FormaFarmaceutica::cases()) . ")),
            via_administracion varchar(" . L::VIA_MAX . ") NOT NULL CHECK (via_administracion IN (" . $lista(ViaAdministracion::cases()) . ")),
            presentacion varchar(" . L::PRESENTACION_MAX . ") NOT NULL CHECK (char_length(presentacion) >= " . L::PRESENTACION_MIN . "),
            registro_sanitario varchar(" . L::REGISTRO_SANITARIO_MAX . ") NULL,
            vigente_desde date NOT NULL CHECK (vigente_desde BETWEEN $rango),
            vigente_hasta date NULL CHECK (vigente_hasta IS NULL OR (vigente_hasta > vigente_desde AND vigente_hasta <= '" . L::FECHA_MAX . "')),
            motivo_cierre varchar(" . L::MOTIVO_MAX . ") NULL,
            creado_por varchar(" . L::USUARIO_MAX . ") NOT NULL,
            creado_en timestamptz NOT NULL DEFAULT now(),
            actualizado_en timestamptz NULL,
            CONSTRAINT uq_medicamentos_codigo_version UNIQUE (hospital_id, codigo, version),
            CONSTRAINT ex_medicamentos_codigo_vigencia EXCLUDE USING gist
                (hospital_id WITH =, codigo WITH =, daterange(vigente_desde, vigente_hasta, '[)') WITH &&),
            CONSTRAINT ex_medicamentos_clave_vigencia EXCLUDE USING gist
                (hospital_id WITH =, clave_equivalencia WITH =, daterange(vigente_desde, vigente_hasta, '[)') WITH &&)
        )",
        'CREATE INDEX IF NOT EXISTS ix_medicamentos_hospital_codigo ON medicamentos (hospital_id, codigo)',
        // SIMULACIÓN de lo que publicaría prescripciones-alergias (pendiente de acordar con su dueño).
        "CREATE TABLE IF NOT EXISTS referencias_prescripcion (
            hospital_id uuid NOT NULL,
            codigo_medicamento varchar(" . L::CODIGO . ") NOT NULL,
            prescripcion_ref varchar(20) NOT NULL,
            PRIMARY KEY (hospital_id, prescripcion_ref, codigo_medicamento)
        )",
    ];
}

function paso(string $texto): void
{
    echo "  - $texto" . PHP_EOL;
}

try {
    $admin = ConexionPdo::crear($config['bd'], 'postgres');
    foreach ([$config['bd']['base'], $config['bd']['base_prueba']] as $base) {
        $st = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = :b');
        $st->execute(['b' => $base]);
        if ($st->fetchColumn() === false) {
            $admin->exec('CREATE DATABASE "' . str_replace('"', '', $base) . '"'); // nombre de config, no de usuario
            echo "Base creada: $base" . PHP_EOL;
        }
    }

    foreach ([$config['bd']['base'] => true, $config['bd']['base_prueba'] => false] as $base => $conDatos) {
        echo "Esquema en $base:" . PHP_EOL;
        $pdo = ConexionPdo::crear($config['bd'], $base);
        if ($reiniciar) {
            $pdo->exec('DROP TABLE IF EXISTS medicamentos, referencias_prescripcion');
            paso('tablas anteriores borradas (--reiniciar)');
        }
        foreach (ddlCatalogo() as $sentencia) {
            $pdo->exec($sentencia);
        }
        paso('tablas medicamentos y referencias_prescripcion listas');
        if ($conDatos && (int) $pdo->query('SELECT count(*) FROM medicamentos')->fetchColumn() === 0) {
            DatosEjemplo::cargar(new RepositorioMedicamentosPdo($pdo), new GeneradorUuidV4());
            $ref = $pdo->prepare('INSERT INTO referencias_prescripcion VALUES (:h, :c, :p)');
            foreach (DatosEjemplo::REFERENCIAS as [$h, $c, $p]) {
                $ref->execute(['h' => $h, 'c' => $c, 'p' => $p]);
            }
            paso('datos ficticios de ejemplo cargados');
        }
        paso('versiones en medicamentos: ' . $pdo->query('SELECT count(*) FROM medicamentos')->fetchColumn());
    }
    echo 'Listo.' . PHP_EOL;
} catch (\Throwable $e) {
    $causa = $e->getPrevious() ?? $e;
    fwrite(STDERR, 'No se pudo crear el esquema: ' . $causa->getMessage() . PHP_EOL);
    fwrite(STDERR, '¿Está el contenedor en estado healthy? Revise con: docker compose ps' . PHP_EOL);
    exit(1);
}
