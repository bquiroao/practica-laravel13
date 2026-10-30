<?php

// Semana 11 (Verificación BD) — Migración PROPUESTA para HOSPITAL: índices de las 4 consultas frecuentes medidas con EXPLAIN ANALYZE.
// CONCURRENTLY no bloquea las escrituras mientras se crea el índice; por eso no corre dentro de una transacción ($withinTransaction = false).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'hospital';

    public $withinTransaction = false;

    public const INDICES = [
        // C1 buscar paciente por inicio de apellido (lower(family_name) LIKE 'pre%'); semana 4
        'local_patients_family_name_lower_pattern_index' => 'local_patients (lower(family_name) text_pattern_ops, given_name)',
        // C2 pacientes pendientes de vincular con CENTRAL, los más antiguos primero; parcial: solo guarda los pendientes; semana 4
        'local_patients_mpi_pending_index' => "local_patients (created_at, local_patient_id) WHERE mpi_link_status = 'pending'",
        // C3 últimas tomas de signos vitales de un episodio; semana 10 (MV-02)
        'vital_signs_episode_measured_index' => 'vital_signs (care_episode_id, measured_at DESC, vital_sign_id DESC)',
        // C4 agenda de un médico en un día
        'appointments_doctor_starts_index' => 'appointments (doctor_profile_id, starts_at)',
    ];

    public function up(): void
    {
        foreach (self::INDICES as $nombre => $definicion) {
            DB::connection($this->connection)->statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS $nombre ON $definicion");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDICES) as $nombre) {
            DB::connection($this->connection)->statement("DROP INDEX CONCURRENTLY IF EXISTS $nombre");
        }
    }
};
