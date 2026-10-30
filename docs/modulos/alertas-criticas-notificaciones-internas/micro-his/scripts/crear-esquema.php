<?php
// Crea la base (si falta) y el esquema del Micro-HIS. Script PHP a propósito: un .sh se rompe con
// los saltos de línea de Windows. Uso (desde micro-his/):
//   php scripts/crear-esquema.php                  -> base micro_alertas
//   php scripts/crear-esquema.php --prueba         -> base micro_alertas_test
//   php scripts/crear-esquema.php --prueba --reiniciar   (borra y vuelve a crear las tablas)
declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use AlertasCriticas\Domain\ErrorPersistenciaException;
use AlertasCriticas\Persistence\ConexionPdo;

set_exception_handler(function (Throwable $e): void {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    if ($e instanceof ErrorPersistenciaException) {
        fwrite(STDERR, "Revise que el contenedor esté «healthy» con: docker compose ps" . PHP_EOL);
    }
    exit(1);
});

$config = require __DIR__ . '/../config.php';
$opciones = getopt('', ['prueba', 'reiniciar']);
$base = isset($opciones['prueba']) ? $config['base_prueba'] : $config['base'];
if (!preg_match('/^[a-z_][a-z0-9_]*$/', $base)) {
    fwrite(STDERR, "Nombre de base no válido: $base\n");
    exit(1);
}

$mantenimiento = ConexionPdo::crear($config, $config['base']);
$st = $mantenimiento->prepare('SELECT 1 FROM pg_database WHERE datname = :base');
$st->execute([':base' => $base]);
if (!$st->fetchColumn()) {
    $mantenimiento->exec("CREATE DATABASE $base"); // nombre validado arriba; CREATE DATABASE no admite parámetros
    echo "Base $base creada.\n";
}

$pdo = ConexionPdo::crear($config, $base);
if (isset($opciones['reiniciar'])) {
    $pdo->exec('DROP TABLE IF EXISTS alerta_evento, alerta_destinatario, alerta_critica CASCADE');
    echo "Tablas anteriores borradas.\n";
}

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS alerta_critica (
    id uuid PRIMARY KEY,
    tenant_id varchar(30) NOT NULL,
    codigo_paciente varchar(9) NOT NULL CHECK (codigo_paciente ~ '^PAC-[0-9]{5}$'),
    servicio varchar(30) NOT NULL,
    origen varchar(60) NOT NULL,
    id_origen varchar(60) NOT NULL,
    prioridad varchar(10) NOT NULL CHECK (prioridad IN ('CRITICA', 'ALTA')),
    descripcion varchar(200) NOT NULL,
    estado varchar(10) NOT NULL CHECK (estado IN ('CREADA', 'ENVIADA', 'ESCALADA', 'ACUSADA', 'CERRADA')),
    nivel smallint NOT NULL DEFAULT 0 CHECK (nivel BETWEEN 0 AND 2),
    plazo_desde timestamptz NULL,
    escalamiento_agotado boolean NOT NULL DEFAULT false,
    creada_en timestamptz NOT NULL,
    actualizada_en timestamptz NOT NULL,
    CONSTRAINT uq_alerta_origen UNIQUE (tenant_id, origen, id_origen)
);
CREATE INDEX IF NOT EXISTS ix_alerta_pendiente ON alerta_critica (plazo_desde)
    WHERE estado IN ('ENVIADA', 'ESCALADA') AND NOT escalamiento_agotado;
CREATE INDEX IF NOT EXISTS ix_alerta_tenant ON alerta_critica (tenant_id, creada_en DESC);

CREATE TABLE IF NOT EXISTS alerta_destinatario (
    alerta_id uuid NOT NULL REFERENCES alerta_critica (id) ON DELETE CASCADE,
    usuario varchar(60) NOT NULL,
    nivel smallint NOT NULL CHECK (nivel BETWEEN 0 AND 2),
    PRIMARY KEY (alerta_id, usuario)
);

CREATE TABLE IF NOT EXISTS alerta_evento (
    alerta_id uuid NOT NULL REFERENCES alerta_critica (id),
    orden integer NOT NULL CHECK (orden > 0),
    evento varchar(30) NOT NULL,
    actor varchar(80) NOT NULL,
    detalle varchar(300) NOT NULL DEFAULT '',
    momento timestamptz NOT NULL,
    PRIMARY KEY (alerta_id, orden)
);

-- RN-10: el historial solo crece. Se bloquean UPDATE y DELETE (TRUNCATE se permite para las pruebas).
CREATE OR REPLACE FUNCTION alerta_evento_solo_insertar() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'RN-10: el historial de alertas no se modifica ni se borra';
END;
$$ LANGUAGE plpgsql;
DROP TRIGGER IF EXISTS tr_alerta_evento_inmutable ON alerta_evento;
CREATE TRIGGER tr_alerta_evento_inmutable BEFORE UPDATE OR DELETE ON alerta_evento
    FOR EACH ROW EXECUTE FUNCTION alerta_evento_solo_insertar();
SQL);

echo "Esquema listo en $base (tablas: alerta_critica, alerta_destinatario, alerta_evento).\n";
