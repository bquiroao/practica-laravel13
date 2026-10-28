<?php
declare(strict_types=1);

// Semana 4 — Propuesta: interfaz Repository de la entidad Paciente local (tabla local_patients, base HOSPITAL).
// La Aplicación pide lo que necesita; no sabe de Laravel, PDO ni de cuál conexión se usa. Eso lo decide el adaptador.
namespace Shi\Pacientes;

enum EstadoVinculo: string { case Pendiente = 'pending'; case Vinculado = 'linked'; case Desvinculado = 'unlinked'; }

/** Paciente tal como lo guarda HOSPITAL (columnas de local_patients que usa la aplicación). */
final class PacienteLocal {
    public function __construct(public readonly string $id, public readonly string $globalId, public readonly string $codigo,
                                public readonly string $nombres, public readonly string $apellidos, public readonly string $nacimiento,
                                public readonly string $sexo, public readonly string $estado = 'active',
                                public readonly EstadoVinculo $vinculo = EstadoVinculo::Pendiente) {}
}

final class ErrorRepositorio extends \RuntimeException {}

/** Repository de Paciente local. Todas sus operaciones van a la conexión 'hospital'. Si la base falla lanza ErrorRepositorio. */
interface RepositorioPacientes {
    public function porId(string $id): ?PacienteLocal;
    /** Código local del hospital (único: local_patients_code_unique). */
    public function porCodigo(string $codigo): ?PacienteLocal;
    /** Identificador que conoce CENTRAL (único: local_patients_global_id_unique). */
    public function porGlobalId(string $globalId): ?PacienteLocal;
    /** @return list<PacienteLocal> apellidos que empiezan con $prefijo (sin distinguir mayúsculas), máximo $limite. Búsqueda de admisión. */
    public function buscarPorApellido(string $prefijo, int $limite = 20): array;
    /** @return list<PacienteLocal> los registrados primero; los toma la sincronización con CENTRAL (semana 3). */
    public function pendientesDeVinculo(int $limite = 100): array;
    /** Falla con ErrorRepositorio si el código o el global_id ya existen. */
    public function registrar(PacienteLocal $p): void;
    /** CENTRAL respondió: guarda el global_id definitivo y el estado. Falla si el paciente no existe. */
    public function marcarVinculo(string $id, string $globalId, EstadoVinculo $vinculo): void;
}

/** La otra conexión ('central'): índice maestro. Interfaz aparte porque es otra base, sin llave foránea ni transacción común. */
interface IndiceMaestroPacientes {
    /** Busca o crea la identidad de red. @return array{0: string, 1: EstadoVinculo} global_id y resultado */
    public function resolver(PacienteLocal $p): array;
}
