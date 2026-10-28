<?php
declare(strict_types=1);

// Adaptador de referencia de RepositorioPacientes con PDO y sentencias preparadas. En Laravel se construye con
// new PdoRepositorioPacientes(DB::connection('hospital')->getPdo()): la conexión se nombra siempre, nunca la de por defecto.
namespace Shi\Pacientes;

final class PdoRepositorioPacientes implements RepositorioPacientes {
    private const COLUMNAS = 'local_patient_id, global_id, code, given_name, family_name, birth_date, sex, status, mpi_link_status';
    /** Consultas principales: las mismas que muestra el documento y que el script prueba con EXPLAIN. */
    public const CONSULTAS = [
        'porId' => 'SELECT ' . self::COLUMNAS . ' FROM local_patients WHERE local_patient_id = ?',
        'porCodigo' => 'SELECT ' . self::COLUMNAS . ' FROM local_patients WHERE code = ?',
        'porGlobalId' => 'SELECT ' . self::COLUMNAS . ' FROM local_patients WHERE global_id = ?',
        'buscarPorApellido' => 'SELECT ' . self::COLUMNAS . " FROM local_patients WHERE lower(family_name) LIKE ? ESCAPE '\\' ORDER BY lower(family_name), given_name LIMIT ?",
        'pendientesDeVinculo' => 'SELECT ' . self::COLUMNAS . " FROM local_patients WHERE mpi_link_status = 'pending' ORDER BY created_at, local_patient_id LIMIT ?",
        'registrar' => 'INSERT INTO local_patients (' . self::COLUMNAS . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'marcarVinculo' => 'UPDATE local_patients SET global_id = ?, mpi_link_status = ?, updated_at = now() WHERE local_patient_id = ?',
    ];

    public function __construct(private \PDO $pdo) { $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); }

    public function porId(string $id): ?PacienteLocal { return $this->leer('porId', [$id])[0] ?? null; }
    public function porCodigo(string $codigo): ?PacienteLocal { return $this->leer('porCodigo', [$codigo])[0] ?? null; }
    public function porGlobalId(string $globalId): ?PacienteLocal { return $this->leer('porGlobalId', [$globalId])[0] ?? null; }
    public function buscarPorApellido(string $prefijo, int $limite = 20): array {
        return $this->leer('buscarPorApellido', [addcslashes(mb_strtolower($prefijo), '\\%_') . '%', $limite]);
    }
    public function pendientesDeVinculo(int $limite = 100): array { return $this->leer('pendientesDeVinculo', [$limite]); }

    public function registrar(PacienteLocal $p): void {
        $this->escribir('registrar', [$p->id, $p->globalId, $p->codigo, $p->nombres, $p->apellidos, $p->nacimiento, $p->sexo, $p->estado, $p->vinculo->value]);
    }

    public function marcarVinculo(string $id, string $globalId, EstadoVinculo $vinculo): void {
        if ($this->escribir('marcarVinculo', [$globalId, $vinculo->value, $id]) !== 1) { throw new ErrorRepositorio("No existe el paciente $id"); }
    }

    /** @return list<PacienteLocal> */
    private function leer(string $consulta, array $parametros): array {
        try { $c = $this->pdo->prepare(self::CONSULTAS[$consulta]); $c->execute($parametros); $filas = $c->fetchAll(\PDO::FETCH_ASSOC); }
        catch (\PDOException $e) { throw new ErrorRepositorio("No se pudo leer ($consulta)", 0, $e); }
        return array_map(fn (array $f) => new PacienteLocal($f['local_patient_id'], $f['global_id'], $f['code'], $f['given_name'], $f['family_name'],
                                                            $f['birth_date'], $f['sex'], $f['status'], EstadoVinculo::from($f['mpi_link_status'])), $filas);
    }

    private function escribir(string $consulta, array $parametros): int {
        try { $c = $this->pdo->prepare(self::CONSULTAS[$consulta]); $c->execute($parametros); return $c->rowCount(); }
        catch (\PDOException $e) { throw new ErrorRepositorio("No se pudo escribir ($consulta)", 0, $e); }
    }
}
