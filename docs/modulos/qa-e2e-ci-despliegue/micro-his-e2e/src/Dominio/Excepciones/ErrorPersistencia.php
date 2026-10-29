<?php

declare(strict_types=1);

namespace E2e\Dominio\Excepciones;

/** La persistencia no respondió. Nunca lleva el SQL, el DSN ni el mensaje del driver. */
final class ErrorPersistencia extends \RuntimeException
{
}
