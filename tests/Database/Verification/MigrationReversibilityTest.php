<?php

namespace Tests\Database\Verification;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

// Semana 15 (Verificación BD): caja blanca de las migraciones. Cada down() deja el esquema como estaba y cada FK está indexada y protegida.
#[Group('database-integrity')]
class MigrationReversibilityTest extends TestCase
{
    public static function connections(): array
    {
        return ['central' => ['central'], 'hospital' => ['hospital']];
    }

    #[DataProvider('connections')]
    public function test_every_migration_rolls_back_to_the_previous_schema_and_reapplies_identically(string $connection): void
    {
        $this->assertPostgreSqlIntegrityPrerequisites();
        $this->migration('migrate:fresh', $connection);
        $this->migration('migrate:reset', $connection);
        $files = glob(database_path("migrations/{$connection}/*.php"));
        $sources = implode("\n", array_map('file_get_contents', $files));
        $leftovers = array_filter(explode("\n", $this->schema($connection)), fn (string $item) => ! str_starts_with($item, 'ext ')
            && (! str_starts_with($item, 'fn ') || str_contains($sources, preg_replace('/^fn (public\.)?|\(.*$/', '', $item))));
        $this->assertSame([], array_values($leftovers), 'migrate:reset deja tablas, índices, triggers o funciones de estas migraciones');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $before = $this->schema($connection);
            $this->migration('migrate', $connection, $file);
            $after = $this->schema($connection);
            $this->migration('migrate:rollback', $connection, $file);
            $this->assertSame($before, $this->schema($connection), basename($file).': down() deja objetos o borra de más');
            $this->migration('migrate', $connection, $file);
            $this->assertSame($after, $this->schema($connection), basename($file).': volver a aplicarla da otro esquema');
        }
    }

    #[DataProvider('connections')]
    public function test_every_foreign_key_restricts_deletes_and_has_a_leading_index(string $connection): void
    {
        $this->assertPostgreSqlIntegrityPrerequisites();
        Artisan::call('migrate:fresh', ['--database' => $connection, '--path' => database_path("migrations/{$connection}"), '--realpath' => true, '--force' => true]);
        $keys = DB::connection($connection)->select("SELECT fk.conname, fk.confdeltype, EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid = fk.conrelid AND (i.indkey::int2[])[0:cardinality(fk.conkey) - 1] @> fk.conkey) AS indexed FROM pg_constraint fk JOIN pg_namespace n ON n.oid = fk.connamespace WHERE fk.contype = 'f' AND n.nspname = 'public'");
        $this->assertNotEmpty($keys);
        foreach ($keys as $key) {
            $this->assertContains($key->confdeltype, ['r', 'a'], "{$key->conname} borra o anula en cascada");
            $this->assertTrue($key->indexed, "{$key->conname} no tiene un índice que empiece por sus columnas");
        }
    }

    private function migration(string $command, string $connection, ?string $file = null): void
    {
        $code = Artisan::call($command, ['--database' => $connection, '--path' => $file ?? database_path("migrations/{$connection}"), '--realpath' => true, '--force' => true]);
        $this->assertSame(0, $code, "{$command} {$connection} ".basename((string) $file));
    }

    private function schema(string $connection): string
    {
        $rows = DB::connection($connection)->select(<<<'SQL'
            SELECT 'col ' || table_name || '.' || column_name || ' ' || data_type || ' ' || is_nullable || ' ' || coalesce(column_default, '') AS item FROM information_schema.columns WHERE table_schema = 'public' AND table_name <> 'migrations'
            UNION ALL SELECT 'con ' || conrelid::regclass || ' ' || conname || ' ' || pg_get_constraintdef(oid) FROM pg_constraint WHERE connamespace = 'public'::regnamespace AND conrelid <> 'public.migrations'::regclass
            UNION ALL SELECT 'idx ' || indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename <> 'migrations'
            UNION ALL SELECT 'trg ' || tgrelid::regclass || ' ' || tgname FROM pg_trigger WHERE NOT tgisinternal
            UNION ALL SELECT 'fn ' || p.oid::regprocedure FROM pg_proc p LEFT JOIN pg_depend d ON d.objid = p.oid AND d.deptype = 'e' WHERE p.pronamespace = 'public'::regnamespace AND d.objid IS NULL
            UNION ALL SELECT 'ext ' || extname FROM pg_extension
            ORDER BY 1
            SQL);

        return implode("\n", array_column($rows, 'item'));
    }
}
