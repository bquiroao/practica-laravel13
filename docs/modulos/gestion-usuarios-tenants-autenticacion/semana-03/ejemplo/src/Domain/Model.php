<?php

declare(strict_types=1);

namespace Auth\Domain;

use DateTimeImmutable;
use DomainException;

final class WeakPassword extends DomainException {}
final class InvalidEmail extends DomainException {}
final class DuplicateEmail extends DomainException {}
final class TenantUnavailable extends DomainException {}
final class InvalidCredentials extends DomainException {}
final class SessionNotActive extends DomainException {}

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly bool $active,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmail('Correo con formato inválido.');
        }
        return $email;
    }

    // Regla de dominio: contraseña de al menos 12 caracteres con letra y dígito.
    public static function assertStrongPassword(string $plain): void
    {
        if (strlen($plain) < 12 || !preg_match('/[A-Za-z]/', $plain) || !preg_match('/\d/', $plain)) {
            throw new WeakPassword('La contraseña debe tener 12+ caracteres, letras y números.');
        }
    }
}

final class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $revokedAt,
    ) {}

    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return $this->revokedAt === null && $now < $this->expiresAt;
    }
}
