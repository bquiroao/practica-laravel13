<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Pdo;

use Pacientes\Aplicacion\Excepciones\ClaveIdempotenciaEnUso;
use Pacientes\Aplicacion\Puertos\AlmacenIdempotencia;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;

/** idempotencia_registros: PRIMARY KEY (hospital_id, actor_id, clave); 23505 = petición simultánea. */
final class PdoAlmacenIdempotencia extends RepositorioPdo implements AlmacenIdempotencia
{
    private const SQL_BUSCAR = 'SELECT huella, respuesta FROM idempotencia_registros
        WHERE hospital_id = :hospital AND actor_id = :actor AND clave = :clave';

    private const SQL_GUARDAR = 'INSERT INTO idempotencia_registros (hospital_id, actor_id, clave, huella, respuesta)
        VALUES (:hospital, :actor, :clave, :huella, CAST(:respuesta AS jsonb))';

    public function buscar(string $hospital, string $actor, string $clave): ?array
    {
        $fila = $this->ejecutar(self::SQL_BUSCAR, ['hospital' => $hospital, 'actor' => $actor, 'clave' => $clave])->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($fila)) {
            return null;
        }
        $datos = json_decode((string) $fila['respuesta'], true);

        return ['huella' => (string) $fila['huella'], 'datos' => is_array($datos) ? $datos : []];
    }

    public function guardar(string $hospital, string $actor, string $clave, string $huella, array $datos): void
    {
        try {
            $this->ejecutar(self::SQL_GUARDAR, [
                'hospital' => $hospital,
                'actor' => $actor,
                'clave' => $clave,
                'huella' => $huella,
                'respuesta' => (string) json_encode($datos, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (ErrorPersistencia $e) {
            throw $e->sqlState === '23505' ? new ClaveIdempotenciaEnUso() : $e;
        }
    }
}
