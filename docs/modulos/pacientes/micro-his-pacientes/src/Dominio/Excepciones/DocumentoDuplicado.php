<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Excepciones;

/**
 * R-02 detectado por la base de datos (índice único): dos registros simultáneos con el
 * mismo documento pasaron la verificación previa y uno de ellos llegó segundo.
 */
final class DocumentoDuplicado extends ErrorDominio
{
    public function __construct()
    {
        parent::__construct(self::CONFLICTO, 'DOCUMENTO_EN_USO');
    }
}
