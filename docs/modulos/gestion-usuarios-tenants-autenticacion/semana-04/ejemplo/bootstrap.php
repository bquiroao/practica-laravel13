<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $map = [
        'Auth\\Domain\\' => ['Domain/Model.php'],
        'Auth\\Application\\' => ['Application/Ports.php', 'Application/UseCases.php'],
        'Auth\\Persistence\\' => ['Persistence/Pdo.php', 'Persistence/InMemory.php'],
        'Auth\\Presentation\\' => ['Presentation/AuthController.php', 'Presentation/Mvc.php'],
    ];
    foreach ($map as $prefix => $files) {
        if (str_starts_with($class, $prefix)) {
            foreach ($files as $f) {
                require_once __DIR__ . '/src/' . $f;
            }
            return;
        }
    }
});

use Auth\Application\Clock;
use Auth\Application\PasswordHasher;
use Auth\Application\TokenGenerator;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

final class BcryptHasher implements PasswordHasher
{
    public function __construct(private int $cost = 10) {}

    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}

final class RandomTokens implements TokenGenerator
{
    public function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}

