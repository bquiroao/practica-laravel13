<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

/** Token anti-CSRF por sesión para los formularios POST. */
final class Csrf
{
    public function __construct(private string $token)
    {
    }

    public static function desdeSesion(): self
    {
        $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
        return new self($_SESSION['csrf']);
    }

    public function token(): string
    {
        return $this->token;
    }

    public function valido(mixed $recibido): bool
    {
        return is_string($recibido) && hash_equals($this->token, $recibido);
    }
}
