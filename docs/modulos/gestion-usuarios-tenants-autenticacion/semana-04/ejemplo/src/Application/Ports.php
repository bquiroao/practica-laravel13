<?php

declare(strict_types=1);

namespace Auth\Application;

use Auth\Domain\Session;
use Auth\Domain\User;
use DateTimeImmutable;
use RuntimeException;

// Falla de infraestructura: la capa de aplicación no conoce los errores del motor de base de datos.
final class PersistenceFailure extends RuntimeException {}

interface TenantRepository
{
    public function isActive(string $tenantId): bool;
}

interface UserRepository
{
    public function emailExists(string $tenantId, string $email): bool;

    public function add(User $user): void;

    public function findByEmail(string $tenantId, string $email): ?User;

    public function find(string $userId): ?User;
}

interface SessionRepository
{
    public function add(Session $session, string $tokenHash): void;

    public function findByTokenHash(string $tokenHash): ?Session;

    public function revoke(string $sessionId, DateTimeImmutable $at): void;
}

interface Clock
{
    public function now(): DateTimeImmutable;
}

interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}

interface TokenGenerator
{
    public function newId(): string;

    public function newToken(): string;
}
