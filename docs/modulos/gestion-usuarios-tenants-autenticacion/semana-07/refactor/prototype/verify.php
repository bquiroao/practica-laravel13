<?php

declare(strict_types=1);

require_once __DIR__ . '/AuthenticateTenantUser.php';

use Week07\Auth\AccessTokenIssuer;
use Week07\Auth\AuthenticateTenantUser;
use Week07\Auth\AuthenticateTenantUserCommand;
use Week07\Auth\InvalidCredentials;
use Week07\Auth\IssuedAccessToken;
use Week07\Auth\TenantContextMismatch;
use Week07\Auth\TenantCredentialAuthenticator;
use Week07\Auth\TenantUser;

final class FakeCredentialAuthenticator implements TenantCredentialAuthenticator
{
    public ?TenantUser $user;
    public array $lastCredentials;

    public function __construct(?TenantUser $user)
    {
        $this->user = $user;
        $this->lastCredentials = [];
    }

    public function authenticate(string $tenantId, string $email, string $password): TenantUser
    {
        $this->lastCredentials = [$tenantId, $email, $password];

        if ($this->user === null) {
            throw new InvalidCredentials('Credenciales no válidas.');
        }

        return $this->user;
    }
}

final class FakeAccessTokenIssuer implements AccessTokenIssuer
{
    public int $calls = 0;
    public ?TenantUser $issuedFor = null;

    public function issue(TenantUser $user): IssuedAccessToken
    {
        $this->calls++;
        $this->issuedFor = $user;

        return new IssuedAccessToken('token-demo-no-secreto', 900);
    }
}

$checks = 0;
$failures = 0;
$check = static function (bool $passed, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$passed) {
        $failures++;
    }
    echo ($passed ? 'PASS' : 'FAIL') . ": {$message}\n";
};

$user = new TenantUser('user-1', 'tenant-a', 'demo@example.test', 'Demo User');
$authenticator = new FakeCredentialAuthenticator($user);
$issuer = new FakeAccessTokenIssuer();
$useCase = new AuthenticateTenantUser($authenticator, $issuer);
$result = $useCase->execute(
    new AuthenticateTenantUserCommand('tenant-a', 'demo@example.test', 'demo-password'),
);

$check($authenticator->lastCredentials === ['tenant-a', 'demo@example.test', 'demo-password'], 'el caso de uso entrega el contexto tenant y credenciales al puerto');
$check($result->user === $user && $result->token->expiresInSeconds === 900, 'el resultado contiene identidad autenticada y metadatos de sesión');
$check($issuer->calls === 1 && $issuer->issuedFor?->tenantId === 'tenant-a', 'el token se emite solo para la identidad del tenant solicitado');

$wrongTenantAuthenticator = new FakeCredentialAuthenticator(
    new TenantUser('user-2', 'tenant-b', 'demo@example.test', 'Other Tenant'),
);
$wrongTenantIssuer = new FakeAccessTokenIssuer();
$wrongTenantUseCase = new AuthenticateTenantUser($wrongTenantAuthenticator, $wrongTenantIssuer);
$mismatchRejected = false;
try {
    $wrongTenantUseCase->execute(
        new AuthenticateTenantUserCommand('tenant-a', 'demo@example.test', 'demo-password'),
    );
} catch (TenantContextMismatch) {
    $mismatchRejected = true;
}
$check($mismatchRejected, 'se rechaza una identidad devuelta para otro tenant');
$check($wrongTenantIssuer->calls === 0, 'no se emite token tras una discrepancia de tenant');

$invalidAuthenticator = new FakeCredentialAuthenticator(null);
$invalidIssuer = new FakeAccessTokenIssuer();
$invalidUseCase = new AuthenticateTenantUser($invalidAuthenticator, $invalidIssuer);
$invalidRejected = false;
try {
    $invalidUseCase->execute(
        new AuthenticateTenantUserCommand('tenant-a', 'demo@example.test', 'wrong-password'),
    );
} catch (InvalidCredentials) {
    $invalidRejected = true;
}
$check($invalidRejected, 'el error de credenciales se propaga al adaptador HTTP');
$check($invalidIssuer->calls === 0, 'no se emite token cuando las credenciales son inválidas');

echo "\n{$checks} comprobaciones, {$failures} fallidas.\n";
exit($failures === 0 ? 0 : 1);
