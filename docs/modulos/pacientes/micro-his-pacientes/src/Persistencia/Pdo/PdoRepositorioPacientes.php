<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Pdo;

use Pacientes\Dominio\Excepciones\ConflictoVersion;
use Pacientes\Dominio\Excepciones\DocumentoDuplicado;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\RepositorioPacientes;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;

/** Adaptador PostgreSQL de RepositorioPacientes. Solo sentencias preparadas con parámetros con nombre. */
final class PdoRepositorioPacientes extends RepositorioPdo implements RepositorioPacientes
{
    private const SQL_POR_CODIGO = 'SELECT code, hospital_id, given_name, family_name, birth_date, sex, contact, status, documento_cifrado, version, identidad_protegida
        FROM pacientes WHERE hospital_id = :hospital AND code = :codigo';

    private const SQL_POR_DOCUMENTO = 'SELECT code, hospital_id, given_name, family_name, birth_date, sex, contact, status, documento_cifrado, version, identidad_protegida
        FROM pacientes WHERE hospital_id = :hospital AND documento_hash = :hash
        ORDER BY apellidos_normalizados, nombre_normalizado, code LIMIT :limite';

    private const SQL_POR_NOMBRE = "SELECT code, hospital_id, given_name, family_name, birth_date, sex, contact, status, documento_cifrado, version, identidad_protegida
        FROM pacientes WHERE hospital_id = :hospital AND nombre_normalizado LIKE :patron ESCAPE '!'
          AND (identidad_protegida = false OR identidad_protegida = :ver_protegidos)
        ORDER BY apellidos_normalizados, nombre_normalizado, code LIMIT :limite";

    private const SQL_POR_NOMBRE_Y_FECHA = "SELECT code, hospital_id, given_name, family_name, birth_date, sex, contact, status, documento_cifrado, version, identidad_protegida
        FROM pacientes WHERE hospital_id = :hospital AND nombre_normalizado LIKE :patron ESCAPE '!'
          AND (identidad_protegida = false OR identidad_protegida = :ver_protegidos)
          AND to_char(birth_date, 'YYYY-MM-DD') LIKE :fecha
        ORDER BY apellidos_normalizados, nombre_normalizado, code LIMIT :limite";

    private const SQL_INSERTAR = 'INSERT INTO pacientes (hospital_id, given_name, family_name, nombre_normalizado, apellidos_normalizados,
            birth_date, sex, contact, status, documento_tipo, documento_hash, documento_cifrado, documento_enmascarado, identidad_protegida)
        VALUES (:hospital, :nombres, :apellidos, :nombre_normalizado, :apellidos_normalizados,
            :nacimiento, :sexo, :telefono, :estado, :documento_tipo, :documento_hash, :documento_cifrado, :documento_enmascarado, :identidad_protegida)
        RETURNING code, version';

    // R-06: comparar la versión y escribir en la MISMA sentencia; si no coincide, no se toca ninguna fila.
    private const SQL_ACTUALIZAR = 'UPDATE pacientes SET given_name = :nombres, family_name = :apellidos,
            nombre_normalizado = :nombre_normalizado, apellidos_normalizados = :apellidos_normalizados,
            birth_date = :nacimiento, sex = :sexo, contact = :telefono, status = :estado,
            documento_tipo = :documento_tipo, documento_hash = :documento_hash, documento_cifrado = :documento_cifrado,
            documento_enmascarado = :documento_enmascarado, identidad_protegida = :identidad_protegida, version = version + 1, updated_at = now()
        WHERE hospital_id = :hospital AND code = :codigo AND version = :version
        RETURNING version';

    private const SQL_VERSION_VIGENTE = 'SELECT version FROM pacientes WHERE hospital_id = :hospital AND code = :codigo';

    public function __construct(\PDO $pdo, private readonly ProteccionDocumentos $proteccion)
    {
        parent::__construct($pdo);
    }

    public function porCodigo(string $hospital, string $codigo): ?Paciente
    {
        $fila = $this->ejecutar(self::SQL_POR_CODIGO, ['hospital' => $hospital, 'codigo' => $codigo])->fetch(\PDO::FETCH_ASSOC);

        return is_array($fila) ? $this->hidratar($fila) : null;
    }

    public function buscar(string $hospital, CriterioBusqueda $criterio): array
    {
        if ($criterio->tipo === CriterioBusqueda::POR_CODIGO) {
            $paciente = $this->porCodigo($hospital, (string) $criterio->codigo);

            return $paciente === null ? [] : [$paciente];
        }

        $parametros = ['hospital' => $hospital, 'limite' => CriterioBusqueda::LIMITE];
        if ($criterio->tipo === CriterioBusqueda::POR_DOCUMENTO && $criterio->documento !== null) {
            $sql = self::SQL_POR_DOCUMENTO;
            $parametros['hash'] = $this->proteccion->hash($criterio->documento);
        } else {
            // El texto del usuario es un DATO del LIKE: se escapan sus comodines.
            $parametros['patron'] = '%' . strtr((string) $criterio->nombre, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $parametros['ver_protegidos'] = $criterio->incluirProtegidos ? 'true' : 'false'; // R-11
            $sql = self::SQL_POR_NOMBRE;
            if ($criterio->nacimiento !== null) {
                $sql = self::SQL_POR_NOMBRE_Y_FECHA;
                $parametros['fecha'] = $criterio->nacimiento . '%';
            }
        }

        $filas = $this->ejecutar($sql, $parametros)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $fila): Paciente => $this->hidratar($fila), $filas);
    }

    public function registrar(Paciente $paciente): Paciente
    {
        try {
            $fila = $this->ejecutar(self::SQL_INSERTAR, $this->parametrosDeEscritura($paciente))->fetch(\PDO::FETCH_ASSOC);
        } catch (ErrorPersistencia $e) {
            throw $e->sqlState === '23505' ? new DocumentoDuplicado() : $e;
        }
        if (!is_array($fila)) {
            throw new ErrorPersistencia('El INSERT no devolvió el código');
        }

        return $paciente->conCodigo((string) $fila['code'], (int) $fila['version']);
    }

    public function actualizar(Paciente $cambios, int $versionEsperada): Paciente
    {
        $parametros = $this->parametrosDeEscritura($cambios) + ['codigo' => (string) $cambios->codigo, 'version' => $versionEsperada];
        try {
            $fila = $this->ejecutar(self::SQL_ACTUALIZAR, $parametros)->fetch(\PDO::FETCH_ASSOC);
        } catch (ErrorPersistencia $e) {
            throw $e->sqlState === '23505' ? new DocumentoDuplicado() : $e;
        }

        if (is_array($fila)) {
            return $cambios->conVersion((int) $fila['version']);
        }

        // Ninguna fila: o no existe en este hospital, o la versión ya cambió.
        $vigente = $this->ejecutar(self::SQL_VERSION_VIGENTE, ['hospital' => $cambios->hospital, 'codigo' => (string) $cambios->codigo])
            ->fetchColumn();
        if ($vigente === false) {
            throw new PacienteNoEncontrado();
        }
        throw new ConflictoVersion((int) $vigente);
    }

    /** @return array<string, string|null> */
    private function parametrosDeEscritura(Paciente $paciente): array
    {
        $documento = $paciente->identificador;

        return [
            'hospital' => $paciente->hospital,
            'nombres' => $paciente->nombres,
            'apellidos' => $paciente->apellidos,
            'nombre_normalizado' => $paciente->nombreNormalizado(),
            'apellidos_normalizados' => $paciente->apellidosNormalizados(),
            'nacimiento' => $paciente->nacimiento->format('Y-m-d'),
            'sexo' => $paciente->sexo,
            'telefono' => $paciente->telefono,
            'estado' => $paciente->estado,
            'documento_tipo' => $documento?->tipo(),
            'documento_hash' => $documento === null ? null : $this->proteccion->hash($documento),
            'documento_cifrado' => $documento === null ? null : $this->proteccion->cifrar($documento),
            'documento_enmascarado' => $documento?->enmascarado(),
            'identidad_protegida' => $paciente->identidadProtegida ? 'true' : 'false',
        ];
    }

    /** @param array<string, mixed> $fila */
    private function hidratar(array $fila): Paciente
    {
        $nacimiento = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $fila['birth_date']);
        if ($nacimiento === false) {
            throw new ErrorPersistencia('Fecha de nacimiento ilegible');
        }

        return Paciente::reconstituir(
            (string) $fila['code'],
            (string) $fila['hospital_id'],
            (string) $fila['given_name'],
            (string) $fila['family_name'],
            $nacimiento,
            (string) $fila['sex'],
            $fila['contact'] === null ? null : (string) $fila['contact'],
            $fila['documento_cifrado'] === null ? null : $this->proteccion->descifrar((string) $fila['documento_cifrado']),
            (string) $fila['status'],
            (int) $fila['version'],
            in_array($fila['identidad_protegida'] ?? false, [true, 't', 'true', 1, '1'], true),
        );
    }
}
