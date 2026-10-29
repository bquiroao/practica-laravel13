<?php
declare(strict_types=1);

namespace Admision\Persistence\Pdo;

final class Uuid
{
    public static function esValido(string $valor): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $valor) === 1;
    }
}
