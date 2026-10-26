<?php

declare(strict_types=1);

enum IdentityStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}

enum MembershipStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}

enum RegistrationStatus: string
{
    case REGISTERED = 'REGISTERED';
    case INVALID_INPUT = 'INVALID_INPUT';
    case TENANT_UNAVAILABLE = 'TENANT_UNAVAILABLE';
    case FORBIDDEN = 'FORBIDDEN';
    case DUPLICATE_MEMBERSHIP = 'DUPLICATE_MEMBERSHIP';
    case PERSISTENCE_FAILURE = 'PERSISTENCE_FAILURE';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
}

enum AuthenticationStatus: string
{
    case AUTHENTICATED = 'AUTHENTICATED';
    case FAILED = 'AUTHENTICATION_FAILED';
    case INVALID_INPUT = 'INVALID_INPUT';
    case SERVICE_UNAVAILABLE = 'AUTHENTICATION_UNAVAILABLE';
}

enum RevocationStatus: string
{
    case REVOKED = 'REVOKED';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case STORAGE_UNAVAILABLE = 'REVOCATION_UNAVAILABLE';
}

enum SessionAccessStatus: string
{
    case ALLOWED = 'ALLOWED';
    case INVALID = 'INVALID';
    case REVOKED = 'REVOKED';
    case EXPIRED = 'EXPIRED';
}

final readonly class NewIdentity
{
    public function __construct(
        public string $subject,
        public string $displayName,
    ) {
    }
}

final readonly class UserIdentity
{
    public function __construct(
        public string $subject,
        public string $displayName,
        public IdentityStatus $status,
    ) {
    }
}

final readonly class TenantMembership
{
    public function __construct(
        public string $subject,
        public string $tenantId,
        public string $role,
        public MembershipStatus $status,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo = null,
    ) {
    }

    public function isValidAt(DateTimeImmutable $now): bool
    {
        return $this->status === MembershipStatus::ACTIVE
            && $this->validFrom <= $now
            && ($this->validTo === null || $now < $this->validTo);
    }
}

final readonly class SessionToken
{
    public function __construct(
        public string $subject,
        public string $tenantId,
        public string $jti,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}

final readonly class RegistrationCommand
{
    public function __construct(
        public string $adminId,
        public string $tenantId,
        public NewIdentity $identity,
        public string $role,
    ) {
    }
}

final readonly class AuthenticationCommand
{
    public function __construct(
        public string $tenantId,
        public string $subject,
        public string $secret,
    ) {
    }
}

final readonly class RegistrationOutcome
{
    public function __construct(public RegistrationStatus $status)
    {
    }
}

final readonly class AuthenticationOutcome
{
    public function __construct(
        public AuthenticationStatus $status,
        public ?SessionToken $token = null,
    ) {
    }
}

interface TenantDirectory
{
    public function isActive(string $tenantId): bool;
}

interface UserManagementAuthorization
{
    public function canManageUsers(string $adminId, string $tenantId): bool;

    public function canAssignInitialRole(string $adminId, string $tenantId, string $role): bool;
}

interface IdentityRepository
{
    public function findIdentity(string $subject): ?UserIdentity;

    public function createIdentity(UserIdentity $identity): bool;
}

interface MembershipRepository
{
    public function hasActiveMembership(string $subject, string $tenantId): bool;

    public function findMembership(string $subject, string $tenantId): ?TenantMembership;

    public function createMembership(TenantMembership $membership): bool;
}

interface IdentityRegistrationTransaction
{
    public function run(callable $operation): bool;
}

interface CredentialVerifier
{
    public function verify(string $subject, string $secret): bool;
}

interface SessionTokenIssuer
{
    public function issue(string $subject, string $tenantId, DateTimeImmutable $expiresAt): ?SessionToken;
}

interface SessionRevocationStore
{
    public function revoke(string $tenantId, string $jti, DateTimeImmutable $expiresAt): bool;

    public function isRevoked(string $tenantId, string $jti): bool;
}

interface Clock
{
    public function now(): DateTimeImmutable;
}

final class RegisterTenantUser
{
    public function __construct(
        private TenantDirectory $tenants,
        private UserManagementAuthorization $authorization,
        private IdentityRepository $identities,
        private MembershipRepository $memberships,
        private IdentityRegistrationTransaction $transaction,
        private Clock $clock,
    ) {
    }

    public function execute(RegistrationCommand $command): RegistrationOutcome
    {
        if (
            trim($command->adminId) === ''
            || trim($command->tenantId) === ''
            || trim($command->identity->subject) === ''
            || trim($command->identity->displayName) === ''
            || trim($command->role) === ''
        ) {
            return new RegistrationOutcome(RegistrationStatus::INVALID_INPUT);
        }

        try {
            $tenantIsActive = $this->tenants->isActive($command->tenantId);
        } catch (Throwable) {
            return new RegistrationOutcome(RegistrationStatus::SERVICE_UNAVAILABLE);
        }
        if (!$tenantIsActive) {
            return new RegistrationOutcome(RegistrationStatus::TENANT_UNAVAILABLE);
        }
        try {
            $mayManageUsers = $this->authorization->canManageUsers($command->adminId, $command->tenantId);
            $mayAssignRole = $this->authorization->canAssignInitialRole(
                $command->adminId,
                $command->tenantId,
                $command->role,
            );
        } catch (Throwable) {
            return new RegistrationOutcome(RegistrationStatus::SERVICE_UNAVAILABLE);
        }
        if (!$mayManageUsers || !$mayAssignRole) {
            return new RegistrationOutcome(RegistrationStatus::FORBIDDEN);
        }
        try {
            $hasMembership = $this->memberships->hasActiveMembership(
                $command->identity->subject,
                $command->tenantId,
            );
        } catch (Throwable) {
            return new RegistrationOutcome(RegistrationStatus::SERVICE_UNAVAILABLE);
        }
        if ($hasMembership) {
            return new RegistrationOutcome(RegistrationStatus::DUPLICATE_MEMBERSHIP);
        }

        $success = $this->transaction->run(function () use ($command): bool {
            $identity = $this->identities->findIdentity($command->identity->subject);
            if ($identity === null) {
                $identity = new UserIdentity(
                    $command->identity->subject,
                    $command->identity->displayName,
                    IdentityStatus::ACTIVE,
                );
                if (!$this->identities->createIdentity($identity)) {
                    return false;
                }
            } elseif ($identity->status !== IdentityStatus::ACTIVE) {
                return false;
            }

            if ($this->memberships->hasActiveMembership($identity->subject, $command->tenantId)) {
                return false;
            }

            return $this->memberships->createMembership(new TenantMembership(
                subject: $identity->subject,
                tenantId: $command->tenantId,
                role: $command->role,
                status: MembershipStatus::ACTIVE,
                validFrom: $this->clock->now(),
            ));
        });

        return new RegistrationOutcome(
            $success ? RegistrationStatus::REGISTERED : RegistrationStatus::PERSISTENCE_FAILURE,
        );
    }
}

final class AuthenticateTenantUser
{
    public function __construct(
        private TenantDirectory $tenants,
        private IdentityRepository $identities,
        private MembershipRepository $memberships,
        private CredentialVerifier $credentials,
        private SessionTokenIssuer $tokenIssuer,
        private Clock $clock,
    ) {
    }

    public function execute(AuthenticationCommand $command): AuthenticationOutcome
    {
        if (
            trim($command->tenantId) === ''
            || trim($command->subject) === ''
            || $command->secret === ''
        ) {
            return new AuthenticationOutcome(AuthenticationStatus::INVALID_INPUT);
        }

        try {
            $tenantIsActive = $this->tenants->isActive($command->tenantId);
        } catch (Throwable) {
            return new AuthenticationOutcome(AuthenticationStatus::SERVICE_UNAVAILABLE);
        }
        if (!$tenantIsActive) {
            return new AuthenticationOutcome(AuthenticationStatus::FAILED);
        }

        try {
            $identity = $this->identities->findIdentity($command->subject);
            $credentialsValid = $this->credentials->verify($command->subject, $command->secret);
        } catch (Throwable) {
            return new AuthenticationOutcome(AuthenticationStatus::SERVICE_UNAVAILABLE);
        }

        if (
            $identity === null
            || $identity->status !== IdentityStatus::ACTIVE
            || !$credentialsValid
        ) {
            return new AuthenticationOutcome(AuthenticationStatus::FAILED);
        }

        try {
            $membership = $this->memberships->findMembership($command->subject, $command->tenantId);
            $membershipIsValid = $membership?->isValidAt($this->clock->now()) ?? false;
        } catch (Throwable) {
            return new AuthenticationOutcome(AuthenticationStatus::SERVICE_UNAVAILABLE);
        }
        if (!$membershipIsValid) {
            return new AuthenticationOutcome(AuthenticationStatus::FAILED);
        }

        try {
            $expiresAt = $this->clock->now()->modify('+15 minutes');
            $token = $this->tokenIssuer->issue($command->subject, $command->tenantId, $expiresAt);
        } catch (Throwable) {
            return new AuthenticationOutcome(AuthenticationStatus::SERVICE_UNAVAILABLE);
        }

        return $token === null
            ? new AuthenticationOutcome(AuthenticationStatus::SERVICE_UNAVAILABLE)
            : new AuthenticationOutcome(AuthenticationStatus::AUTHENTICATED, $token);
    }
}

final class RevokeCurrentSession
{
    public function __construct(
        private SessionRevocationStore $revocations,
        private Clock $clock,
    ) {
    }

    public function execute(?SessionToken $token): RevocationStatus
    {
        if (
            $token === null
            || trim($token->tenantId) === ''
            || trim($token->jti) === ''
            || trim($token->subject) === ''
            || $token->expiresAt <= $this->clock->now()
        ) {
            return RevocationStatus::INVALID_TOKEN;
        }

        try {
            return $this->revocations->revoke($token->tenantId, $token->jti, $token->expiresAt)
                ? RevocationStatus::REVOKED
                : RevocationStatus::STORAGE_UNAVAILABLE;
        } catch (Throwable) {
            return RevocationStatus::STORAGE_UNAVAILABLE;
        }
    }
}

final class CheckSessionAccess
{
    public function __construct(
        private SessionRevocationStore $revocations,
        private Clock $clock,
    ) {
    }

    public function execute(?SessionToken $token, string $requestedTenantId): SessionAccessStatus
    {
        if (
            $token === null
            || $token->jti === ''
            || $token->subject === ''
            || $token->tenantId === ''
            || $requestedTenantId === ''
            || $token->tenantId !== $requestedTenantId
        ) {
            return SessionAccessStatus::INVALID;
        }
        if ($token->expiresAt <= $this->clock->now()) {
            return SessionAccessStatus::EXPIRED;
        }

        try {
            return $this->revocations->isRevoked($token->tenantId, $token->jti)
                ? SessionAccessStatus::REVOKED
                : SessionAccessStatus::ALLOWED;
        } catch (Throwable) {
            return SessionAccessStatus::INVALID;
        }
    }
}
