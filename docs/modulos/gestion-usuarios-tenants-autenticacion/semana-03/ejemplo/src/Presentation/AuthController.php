<?php

declare(strict_types=1);

namespace Auth\Presentation;

use Auth\Application\Authenticate;
use Auth\Application\PersistenceFailure;
use Auth\Application\RegisterUser;
use Auth\Application\RevokeSession;
use Auth\Domain\DuplicateEmail;
use Auth\Domain\InvalidCredentials;
use Auth\Domain\InvalidEmail;
use Auth\Domain\SessionNotActive;
use Auth\Domain\TenantUnavailable;
use Auth\Domain\WeakPassword;

/** Traduce casos de uso a respuestas tipo HTTP. No contiene reglas de negocio ni SQL. */
final class AuthController
{
    public function __construct(
        private RegisterUser $register,
        private Authenticate $login,
        private RevokeSession $logout,
    ) {}

    /** @return array{status:int, body:array<string,mixed>} */
    public function register(string $tenantId, string $email, string $password): array
    {
        return $this->handle(fn () => ['status' => 201, 'body' => ['userId' => ($this->register)($tenantId, $email, $password)]]);
    }

    public function login(string $tenantId, string $email, string $password): array
    {
        return $this->handle(fn () => ['status' => 200, 'body' => ['token' => ($this->login)($tenantId, $email, $password)]]);
    }

    public function logout(string $token): array
    {
        return $this->handle(function () use ($token) {
            ($this->logout)($token);
            return ['status' => 204, 'body' => []];
        });
    }

    private function handle(callable $action): array
    {
        try {
            return $action();
        } catch (InvalidEmail | WeakPassword $e) {
            return ['status' => 422, 'body' => ['error' => $e->getMessage()]];
        } catch (DuplicateEmail $e) {
            return ['status' => 409, 'body' => ['error' => $e->getMessage()]];
        } catch (TenantUnavailable $e) {
            return ['status' => 404, 'body' => ['error' => $e->getMessage()]];
        } catch (InvalidCredentials | SessionNotActive $e) {
            return ['status' => 401, 'body' => ['error' => $e->getMessage()]];
        } catch (PersistenceFailure) {
            return ['status' => 503, 'body' => ['error' => 'Servicio no disponible, intente de nuevo.']];
        }
    }
}
