<?php

declare(strict_types=1);

require_once __DIR__ . '/despues/IdentityWorkflow.php';
require_once __DIR__ . '/despues/InMemoryAdapters.php';

function verifySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "{$message}: se esperaba " . var_export($expected, true)
            . ', se obtuvo ' . var_export($actual, true),
        );
    }

    echo "PASS: {$message}\n";
}

$assertions = 0;
$check = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    verifySame($expected, $actual, $message);
    $assertions++;
};

$now = new DateTimeImmutable('2026-10-25T12:00:00-06:00');
$infra = new InMemoryIdentityInfrastructure($now);
$infra->addTenant('HOSPITAL-A');
$infra->authorizeAdmin('ADMIN-1', 'HOSPITAL-A');
$infra->allowRole('ADMIN-1', 'HOSPITAL-A', 'laboratorio');
$register = new RegisterTenantUser($infra, $infra, $infra, $infra, $infra, $infra);

$command = new RegistrationCommand(
    'ADMIN-1',
    'HOSPITAL-A',
    new NewIdentity('SUBJECT-1', 'Usuario de demostración'),
    'laboratorio',
);
$outcome = $register->execute($command);
$check(RegistrationStatus::REGISTERED, $outcome->status, 'CA-01 registra identidad y membresía autorizadas');
$check(1, $infra->identityCount(), 'CA-01 crea una identidad global');
$check(1, $infra->membershipCount(), 'CA-01 crea una membresía del tenant');

$outcome = $register->execute($command);
$check(RegistrationStatus::DUPLICATE_MEMBERSHIP, $outcome->status, 'CA-02 evita membresía duplicada');
$check(1, $infra->membershipCount(), 'CA-02 conserva una sola membresía');

$forbidden = new RegistrationCommand(
    'UNAUTHORIZED-ADMIN',
    'HOSPITAL-A',
    new NewIdentity('SUBJECT-2', 'Otro usuario'),
    'laboratorio',
);
$outcome = $register->execute($forbidden);
$check(RegistrationStatus::FORBIDDEN, $outcome->status, 'CA-03 deniega alta sin permiso administrativo');
$check(1, $infra->identityCount(), 'CA-03 no crea identidad ante denegación');

$invalidRole = new RegistrationCommand(
    'ADMIN-1',
    'HOSPITAL-A',
    new NewIdentity('SUBJECT-3', 'Otro usuario'),
    'administrador_global',
);
$outcome = $register->execute($invalidRole);
$check(RegistrationStatus::FORBIDDEN, $outcome->status, 'CA-04 no permite asignar un rol no autorizado');
$check(1, $infra->membershipCount(), 'CA-04 no crea membresía con rol no autorizado');

$infra->addTenant('HOSPITAL-INACTIVO', active: false);
$inactiveTenant = new RegistrationCommand(
    'ADMIN-1',
    'HOSPITAL-INACTIVO',
    new NewIdentity('SUBJECT-4', 'Usuario inactivo'),
    'laboratorio',
);
$outcome = $register->execute($inactiveTenant);
$check(RegistrationStatus::TENANT_UNAVAILABLE, $outcome->status, 'CA-05 rechaza tenant inactivo');

$invalidInput = new RegistrationCommand(
    'ADMIN-1',
    'HOSPITAL-A',
    new NewIdentity('', ''),
    'laboratorio',
);
$outcome = $register->execute($invalidInput);
$check(RegistrationStatus::INVALID_INPUT, $outcome->status, 'CA-06 valida campos antes de persistir');

$infra->failNextRegistration();
$failedRegistration = new RegistrationCommand(
    'ADMIN-1',
    'HOSPITAL-A',
    new NewIdentity('SUBJECT-5', 'Usuario para rollback'),
    'laboratorio',
);
$outcome = $register->execute($failedRegistration);
$check(RegistrationStatus::PERSISTENCE_FAILURE, $outcome->status, 'CA-07 expone falla de transacción');
$check(1, $infra->identityCount(), 'CA-07 no deja identidad parcial');
$check(1, $infra->membershipCount(), 'CA-07 no deja membresía parcial');

$infra->addCredentials('SUBJECT-1', 'demo-secret-not-for-production');
$authenticate = new AuthenticateTenantUser($infra, $infra, $infra, $infra, $infra, $infra);
$login = $authenticate->execute(new AuthenticationCommand(
    'HOSPITAL-A',
    'SUBJECT-1',
    'demo-secret-not-for-production',
));
$check(AuthenticationStatus::AUTHENTICATED, $login->status, 'CA-08 autentica identidad con membresía vigente');
$check('SUBJECT-1', $login->token?->subject, 'CA-08 liga la sesión al sujeto autenticado');
$check('HOSPITAL-A', $login->token?->tenantId, 'CA-08 limita la sesión al tenant elegido');
$check(
    $now->modify('+15 minutes')->format(DATE_ATOM),
    $login->token?->expiresAt->format(DATE_ATOM),
    'CA-08 aplica expiración acotada de demostración',
);

$invalidLogin = $authenticate->execute(new AuthenticationCommand(
    'HOSPITAL-A',
    'SUBJECT-1',
    'wrong-secret',
));
$unknownLogin = $authenticate->execute(new AuthenticationCommand(
    'HOSPITAL-A',
    'UNKNOWN-SUBJECT',
    'wrong-secret',
));
$check(AuthenticationStatus::FAILED, $invalidLogin->status, 'CA-09 usa error genérico para credencial incorrecta');
$check($invalidLogin->status, $unknownLogin->status, 'CA-09 no revela si existe la identidad');
$check(null, $invalidLogin->token, 'CA-09 no emite token ante autenticación fallida');

$wrongTenantLogin = $authenticate->execute(new AuthenticationCommand(
    'HOSPITAL-B',
    'SUBJECT-1',
    'demo-secret-not-for-production',
));
$check(AuthenticationStatus::FAILED, $wrongTenantLogin->status, 'CA-10 no autentica sin membresía del tenant');
$check(null, $wrongTenantLogin->token, 'CA-10 no emite token sin membresía vigente');

$access = new CheckSessionAccess($infra, $infra);
$revoke = new RevokeCurrentSession($infra, $infra);
$token = $login->token;
$check(
    SessionAccessStatus::ALLOWED,
    $access->execute($token, 'HOSPITAL-A'),
    'CA-11 permite una sesión vigente no revocada',
);
$check(RevocationStatus::REVOKED, $revoke->execute($token), 'CA-12 confirma revocación persistida');
$check(
    SessionAccessStatus::REVOKED,
    $access->execute($token, 'HOSPITAL-A'),
    'CA-12 rechaza el token revocado en comprobaciones posteriores',
);
$check(
    SessionAccessStatus::INVALID,
    $access->execute($token, 'HOSPITAL-B'),
    'CA-13 rechaza reutilizar la sesión en otro tenant',
);

$infra->failNextRevocation();
$secondLogin = $authenticate->execute(new AuthenticationCommand(
    'HOSPITAL-A',
    'SUBJECT-1',
    'demo-secret-not-for-production',
));
$check(RevocationStatus::STORAGE_UNAVAILABLE, $revoke->execute($secondLogin->token), 'CA-14 no confirma cierre si falla la revocación');
$check(
    SessionAccessStatus::ALLOWED,
    $access->execute($secondLogin->token, 'HOSPITAL-A'),
    'CA-14 informa que la sesión sigue válida si no se pudo revocar',
);

$serviceDependencies = static function (string $class): array {
    return array_map(
        static fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName(),
        (new ReflectionMethod($class, '__construct'))->getParameters(),
    );
};
$check(
    [
        TenantDirectory::class,
        UserManagementAuthorization::class,
        IdentityRepository::class,
        MembershipRepository::class,
        IdentityRegistrationTransaction::class,
        Clock::class,
    ],
    $serviceDependencies(RegisterTenantUser::class),
    'CA-15 la alta depende de colaboraciones separadas por responsabilidad',
);
$check(
    [
        TenantDirectory::class,
        IdentityRepository::class,
        MembershipRepository::class,
        CredentialVerifier::class,
        SessionTokenIssuer::class,
        Clock::class,
    ],
    $serviceDependencies(AuthenticateTenantUser::class),
    'CA-16 autenticación no maneja alta ni revocación',
);
$check(
    [SessionRevocationStore::class, Clock::class],
    $serviceDependencies(RevokeCurrentSession::class),
    'CA-17 revocación no maneja credenciales ni alta',
);
$check(
    [SessionRevocationStore::class, Clock::class],
    $serviceDependencies(CheckSessionAccess::class),
    'CA-18 verificación de sesión tiene responsabilidad propia',
);

echo "OK: {$assertions} aserciones.\n";
