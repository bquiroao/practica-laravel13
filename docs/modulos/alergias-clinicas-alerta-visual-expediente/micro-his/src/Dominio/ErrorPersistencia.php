<?php
declare(strict_types=1);

namespace Alergias\Dominio;

use RuntimeException;

/** La base no respondió: el caso de uso no deja datos a medias. */
final class ErrorPersistencia extends RuntimeException
{
}
