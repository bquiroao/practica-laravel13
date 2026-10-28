<?php
declare(strict_types=1);

// Autorrevisión del autor (criterio de entrada E-03): corre las pruebas del Micro-HIS y reproduce cada hallazgo
// sobre el código tal como está. Si alguien corrige un hallazgo, su línea pasa a «no reproducido». Datos ficticios.
$raiz = __DIR__ . '/../../micro-his-contratos-api';
require $raiz . '/bootstrap.php';

use MicroHis\ContratosApi\Aplicacion\{EscritorVersiones, ServicioConsulta, ServicioPublicacion, ServicioRevision};
use MicroHis\ContratosApi\Dominio\VersionNueva;
use MicroHis\ContratosApi\Persistencia\RepositorioPdo;
use MicroHis\ContratosApi\Presentacion\ControladorDocumentacion;

function linea(string $id, bool $reproducido, string $detalle): void { printf("%-5s %-15s %s\n", $id, $reproducido ? 'reproducido' : 'no reproducido', $detalle); }
function columnas(PDO $db, string $tabla): array { return array_column($db->query("PRAGMA table_info($tabla)")->fetchAll(), 'name'); }
$resultado = fn (array $r) => rtrim("{$r['status']} " . ($r['error'] ?? ''));

exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$raiz/pruebas/pruebas.php"), $salida, $codigo);
printf("ENTRADA pruebas del Micro-HIS: %s (%s)\n\n", $codigo === 0 ? 'en verde' : 'FALLAN', end($salida));

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents("$raiz/database/schema.sql"));
$app = crearControlador($db);
$version = fn (string $v, array $aud, string $tipo = 'manual') => ['slug' => 'guia-citas', 'tipo' => $tipo, 'version' => $v, 'audiencia' => $aud, 'usuario' => 'ana'];
$http = (new ReflectionClassConstant(ControladorDocumentacion::class, 'HTTP'))->getValue();

// H-01: CU-06 / R-06. Usuario y roles llegan en el cuerpo de la petición: quien escriba roles = [admin] los obtiene. No existe E1.
$app->atender('enviar', $version('1.0.0', ['medico', 'enfermeria']));
$app->atender('aprobar', ['id' => 1, 'usuario' => 'luis']);
$r = $app->atender('consultar', ['slug' => 'guia-citas', 'roles' => ['medico']]);
linea('H-01', !isset($http['E1']), "consultar con roles = [medico] escritos por el cliente = {$resultado($r)}; códigos: " . implode(' ', array_keys($http)));

// H-02: R-04 y R-09 (CU-05). La versión no lleva contenido: un OpenAPI va a revisión sin lint y sin revisar datos reales. No existe E4.
$r = $app->atender('enviar', ['slug' => 'api-citas', 'tipo' => 'openapi', 'version' => '1.0.0', 'audiencia' => ['integrador'], 'usuario' => 'ana', 'contenido' => 'esto no es OpenAPI']);
linea('H-02', $r['status'] === 201, "enviar un openapi inválido = {$resultado($r)} (se esperaba 422 E4); columnas: " . implode(', ', columnas($db, 'doc_versions')));

// H-03: R-10. reviewed_by se sobrescribe: al marcar obsoleta se pierde quién aprobó.
$app->atender('aprobar', ['id' => $app->atender('enviar', $version('1.1.0', ['medico']))['id'], 'usuario' => 'luis']);
$app->atender('obsoleta', ['id' => 1, 'usuario' => 'marta']);
$revisor = $db->query('SELECT reviewed_by FROM doc_versions WHERE id = 1')->fetchColumn();
linea('H-03', $revisor !== 'luis', "1.0.0 aprobada por luis y marcada obsoleta por marta: reviewed_by = $revisor");

// H-04: R-02. Dos autores envían la misma versión a la vez: el índice único evita el duplicado, pero la respuesta es 503 E8, no 409 E3.
$intercalado = new class(new RepositorioPdo($db), fn () => $app->atender('enviar', $version('2.0.0', ['medico']))) implements EscritorVersiones {
    private bool $hecho = false;
    public function __construct(private RepositorioPdo $real, private Closure $otra) {}
    public function versiones(string $slug): array { $r = $this->real->versiones($slug); if (!$this->hecho) { $this->hecho = true; ($this->otra)(); } return $r; }
    public function guardarEnRevision(VersionNueva $v, string $autor): int { return $this->real->guardarEnRevision($v, $autor); }
};
$repo = new RepositorioPdo($db);
$r = (new ControladorDocumentacion(new ServicioPublicacion($intercalado), new ServicioRevision($repo), new ServicioConsulta($repo)))
    ->atender('enviar', ['usuario' => 'pedro'] + $version('2.0.0', ['medico']));
linea('H-04', $resultado($r) !== '409 E3', "la solicitud que empezó primero = {$resultado($r)} (se esperaba 409 E3)");

// H-05: R-08 / CU-04. La obsoleta no guarda qué versión la reemplaza; enfermería, fuera de la audiencia de la 1.1.0, se queda sin aviso.
$r = $app->atender('consultar', ['slug' => 'guia-citas', 'roles' => ['enfermeria']]);
linea('H-05', !in_array('replaced_by', columnas($db, 'doc_versions'), true), "enfermería consulta guia-citas sin versión = {$resultado($r)}, sin aviso de la 1.0.0 obsoleta");
