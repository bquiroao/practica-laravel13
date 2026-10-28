<?php

declare(strict_types=1);

namespace Prescripciones\Dominio\Excepciones;

/** La base no respondió o no confirmó. Nunca lleva el mensaje del driver hacia afuera. */
final class ErrorPersistencia extends \RuntimeException
{
}
