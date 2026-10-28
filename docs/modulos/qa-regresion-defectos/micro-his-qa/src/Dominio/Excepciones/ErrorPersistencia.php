<?php

declare(strict_types=1);

namespace Qa\Dominio\Excepciones;

/** La base no respondió o no confirmó. El mensaje nunca lleva el DSN ni la contraseña. */
final class ErrorPersistencia extends \RuntimeException
{
}
