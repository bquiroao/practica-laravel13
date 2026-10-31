<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** Módulos verificación de base de datos PostgreSQL y contratos API (centro de documentación por rol). */
class SystemController extends Controller
{
    /** Chequeos en vivo de las dos bases: conexión, versión, migraciones pendientes, extensión, integridad de la bitácora. */
    public function database(Request $request, AuditTrail $audit): JsonResponse
    {
        $hospital = $request->attributes->get('hospital');
        $check = fn (string $label, callable $fn) => (function () use ($label, $fn) {
            try {
                [$ok, $detail] = $fn();

                return ['label' => $label, 'ok' => (bool) $ok, 'detail' => $detail];
            } catch (\Throwable $e) {
                return ['label' => $label, 'ok' => false, 'detail' => 'No se pudo comprobar: '.class_basename($e)];
            }
        })();
        $pending = function (string $connection): array {
            $files = collect(glob(database_path("migrations/{$connection}/*.php")))->map(fn ($f) => basename($f, '.php'));
            $ran = DB::connection($connection)->table('migrations')->pluck('migration');
            $missing = $files->diff($ran)->values();

            return [$missing->isEmpty(), $missing->isEmpty() ? "{$files->count()} migraciones aplicadas" : 'Pendientes: '.$missing->implode(', ')];
        };
        $chain = $audit->verify($hospital->hospital_id);

        return response()->json([
            'hospital' => ['code' => $hospital->code, 'database' => DB::connection('hospital')->getDatabaseName()],
            'checks' => [
                $check('Conexión a la base CENTRAL', fn () => [true, DB::connection('central')->getDatabaseName().' · '.DB::connection('central')->scalar('SHOW server_version')]),
                $check('Conexión a la base del hospital', fn () => [true, DB::connection('hospital')->getDatabaseName().' · '.DB::connection('hospital')->scalar('SHOW server_version')]),
                $check('Migraciones de CENTRAL', fn () => $pending('central')),
                $check('Migraciones del hospital', fn () => $pending('hospital')),
                $check('Extensión btree_gist (impide cruces de horarios y camas)', fn () => [($v = DB::connection('hospital')->scalar("SELECT extversion FROM pg_extension WHERE extname = 'btree_gist'")) !== null, $v ? "versión {$v}" : 'No instalada']),
                $check('Bitácora sin alteraciones', fn () => [$chain['integra'], $chain['integra'] ? "{$chain['revisados']} registros verificados" : "Alterada desde el registro #{$chain['primera_falla']}"]),
                $check('Camas con estado coherente', fn () => [($n = (int) DB::connection('hospital')->scalar("SELECT count(*) FROM beds b WHERE (b.status = 'occupied') <> EXISTS (SELECT 1 FROM bed_stays s WHERE s.bed_id = b.bed_id AND s.ended_at IS NULL)")) === 0,
                    $n === 0 ? 'Todas coinciden con sus estancias' : "{$n} cama(s) por revisar (defecto AU-05)"]),
            ],
            'tables' => collect(['local_patients' => 'Pacientes', 'appointments' => 'Citas', 'care_episodes' => 'Atenciones', 'clinical_notes' => 'Notas', 'vital_signs' => 'Tomas de signos',
                'lab_order_items' => 'Pruebas de laboratorio', 'prescriptions' => 'Recetas', 'beds' => 'Camas'])
                ->map(fn ($label, $table) => ['table' => $table, 'label' => $label, 'rows' => (int) DB::connection('hospital')->table($table)->count()])->values(),
        ]);
    }

    /**
     * OpenAPI 3 generado de las rutas reales (nunca se desactualiza). Cada usuario ve solo las operaciones que su rol
     * puede usar; con ?completo=1 y permiso contratos_api.ver, todas.
     */
    public function openapi(Request $request, PermissionResolver $permissions): JsonResponse
    {
        $user = $request->user();
        $hospital = $request->attributes->get('hospital');
        $can = fn (string $p) => $permissions->allows($user->user_id, $hospital->hospital_id, $p);
        $all = $request->boolean('completo') && $can('contratos_api.ver');
        $paths = [];
        foreach (Route::getRoutes() as $route) {
            /** @var LaravelRoute $route */
            if (! str_starts_with($route->uri(), 'api/v1')) {
                continue;
            }
            $needed = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'permiso:'));
            $alternatives = $needed ? explode('|', substr($needed, 8)) : [];
            if (! $all && $alternatives !== [] && ! collect($alternatives)->contains($can)) {
                continue;
            }
            $path = '/'.substr($route->uri(), strlen('api/v1/'));
            $module = explode('/', trim($path, '/'))[0];
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $paths[$path][strtolower($method)] = [
                    'tags' => [$module], 'summary' => class_basename((string) $route->getControllerClass()).'@'.$route->getActionMethod(),
                    'x-permisos' => $alternatives, 'security' => in_array('sesion', $route->gatherMiddleware(), true) ? [['bearer' => []]] : [],
                    'parameters' => collect($route->parameterNames())->map(fn ($p) => ['name' => $p, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']])->values()->all(),
                    'responses' => ['200' => ['description' => 'Correcto'], '401' => ['description' => 'Sin sesión'], '403' => ['description' => 'Sin permiso'], '422' => ['description' => 'Datos o regla de negocio rechazada: {message, code, details}']],
                ];
            }
        }
        ksort($paths);

        return response()->json([
            'openapi' => '3.0.3',
            'info' => ['title' => 'API de Hospital Nido', 'version' => '1.0.0', 'description' => 'Todas las rutas requieren el encabezado X-Tenant-ID con el id del hospital.'],
            'servers' => [['url' => url('/api/v1')]],
            'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']]],
            'paths' => $paths,
        ]);
    }
}
