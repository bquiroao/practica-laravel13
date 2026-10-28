<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Auth\Application\Authenticate;
use Auth\Application\Clock;
use Auth\Application\PersistenceFailure;
use Auth\Application\RegisterUser;
use Auth\Application\RevokeSession;
use Auth\Application\UserRepository;
use Auth\Domain\User;
use Auth\Persistence\PdoSessionRepository;
use Auth\Persistence\PdoTenantRepository;
use Auth\Persistence\PdoUserRepository;
use Auth\Presentation\AuthController;

$passed = 0;
$failed = 0;
function check(string $name, bool $ok): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    $ok ? $passed++ : $failed++;
}
function step(string $t): void
{
    echo PHP_EOL . $t . PHP_EOL;
}

final class FakeClock implements Clock
{
    public function __construct(public DateTimeImmutable $t) {}

    public function now(): DateTimeImmutable
    {
        return $this->t;
    }
}

function newPdo(bool $withSchema = true): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($withSchema) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
        $pdo->exec("INSERT INTO tenants (id, name, active) VALUES ('H1','Hospital Uno',1),('H2','Hospital Dos',1),('H3','Hospital Cerrado',0)");
    }
    return $pdo;
}

function build(PDO $pdo, FakeClock $clock, ?UserRepository $users = null): AuthController
{
    $users ??= new PdoUserRepository($pdo);
    $sessions = new PdoSessionRepository($pdo);
    $hasher = new BcryptHasher(4);
    $ids = new RandomTokens();
    return new AuthController(
        new RegisterUser(new PdoTenantRepository($pdo), $users, $hasher, $ids, $clock),
        new Authenticate($users, $sessions, $hasher, $ids, $clock),
        new RevokeSession($sessions, $clock),
    );
}

$pass = 'ClaveSegura2026';
$clock = new FakeClock(new DateTimeImmutable('2026-10-27 08:00:00'));

step('A. Camino feliz: alta, autenticación y revocación');
$pdo = newPdo();
$c = build($pdo, $clock);
$r = $c->register('H1', '  Ana@Hospital.GT ', $pass);
check('el alta responde 201', $r['status'] === 201);
$l = $c->login('H1', 'ana@hospital.gt', $pass);
check('el inicio de sesión responde 200 y entrega token', $l['status'] === 200 && strlen($l['body']['token']) === 64);
$token = $l['body']['token'];
check('el correo se normalizó a minúsculas', (string) $pdo->query('SELECT email FROM users')->fetchColumn() === 'ana@hospital.gt');
$o = $c->logout($token);
check('la revocación responde 204', $o['status'] === 204);
check('la sesión revocada ya no puede revocarse de nuevo (401)', $c->logout($token)['status'] === 401);

step('B. Reglas de dominio');
check('contraseña débil responde 422', $c->register('H1', 'b@h.gt', 'corta1')['status'] === 422);
check('contraseña sin dígitos responde 422', $c->register('H1', 'b@h.gt', 'SoloLetrasLargas')['status'] === 422);
check('correo inválido responde 422', $c->register('H1', 'no-es-correo', $pass)['status'] === 422);
check('correo repetido en el mismo tenant responde 409', $c->register('H1', 'ANA@hospital.gt', $pass)['status'] === 409);
check('el mismo correo en otro tenant sí se permite (201)', $c->register('H2', 'ana@hospital.gt', $pass)['status'] === 201);
check('un tenant inactivo responde 404', $c->register('H3', 'x@h.gt', $pass)['status'] === 404);
check('un tenant inexistente responde 404', $c->register('H9', 'x@h.gt', $pass)['status'] === 404);

$bad = $c->login('H1', 'ana@hospital.gt', 'otraClave12345');
$none = $c->login('H1', 'nadie@hospital.gt', $pass);
check('contraseña errónea y usuario inexistente dan 401 con el mismo mensaje', $bad['status'] === 401 && $bad === $none);
check('un usuario no inicia sesión en otro tenant donde no existe (401)', $c->login('H2', 'nadie@hospital.gt', $pass)['status'] === 401);

step('C. Expiración de la sesión (reloj controlado)');
$token2 = $c->login('H1', 'ana@hospital.gt', $pass)['body']['token'];
$clock->t = new DateTimeImmutable('2026-10-27 16:00:01');
check('pasadas 8 horas la sesión expira (401)', $c->logout($token2)['status'] === 401);
$clock->t = new DateTimeImmutable('2026-10-27 08:00:00');

step('D. Seguridad de lo almacenado');
$hash = (string) $pdo->query('SELECT password_hash FROM users WHERE tenant_id = \'H1\'')->fetchColumn();
check('la contraseña se guarda con hash bcrypt, no en claro', str_starts_with($hash, '$2y$') && !str_contains($hash, $pass));
$stored = $pdo->query('SELECT token_hash FROM sessions')->fetchAll(PDO::FETCH_COLUMN);
check('solo se guarda el hash SHA-256 del token, nunca el token', !in_array($token, $stored, true) && in_array(hash('sha256', $token), $stored, true));
$inj = $c->register('H1', "x'); DROP TABLE users;--@h.gt", $pass);
check('un correo con comillas y SQL se rechaza en el dominio (422)', $inj['status'] === 422);
$payload = "' OR '1'='1";
$repo = new PdoUserRepository($pdo);
check('el repositorio trata la inyección como dato literal: no encuentra usuario', $repo->findByEmail('H1', $payload) === null && !$repo->emailExists('H1', $payload));
check('las sentencias preparadas dejan intacta la tabla users', (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 2);

step('E. Error de persistencia');
$broken = build(newPdo(false), $clock);
check('sin esquema, la base falla y el alta responde 503 (no 500)', $broken->register('H1', 'a@h.gt', $pass)['status'] === 503);
check('el cuerpo del 503 no filtra SQL ni detalles del motor', !str_contains(json_encode($broken->login('H1', 'a@h.gt', $pass)), 'SQLSTATE'));

$failingUsers = new class implements UserRepository {
    public function emailExists(string $t, string $e): bool { return false; }
    public function add(User $u): void { throw new PersistenceFailure('disco lleno'); }
    public function findByEmail(string $t, string $e): ?User { return null; }
    public function find(string $id): ?User { return null; }
};
check('un doble que falla al guardar produce 503', build(newPdo(), $clock, $failingUsers)->register('H1', 'a@h.gt', $pass)['status'] === 503);

$pdoT = newPdo();
$pdoT->exec("INSERT INTO users VALUES ('u1','H1','dup@h.gt','x',1,'2026-01-01')");
check('la restricción UNIQUE de la base también se traduce a 503 si hay carrera', (function () use ($pdoT): bool {
    try {
        (new PdoUserRepository($pdoT))->add(new User('u2', 'H1', 'dup@h.gt', 'x', true, new DateTimeImmutable()));
        return false;
    } catch (PersistenceFailure) {
        return true;
    }
})());

step('F. Arquitectura: el núcleo no conoce la persistencia');
$bad = 0;
foreach (['Domain/Model.php', 'Application/Ports.php', 'Application/UseCases.php', 'Presentation/AuthController.php'] as $f) {
    $src = file_get_contents(__DIR__ . '/../src/' . $f);
    if (preg_match('/\bPDO\b|PDOException|\b(SELECT|INSERT|UPDATE|DELETE)\b\s/', $src)) {
        $bad++;
        echo "  menciona persistencia: $f" . PHP_EOL;
    }
}
check('dominio, aplicación y presentación no usan PDO ni SQL', $bad === 0);
$dom = file_get_contents(__DIR__ . '/../src/Domain/Model.php');
check('el dominio no depende de la capa de aplicación', !str_contains($dom, 'Auth\\Application'));

echo PHP_EOL . ($passed + $failed) . " comprobaciones, $failed fallidas." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
