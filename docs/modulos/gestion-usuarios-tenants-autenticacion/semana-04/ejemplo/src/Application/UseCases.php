<?php

declare(strict_types=1);

namespace Auth\Application;

use Auth\Domain\DuplicateEmail;
use Auth\Domain\InvalidCredentials;
use Auth\Domain\Session;
use Auth\Domain\SessionNotActive;
use Auth\Domain\TenantUnavailable;
use Auth\Domain\User;

final class RegisterUser
{
    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenGenerator $ids,
        private Clock $clock,
    ) {}

    public function __invoke(string $tenantId, string $email, string $password): string
    {
        $email = User::normalizeEmail($email);
        User::assertStrongPassword($password);
        if (!$this->tenants->isActive($tenantId)) {
            throw new TenantUnavailable('El tenant no existe o está inactivo.');
        }
        if ($this->users->emailExists($tenantId, $email)) {
            throw new DuplicateEmail('Ya existe un usuario con ese correo en el tenant.');
        }
        $user = new User($this->ids->newId(), $tenantId, $email, $this->hasher->hash($password), true, $this->clock->now());
        $this->users->add($user);
        return $user->id;
    }
}

final class Authenticate
{
    private const TTL = 'PT8H';

    public function __construct(
        private UserRepository $users,
        private SessionRepository $sessions,
        private PasswordHasher $hasher,
        private TokenGenerator $ids,
        private Clock $clock,
    ) {}

    /** Devuelve el token en claro, que solo se entrega una vez; se guarda su hash. */
    public function __invoke(string $tenantId, string $email, string $password): string
    {
        $user = $this->users->findByEmail($tenantId, strtolower(trim($email)));
        // Mismo error para usuario inexistente, inactivo o contraseña errónea: no revela cuál falló.
        if ($user === null || !$user->active || !$this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Credenciales inválidas.');
        }
        $now = $this->clock->now();
        $token = $this->ids->newToken();
        $this->sessions->add(
            new Session($this->ids->newId(), $user->id, $tenantId, $now->add(new \DateInterval(self::TTL)), null),
            hash('sha256', $token),
        );
        return $token;
    }
}

final class RevokeSession
{
    public function __construct(private SessionRepository $sessions, private Clock $clock) {}

    public function __invoke(string $token): void
    {
        $session = $this->sessions->findByTokenHash(hash('sha256', $token));
        if ($session === null || !$session->isActiveAt($this->clock->now())) {
            throw new SessionNotActive('La sesión no existe, expiró o ya fue revocada.');
        }
        $this->sessions->revoke($session->id, $this->clock->now());
    }
}

final class ValidateSession
{
    public function __construct(private SessionRepository $sessions, private Clock $clock) {}

    public function __invoke(string $token): Session
    {
        $session = $this->sessions->findByTokenHash(hash('sha256', $token));
        if ($session === null || !$session->isActiveAt($this->clock->now())) {
            throw new SessionNotActive('Sesión no válida.');
        }
        return $session;
    }
}
