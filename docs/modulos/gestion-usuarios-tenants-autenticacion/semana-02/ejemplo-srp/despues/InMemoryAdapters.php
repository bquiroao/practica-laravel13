<?php

declare(strict_types=1);

final class InMemoryIdentityInfrastructure implements
    TenantDirectory,
    UserManagementAuthorization,
    IdentityRepository,
    MembershipRepository,
    IdentityRegistrationTransaction,
    CredentialVerifier,
    SessionTokenIssuer,
    SessionRevocationStore,
    Clock
{
    /** @var array<string, bool> */
    private array $tenants = [];

    /** @var array<string, bool> */
    private array $administrators = [];

    /** @var array<string, bool> */
    private array $assignableRoles = [];

    /** @var array<string, UserIdentity> */
    private array $identities = [];

    /** @var array<string, TenantMembership> */
    private array $memberships = [];

    /** @var array<string, string> subject => password hash */
    private array $credentialHashes = [];

    /** @var array<string, SessionToken> */
    private array $tokens = [];

    /** @var array<string, DateTimeImmutable> tenant|jti => expiresAt */
    private array $revoked = [];

    private bool $failRegistration = false;
    private bool $failRevocation = false;
    private int $tokenSequence = 0;

    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function addTenant(string $tenantId, bool $active = true): void
    {
        $this->tenants[$tenantId] = $active;
    }

    public function authorizeAdmin(string $adminId, string $tenantId): void
    {
        $this->administrators[$adminId . '|' . $tenantId] = true;
    }

    public function allowRole(string $adminId, string $tenantId, string $role): void
    {
        $this->assignableRoles[$adminId . '|' . $tenantId . '|' . $role] = true;
    }

    public function addCredentials(string $subject, string $secret): void
    {
        $this->credentialHashes[$subject] = password_hash($secret, PASSWORD_DEFAULT);
    }

    public function addIdentity(UserIdentity $identity): void
    {
        $this->identities[$identity->subject] = $identity;
    }

    public function addMembership(TenantMembership $membership): void
    {
        $this->memberships[$membership->subject . '|' . $membership->tenantId] = $membership;
    }

    public function failNextRegistration(): void
    {
        $this->failRegistration = true;
    }

    public function failNextRevocation(): void
    {
        $this->failRevocation = true;
    }

    public function identityCount(): int
    {
        return count($this->identities);
    }

    public function membershipCount(): int
    {
        return count($this->memberships);
    }

    public function isActive(string $tenantId): bool
    {
        return $this->tenants[$tenantId] ?? false;
    }

    public function canManageUsers(string $adminId, string $tenantId): bool
    {
        return $this->administrators[$adminId . '|' . $tenantId] ?? false;
    }

    public function canAssignInitialRole(string $adminId, string $tenantId, string $role): bool
    {
        return $this->assignableRoles[$adminId . '|' . $tenantId . '|' . $role] ?? false;
    }

    public function findIdentity(string $subject): ?UserIdentity
    {
        return $this->identities[$subject] ?? null;
    }

    public function createIdentity(UserIdentity $identity): bool
    {
        if (isset($this->identities[$identity->subject])) {
            return false;
        }

        $this->identities[$identity->subject] = $identity;
        return true;
    }

    public function hasActiveMembership(string $subject, string $tenantId): bool
    {
        $membership = $this->memberships[$subject . '|' . $tenantId] ?? null;
        return $membership?->status === MembershipStatus::ACTIVE;
    }

    public function findMembership(string $subject, string $tenantId): ?TenantMembership
    {
        return $this->memberships[$subject . '|' . $tenantId] ?? null;
    }

    public function createMembership(TenantMembership $membership): bool
    {
        $key = $membership->subject . '|' . $membership->tenantId;
        if (isset($this->memberships[$key])) {
            return false;
        }

        $this->memberships[$key] = $membership;
        return true;
    }

    public function run(callable $operation): bool
    {
        $identitiesBefore = $this->identities;
        $membershipsBefore = $this->memberships;

        if ($this->failRegistration) {
            $this->failRegistration = false;
            return false;
        }

        try {
            if ($operation()) {
                return true;
            }
        } catch (Throwable) {
            $this->identities = $identitiesBefore;
            $this->memberships = $membershipsBefore;
            return false;
        }

        $this->identities = $identitiesBefore;
        $this->memberships = $membershipsBefore;
        return false;
    }

    public function verify(string $subject, string $secret): bool
    {
        $hash = $this->credentialHashes[$subject] ?? null;
        return $hash !== null && password_verify($secret, $hash);
    }

    public function issue(string $subject, string $tenantId, DateTimeImmutable $expiresAt): ?SessionToken
    {
        $this->tokenSequence++;
        $jti = 'demo-jti-' . $this->tokenSequence;
        $token = new SessionToken($subject, $tenantId, $jti, $expiresAt);
        $this->tokens[$jti] = $token;
        return $token;
    }

    public function revoke(string $tenantId, string $jti, DateTimeImmutable $expiresAt): bool
    {
        if ($this->failRevocation) {
            $this->failRevocation = false;
            return false;
        }

        $this->revoked[$tenantId . '|' . $jti] = $expiresAt;
        return true;
    }

    public function isRevoked(string $tenantId, string $jti): bool
    {
        return isset($this->revoked[$tenantId . '|' . $jti]);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
