<?php
declare(strict_types=1);

namespace Admision\Persistence\Pdo;

/**
 * Esquema del Micro-HIS. Los nombres son PROPUESTA DEL MICRO-HIS: el esquema real de HOSPITAL no está publicado.
 * Tablas propias del módulo: admision, asignacion_cama, evento_admision.
 * cama_catalogo y paciente_ref SIMULAN a los módulos vecinos: el código del módulo solo las lee.
 */
final class EsquemaMicroHis
{
    private const SENTENCIAS = [
        "CREATE TABLE IF NOT EXISTS cama_catalogo (
            id uuid PRIMARY KEY,
            tenant_id varchar(10) NOT NULL,
            codigo varchar(20) NOT NULL,
            servicio varchar(40) NOT NULL,
            tipo varchar(20) NOT NULL CHECK (tipo IN ('GENERAL', 'UCI', 'PEDIATRICA')),
            aislamiento boolean NOT NULL DEFAULT false,
            estado_operativo varchar(20) NOT NULL CHECK (estado_operativo IN ('OPERATIVA', 'MANTENIMIENTO', 'BLOQUEADA')),
            UNIQUE (tenant_id, codigo))",
        "CREATE TABLE IF NOT EXISTS paciente_ref (
            tenant_id varchar(10) NOT NULL,
            codigo_paciente varchar(9) NOT NULL,
            activo boolean NOT NULL,
            PRIMARY KEY (tenant_id, codigo_paciente))",
        "CREATE TABLE IF NOT EXISTS admision (
            id uuid PRIMARY KEY,
            tenant_id varchar(10) NOT NULL,
            codigo_paciente varchar(9) NOT NULL CHECK (codigo_paciente ~ '^PAC-[0-9]{5}$'),
            servicio_requerido varchar(40) NOT NULL,
            tipo_cama_requerido varchar(20) NOT NULL,
            requiere_aislamiento boolean NOT NULL,
            origen varchar(20) NOT NULL CHECK (origen IN ('EMERGENCIA', 'CONSULTA_EXTERNA', 'REFERENCIA')),
            estado varchar(10) NOT NULL CHECK (estado IN ('ACTIVA', 'CERRADA')),
            creada_por varchar(40) NOT NULL,
            creada_por_rol varchar(20) NOT NULL,
            solicitud_id varchar(64) NOT NULL,
            creada_en timestamptz NOT NULL)",
        // RN-02: una sola admisión activa por paciente y hospital.
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_admision_paciente_activa ON admision (tenant_id, codigo_paciente) WHERE estado = 'ACTIVA'",
        "CREATE TABLE IF NOT EXISTS asignacion_cama (
            id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
            admision_id uuid NOT NULL REFERENCES admision (id),
            tenant_id varchar(10) NOT NULL,
            cama_id uuid NOT NULL,
            cama_codigo varchar(20) NOT NULL,
            asignada_por varchar(40) NOT NULL,
            asignada_en timestamptz NOT NULL,
            liberada_en timestamptz NULL)",
        // RN-01: una sola asignación activa por cama. Es la garantía real ante dos admisiones simultáneas.
        "CREATE UNIQUE INDEX IF NOT EXISTS ux_asignacion_cama_activa ON asignacion_cama (tenant_id, cama_id) WHERE liberada_en IS NULL",
        "CREATE TABLE IF NOT EXISTS evento_admision (
            id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
            orden bigint GENERATED ALWAYS AS IDENTITY,
            admision_id uuid NOT NULL REFERENCES admision (id),
            tenant_id varchar(10) NOT NULL,
            tipo varchar(30) NOT NULL,
            usuario_id varchar(40) NOT NULL,
            rol varchar(20) NOT NULL,
            solicitud_id varchar(64) NOT NULL,
            ocurrido_en timestamptz NOT NULL,
            detalle jsonb NOT NULL)",
        // RN-05: los eventos son de solo inserción.
        "CREATE OR REPLACE FUNCTION evento_admision_inmutable() RETURNS trigger LANGUAGE plpgsql AS
            'BEGIN RAISE EXCEPTION ''evento_admision es de solo inserción (RN-05)''; END'",
        "CREATE OR REPLACE TRIGGER tr_evento_admision_inmutable BEFORE UPDATE OR DELETE ON evento_admision
            FOR EACH ROW EXECUTE FUNCTION evento_admision_inmutable()",
    ];

    public static function crear(\PDO $pdo, bool $recrear = false): void
    {
        if ($recrear) {
            $pdo->prepare('DROP TABLE IF EXISTS evento_admision, asignacion_cama, admision, paciente_ref, cama_catalogo')->execute();
        }
        foreach (self::SENTENCIAS as $sql) {
            $pdo->prepare($sql)->execute();
        }
    }

    /** Siembra los datos ficticios de los módulos simulados (camas y pacientes). */
    public static function sembrar(\PDO $pdo, array $datos): void
    {
        $cama = $pdo->prepare('INSERT INTO cama_catalogo (id, tenant_id, codigo, servicio, tipo, aislamiento, estado_operativo)
            VALUES (:id, :tenant, :codigo, :servicio, :tipo, :aislamiento, :estado) ON CONFLICT (id) DO NOTHING');
        foreach ($datos['camas'] as [$id, $tenant, $codigo, $servicio, $tipo, $aislamiento, $operativa]) {
            $cama->execute([
                ':id' => $id, ':tenant' => $tenant, ':codigo' => $codigo, ':servicio' => $servicio, ':tipo' => $tipo,
                ':aislamiento' => $aislamiento ? 'true' : 'false', ':estado' => $operativa ? 'OPERATIVA' : 'MANTENIMIENTO',
            ]);
        }
        $paciente = $pdo->prepare('INSERT INTO paciente_ref (tenant_id, codigo_paciente, activo)
            VALUES (:tenant, :codigo, :activo) ON CONFLICT DO NOTHING');
        foreach ($datos['pacientes'] as [$tenant, $codigo, $activo]) {
            $paciente->execute([':tenant' => $tenant, ':codigo' => $codigo, ':activo' => $activo ? 'true' : 'false']);
        }
    }
}
