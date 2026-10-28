<?php

declare(strict_types=1);

namespace Week07\Auth;

use InvalidArgumentException;
use RuntimeException;

final readonly class AuthenticateTenantUserCommand
{
    public function __construct(
        public string $tenantId,
        public string $email,
        public string $password,
    ) {
        if (trim($tenantId) === '' || trim($email) === '' || $password === '') {
            throw new InvalidArgumentException('El contexto de tenant y las credenciales son obligatorios.');
        }
    }
}

final readonly class TenantUser
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $email,
        public string $displayName,
    ) {
    }
}

final readonly class IssuedAccessToken
{
    public function __construct(
        public string $value,
        public int $expiresInSeconds,
    ) {
    }
}

interface TenantCredentialAuthenticator
{
    public function authenticate(string $tenantId, string $email, string $password): TenantUser;
}

interface AccessTokenIssuer
{
    public function issue(TenantUser $user): IssuedAccessToken;
}

final readonly class TenantAuthenticationResult
{
    public function __construct(
        public IssuedAccessToken $token,
        public TenantUser $user,
    ) {
    }
}

final class InvalidCredentials extends RuntimeException
{
}

final class TenantContextMismatch extends RuntimeException
{
}

final class AuthenticateTenantUser
{
    public function __construct(
        private TenantCredentialAuthenticator $credentials,
        private AccessTokenIssuer $tokens,
    ) {
    }

    public function execute(AuthenticateTenantUserCommand $command): TenantAuthenticationResult
    {
        $user = $this->credentials->authenticate(
            $command->tenantId,
            $command->email,
            $command->password,
        );

        if ($user->tenantId !== $command->tenantId) {
            throw new TenantContextMismatch('La identidad no pertenece al tenant autenticado.');
        }

        return new TenantAuthenticationResult(
            $this->tokens->issue($user),
            $user,
        );
    }
}
