<?php

declare(strict_types=1);

final class AllInOneIdentityService
{
    /** @var array<string, array{active: bool, displayName: string}> */
    private array $users = [];

    /** @var array<string, array{role: string, active: bool}> */
    private array $memberships = [];

    /** @var array<string, true> */
    private array $revokedTokens = [];

    public function register(
        string $adminId,
        bool $adminCanManageUsers,
        string $tenantId,
        bool $tenantIsActive,
        string $subject,
        string $displayName,
        string $role,
    ): string {
        if (!$adminCanManageUsers || !$tenantIsActive) {
            return 'REGISTRATION_DENIED';
        }
        if (trim($subject) === '' || trim($displayName) === '' || trim($role) === '') {
            return 'INVALID_INPUT';
        }

        $membershipKey = $subject . '|' . $tenantId;
        if (isset($this->memberships[$membershipKey])) {
            return 'DUPLICATE_MEMBERSHIP';
        }

        // Every concern is handled here: validation, authorization, identity,
        // membership, credential checks, token issuance and session revocation.
        $this->users[$subject] = ['active' => true, 'displayName' => $displayName];
        $this->memberships[$membershipKey] = ['role' => $role, 'active' => true];
        return 'REGISTERED';
    }

    public function authenticate(
        string $tenantId,
        bool $tenantIsActive,
        string $subject,
        string $password,
        bool $credentialsAreValid,
        bool $membershipIsActive,
    ): string {
        if (!$tenantIsActive || !$credentialsAreValid || !$membershipIsActive) {
            return 'AUTHENTICATION_FAILED';
        }

        $jti = bin2hex(random_bytes(16));
        return $jti . ':' . $tenantId . ':' . $subject;
    }

    public function revoke(string $jti, string $tenantId, bool $storeIsAvailable): string
    {
        if ($jti === '' || $tenantId === '') {
            return 'INVALID_TOKEN';
        }
        if (!$storeIsAvailable) {
            return 'REVOCATION_FAILED';
        }

        $this->revokedTokens[$tenantId . '|' . $jti] = true;
        return 'REVOKED';
    }

    public function isRevoked(string $jti, string $tenantId): bool
    {
        return isset($this->revokedTokens[$tenantId . '|' . $jti]);
    }
}
