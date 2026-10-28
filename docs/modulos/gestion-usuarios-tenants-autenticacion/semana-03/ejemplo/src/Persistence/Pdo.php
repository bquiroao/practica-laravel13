<?php

declare(strict_types=1);

namespace Auth\Persistence;

use Auth\Application\PersistenceFailure;
use Auth\Application\SessionRepository;
use Auth\Application\TenantRepository;
use Auth\Application\UserRepository;
use Auth\Domain\Session;
use Auth\Domain\User;
use DateTimeImmutable;
use PDO;
use PDOException;

abstract class PdoBase
{
    public function __construct(protected PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function run(string $sql, array $params): \PDOStatement
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st;
        } catch (PDOException $e) {
            // Se traduce y no se filtra el SQL ni el mensaje del motor.
            throw new PersistenceFailure('Error de persistencia.', 0, $e);
        }
    }

    protected static function date(string $v): DateTimeImmutable
    {
        return new DateTimeImmutable($v);
    }
}

final class PdoTenantRepository extends PdoBase implements TenantRepository
{
    public function isActive(string $tenantId): bool
    {
        $row = $this->run('SELECT active FROM tenants WHERE id = :id', ['id' => $tenantId])->fetch(PDO::FETCH_ASSOC);
        return $row !== false && (int) $row['active'] === 1;
    }
}

final class PdoUserRepository extends PdoBase implements UserRepository
{
    public function emailExists(string $tenantId, string $email): bool
    {
        return $this->run('SELECT 1 FROM users WHERE tenant_id = :t AND email = :e', ['t' => $tenantId, 'e' => $email])->fetchColumn() !== false;
    }

    public function add(User $user): void
    {
        $this->run(
            'INSERT INTO users (id, tenant_id, email, password_hash, active, created_at) VALUES (:id, :t, :e, :p, :a, :c)',
            ['id' => $user->id, 't' => $user->tenantId, 'e' => $user->email, 'p' => $user->passwordHash, 'a' => (int) $user->active, 'c' => $user->createdAt->format('c')],
        );
    }

    public function findByEmail(string $tenantId, string $email): ?User
    {
        return $this->map($this->run('SELECT * FROM users WHERE tenant_id = :t AND email = :e', ['t' => $tenantId, 'e' => $email])->fetch(PDO::FETCH_ASSOC));
    }

    public function find(string $userId): ?User
    {
        return $this->map($this->run('SELECT * FROM users WHERE id = :id', ['id' => $userId])->fetch(PDO::FETCH_ASSOC));
    }

    private function map(array|false $r): ?User
    {
        return $r === false ? null : new User($r['id'], $r['tenant_id'], $r['email'], $r['password_hash'], (int) $r['active'] === 1, self::date($r['created_at']));
    }
}

final class PdoSessionRepository extends PdoBase implements SessionRepository
{
    public function add(Session $s, string $tokenHash): void
    {
        $this->run(
            'INSERT INTO sessions (id, token_hash, user_id, tenant_id, expires_at, revoked_at) VALUES (:id, :h, :u, :t, :x, NULL)',
            ['id' => $s->id, 'h' => $tokenHash, 'u' => $s->userId, 't' => $s->tenantId, 'x' => $s->expiresAt->format('c')],
        );
    }

    public function findByTokenHash(string $tokenHash): ?Session
    {
        $r = $this->run('SELECT * FROM sessions WHERE token_hash = :h', ['h' => $tokenHash])->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : new Session(
            $r['id'], $r['user_id'], $r['tenant_id'], self::date($r['expires_at']),
            $r['revoked_at'] === null ? null : self::date($r['revoked_at']),
        );
    }

    public function revoke(string $sessionId, DateTimeImmutable $at): void
    {
        $this->run('UPDATE sessions SET revoked_at = :a WHERE id = :id AND revoked_at IS NULL', ['a' => $at->format('c'), 'id' => $sessionId]);
    }
}
