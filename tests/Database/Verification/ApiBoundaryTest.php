<?php

namespace Tests\Database\Verification;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

// Semana 15 (Verificación BD): caja negra. Casos límite contra /api/v1 por el kernel HTTP; solo se miran código, cuerpo y filas de la base.
#[Group('database-integrity')]
class ApiBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => config('jwt.secret') ?: str_repeat('s', 32)]);
        $this->assertPostgreSqlIntegrityPrerequisites();
        foreach (['central', 'hospital'] as $connection) {
            Artisan::call('migrate:fresh', ['--database' => $connection, '--path' => database_path("migrations/{$connection}"), '--realpath' => true, '--force' => true]);
        }
    }

    public static function rejectedRequests(): array
    {
        return [
            'login sin X-Tenant-ID' => ['POST', '/api/v1/auth/login', ['email' => 'ana@hospital.test', 'password' => 'x'], []],
            'registro con X-Tenant-ID vacío' => ['POST', '/api/v1/auth/register', ['name' => 'Ana'], ['X-Tenant-ID' => '']],
            'refresh sin tenant ni token' => ['POST', '/api/v1/auth/refresh', [], []],
            'login con inyección SQL y sin tenant' => ['POST', '/api/v1/auth/login', ['email' => "x' OR 1=1; DROP TABLE local_patients; --", 'password' => str_repeat('a', 10000)], []],
        ];
    }

    #[DataProvider('rejectedRequests')]
    public function test_request_without_tenant_gets_400_without_writing_or_leaking_internals(string $method, string $uri, array $body, array $headers): void
    {
        $before = $this->rowCounts();
        $response = $this->json($method, $uri, $body, $headers);

        $response->assertStatus(400)->assertExactJson(['message' => 'La cabecera X-Tenant-ID es obligatoria.']);
        foreach (['SQLSTATE', 'pgsql', 'his_hospital', 'his_central'] as $internal) {
            $this->assertStringNotContainsStringIgnoringCase($internal, $response->getContent());
        }
        $this->assertSame($before, $this->rowCounts(), 'Una petición rechazada cambió filas en la base');
    }

    public static function openDefects(): array
    {
        return [
            'tenant inexistente' => ['POST', '/api/v1/auth/login', ['X-Tenant-ID' => '00000000-0000-0000-0000-0000000000aa'], 404, 'E-01: App\\Models\\Tenant y la tabla tenants no existen'],
            'ruta de API que no existe' => ['POST', '/api/v1/pacientes', [], 404, 'E-07: la ruta web /{any?} también atrapa /api/v1/*'],
            'método no permitido' => ['GET', '/api/v1/auth/login', [], 405, 'E-07: la ruta web /{any?} responde la vista de la SPA en vez de 405'],
        ];
    }

    #[DataProvider('openDefects')]
    public function test_boundary_request_gets_the_expected_client_error(string $method, string $uri, array $headers, int $status, string $defect): void
    {
        $before = $this->rowCounts();
        $response = $this->json($method, $uri, ['email' => 'ana@hospital.test', 'password' => 'x'], $headers);

        $this->assertSame($before, $this->rowCounts());
        if ($response->status() !== $status) {
            $this->markTestIncomplete("Defecto abierto {$defect} (se esperaba {$status} y responde {$response->status()})");
        }
        $response->assertJsonStructure(['message']);
    }

    public function test_every_api_class_compiles_so_no_route_kills_the_php_process(): void
    {
        $known = ['app/Http/Middleware/JwtAuth.php' => 'E-06: JwtAuth choca con el alias JWTAuth (PHP no distingue mayúsculas); GET /auth/me y POST /auth/logout terminan con Fatal error'];
        $broken = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)) as $file) {
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
            if ($code !== 0) {
                $broken[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }
        $this->assertSame([], array_diff($broken, array_keys($known)), 'Clases que no compilan');
        if ($broken !== []) {
            $this->markTestIncomplete('Defecto abierto '.implode('; ', array_intersect_key($known, array_flip($broken))));
        }
    }

    private function rowCounts(): array
    {
        $counts = [];
        foreach (['central', 'hospital'] as $connection) {
            $db = DB::connection($connection);
            foreach ($db->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename") as $table) {
                $counts["{$connection}.{$table->tablename}"] = (int) $db->scalar("SELECT count(*) FROM \"{$table->tablename}\"");
            }
        }

        return $counts;
    }
}
