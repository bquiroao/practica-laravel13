<?php

declare(strict_types=1);

namespace Pacientes\Persistencia;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Persistencia\Pdo\FabricaConexion;
use Pacientes\Persistencia\Pdo\PdoIndiceDuplicados;
use Pacientes\Persistencia\Pdo\PdoRegistroAuditoria;
use Pacientes\Persistencia\Pdo\PdoRepositorioPacientes;
use Pacientes\Persistencia\Pdo\PdoTransaccion;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;

/** Adaptadores PostgreSQL: todos sobre la MISMA conexión, para que la transacción los abarque. */
final class FabricaPdo
{
    /**
     * @throws ErrorPersistencia si no hay conexión
     * @throws \InvalidArgumentException si faltan las claves del documento
     */
    public static function crear(string $dsn, string $usuario, string $clave, ProteccionDocumentos $proteccion): ConjuntoAdaptadores
    {
        return self::sobre(FabricaConexion::crear($dsn, $usuario, $clave), $proteccion);
    }

    /** Sobre una conexión ya abierta (la usan las pruebas de integración con un esquema temporal). */
    public static function sobre(\PDO $pdo, ProteccionDocumentos $proteccion): ConjuntoAdaptadores
    {
        $repositorio = new PdoRepositorioPacientes($pdo, $proteccion);

        return new ConjuntoAdaptadores(
            $repositorio,
            $repositorio,
            new PdoIndiceDuplicados($pdo, $proteccion),
            new PdoRegistroAuditoria($pdo),
            new PdoTransaccion($pdo),
        );
    }
}
