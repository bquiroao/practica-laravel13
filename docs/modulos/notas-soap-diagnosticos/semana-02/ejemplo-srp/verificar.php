<?php

declare(strict_types=1);

/*
 * Evidencia verificable de la semana 2 (ASII-11 Notas SOAP y diagnósticos).
 * Ejecuta los criterios de aceptación (CA) contra el diseño DESPUÉS, verifica
 * la separación de responsabilidades (SRP) y compara con el diseño ANTES.
 *
 * Uso (desde la raíz del repositorio):
 *   php docs/modulos/notas-soap-diagnosticos/semana-02/ejemplo-srp/verificar.php
 * Sale con código 0 si todas las pruebas pasan y 1 si alguna falla.
 */

require __DIR__ . '/despues/autoload.php';
require __DIR__ . '/despues/composicion.php';
require __DIR__ . '/antes/ServicioNotaSoap.php';

use NotasSoap\Antes\BaseDatosFalsa;
use NotasSoap\Antes\ServicioNotaSoap;
use NotasSoap\Dominio\Diagnostico;
use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;
use NotasSoap\Dominio\UsuarioAutenticado;
use NotasSoap\EnMemoria\AutenticadorTokenEnMemoria;
use NotasSoap\EnMemoria\RegistroAuditoriaEnMemoria;
use NotasSoap\EnMemoria\RelojFijo;
use NotasSoap\EnMemoria\RepositorioDiagnosticosEnMemoria;
use NotasSoap\EnMemoria\RepositorioDiagnosticosQueFalla;
use NotasSoap\EnMemoria\RepositorioExpedientesEnMemoria;
use NotasSoap\EnMemoria\RepositorioNotasEnMemoria;
use NotasSoap\EnMemoria\TransaccionEnMemoria;
use NotasSoap\EnMemoria\VerificadorAccesoEnMemoria;
use NotasSoap\Servicios\RegistrarNotaConDiagnostico;

// ---------------------------------------------------------------------------
// Mini arnés de pruebas (sin dependencias)
// ---------------------------------------------------------------------------

$resultados = [];

function prueba(string $id, string $descripcion, callable $cuerpo): void
{
    global $resultados;
    try {
        $cuerpo();
        $resultados[] = [$id, $descripcion, null];
    } catch (\Throwable $e) {
        $resultados[] = [$id, $descripcion, $e->getMessage()];
    }
}

function igual(mixed $esperado, mixed $real, string $que): void
{
    if ($esperado !== $real) {
        throw new \RuntimeException(sprintf('%s: esperado %s, obtenido %s', $que, var_export($esperado, true), var_export($real, true)));
    }
}

// ---------------------------------------------------------------------------
// Datos ficticios
// ---------------------------------------------------------------------------

const TENANT = 'hosp-oriente';

function usuarios(): array
{
    return [
        'tok-mendez' => new UsuarioAutenticado('dra-mendez', TENANT, ['medico']),
        'tok-lopez' => new UsuarioAutenticado('dr-lopez', TENANT, ['medico']),
        'tok-ana' => new UsuarioAutenticado('enf-ana', TENANT, ['enfermeria']),
    ];
}

const ASIGNACIONES = ['epi-100' => ['dra-mendez']];
const EXPEDIENTES = [
    'exp-carlos' => ['paciente' => 'pac-carlos', 'abierto' => true],
    'exp-rosa' => ['paciente' => 'pac-rosa', 'abierto' => false],
];
const EPISODIOS = ['epi-100' => 'pac-carlos', 'epi-200' => 'pac-rosa'];

/** Petición base válida: la Dra. Méndez registra la nota de Carlos (neumonía). */
function peticion(array $cambiosBody = [], array $cambiosHeaders = []): array
{
    $body = array_replace([
        'medical_record_id' => 'exp-carlos',
        'care_episode_id' => 'epi-100',
        'subjetivo' => 'Tos productiva y fiebre de 3 días',
        'objetivo' => 'T 38.6 °C, estertores en base derecha',
        'analisis' => 'Probable neumonía adquirida en la comunidad',
        'plan' => 'Radiografía de tórax y antibiótico',
        'diagnostico' => ['code' => 'J18.9', 'description' => 'Neumonía no especificada', 'type' => 'primary'],
    ], $cambiosBody);
    $headers = array_replace(['X-Tenant-ID' => TENANT, 'Authorization' => 'Bearer tok-mendez'], $cambiosHeaders);

    return ['headers' => $headers, 'body' => $body];
}

/** Entorno DESPUÉS, nuevo e independiente para cada prueba. */
function despues(bool $falla = false): object
{
    $notas = new RepositorioNotasEnMemoria();
    $diagnosticos = $falla ? new RepositorioDiagnosticosQueFalla() : new RepositorioDiagnosticosEnMemoria();
    $auditoria = new RegistroAuditoriaEnMemoria();
    $controller = componerController(
        new AutenticadorTokenEnMemoria(usuarios()),
        new VerificadorAccesoEnMemoria(ASIGNACIONES),
        new RepositorioExpedientesEnMemoria(EXPEDIENTES, EPISODIOS),
        $notas,
        $diagnosticos,
        new TransaccionEnMemoria([$notas, $diagnosticos]),
        $auditoria,
        new RelojFijo(new \DateTimeImmutable('2026-10-12T09:30:00-06:00')),
    );

    return (object) compact('controller', 'notas', 'diagnosticos', 'auditoria');
}

/** Entorno ANTES con los mismos datos. */
function antes(bool $falla = false): object
{
    $bd = new BaseDatosFalsa();
    foreach (usuarios() as $token => $u) {
        $bd->tokens[$token] = ['id' => $u->id, 'tenant' => $u->tenantId, 'roles' => $u->roles];
    }
    $bd->asignaciones = ASIGNACIONES;
    $bd->expedientes = EXPEDIENTES;
    $bd->episodios = EPISODIOS;
    $bd->fallarInsertDiagnostico = $falla;

    return (object) ['servicio' => new ServicioNotaSoap($bd), 'bd' => $bd];
}

function codigo(array $respuesta): ?string
{
    return $respuesta['body']['error']['code'] ?? null;
}

// ---------------------------------------------------------------------------
// Criterios de aceptación (diseño DESPUÉS)
// ---------------------------------------------------------------------------

prueba('CA-01', 'Registro válido: 201, nota en borrador v1 con su diagnóstico', function (): void {
    $e = despues();
    $r = $e->controller->registrar(peticion());
    igual(201, $r['status'], 'status');
    igual('borrador', $r['body']['status'], 'estado');
    igual(1, $r['body']['version_no'], 'versión');
    igual(1, $e->notas->total(), 'notas guardadas');
    igual(1, $e->diagnosticos->total(), 'diagnósticos guardados');
    igual('NOTA_REGISTRADA', $e->auditoria->eventos[0]['evento'] ?? null, 'evento de auditoría');
});

prueba('CA-02', 'Sin X-Tenant-ID responde 400 y con token inválido 401; no guarda nada', function (): void {
    $e = despues();
    igual(400, $e->controller->registrar(peticion([], ['X-Tenant-ID' => '']))['status'], 'sin tenant');
    igual(401, $e->controller->registrar(peticion([], ['Authorization' => 'Bearer tok-falso']))['status'], 'token inválido');
    igual(0, $e->notas->total(), 'notas guardadas');
});

prueba('CA-03', 'Usuario sin rol médico (enfermería) recibe 403', function (): void {
    $e = despues();
    $r = $e->controller->registrar(peticion([], ['Authorization' => 'Bearer tok-ana']));
    igual(403, $r['status'], 'status');
    igual('acceso_denegado', codigo($r), 'código');
});

prueba('CA-04', 'Médico no asignado al episodio recibe 403', function (): void {
    $e = despues();
    igual(403, $e->controller->registrar(peticion([], ['Authorization' => 'Bearer tok-lopez']))['status'], 'status');
});

prueba('CA-05', 'Tenant del token distinto al de la solicitud recibe 403', function (): void {
    $e = despues();
    igual(403, $e->controller->registrar(peticion([], ['X-Tenant-ID' => 'hosp-norte']))['status'], 'status');
});

prueba('CA-06', 'Expediente cerrado o de otro paciente responde 409', function (): void {
    $e = despues();
    igual(409, $e->controller->registrar(peticion(['medical_record_id' => 'exp-rosa']))['status'], 'expediente cerrado');
    igual(409, $e->controller->registrar(peticion(['medical_record_id' => 'exp-no-existe']))['status'], 'expediente inexistente');
});

prueba('CA-07', 'Falta una sección SOAP: 422 nota_incompleta y no se guarda nada', function (): void {
    $e = despues();
    $r = $e->controller->registrar(peticion(['plan' => '   ']));
    igual(422, $r['status'], 'status');
    igual('nota_incompleta', codigo($r), 'código');
    igual(0, $e->notas->total(), 'notas guardadas');
});

prueba('CA-08', 'Código CIE-10 inválido: 422 y NO queda nota huérfana', function (): void {
    $e = despues();
    $r = $e->controller->registrar(peticion(['diagnostico' => ['code' => 'ZZZ-1', 'description' => 'x', 'type' => 'primary']]));
    igual(422, $r['status'], 'status');
    igual('diagnostico_invalido', codigo($r), 'código');
    igual(0, $e->notas->total(), 'notas guardadas');
});

prueba('CA-09', 'Tipo de diagnóstico no permitido o descripción vacía: 422', function (): void {
    $e = despues();
    igual(422, $e->controller->registrar(peticion(['diagnostico' => ['code' => 'J18.9', 'description' => 'Neumonía', 'type' => 'presuntivo']]))['status'], 'tipo');
    igual(422, $e->controller->registrar(peticion(['diagnostico' => ['code' => 'J18.9', 'description' => ' ', 'type' => 'primary']]))['status'], 'descripción');
});

prueba('CA-10', 'Falla la base al guardar el diagnóstico: 500 y se revierte la nota', function (): void {
    $e = despues(falla: true);
    $r = $e->controller->registrar(peticion());
    igual(500, $r['status'], 'status');
    igual('error_persistencia', codigo($r), 'código');
    igual(0, $e->notas->total(), 'notas guardadas tras la reversión');
    igual([], $e->auditoria->eventos, 'eventos de auditoría');
});

prueba('CA-11', 'Firmar: la nota pasa a firmada; firmarla otra vez responde 409', function (): void {
    $e = despues();
    $id = $e->controller->registrar(peticion())['body']['clinical_note_id'];
    $r = $e->controller->firmar(peticion(), $id);
    igual(200, $r['status'], 'firma');
    igual('firmada', $r['body']['status'], 'estado');
    igual(409, $e->controller->firmar(peticion(), $id)['status'], 'segunda firma');
});

prueba('CA-12', 'El autor sale del token aunque el cliente mande otro author_user_ref_id', function (): void {
    $e = despues();
    $r = $e->controller->registrar(peticion(['author_user_ref_id' => 'dr-lopez']));
    igual('dra-mendez', $r['body']['author_user_ref_id'], 'autor');
});

// ---------------------------------------------------------------------------
// Verificación de SRP
// ---------------------------------------------------------------------------

prueba('SRP-01', 'Servicios y dominio no contienen SQL ni HTTP', function (): void {
    $prohibido = ['PDO', 'SELECT ', 'INSERT ', 'http_response_code', '$_SERVER', 'header(', "'status'"];
    foreach (glob(__DIR__ . '/despues/src/{Servicios,Dominio}/*.php', GLOB_BRACE) as $archivo) {
        $codigo = (string) file_get_contents($archivo);
        foreach ($prohibido as $texto) {
            if (str_contains($codigo, $texto)) {
                throw new \RuntimeException(basename($archivo) . " contiene «{$texto}»");
            }
        }
    }
});

prueba('SRP-02', 'Cada regla vive en una sola clase (CIE-10, secciones SOAP, códigos HTTP)', function (): void {
    $buscar = static function (string $texto): array {
        $hallados = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/despues/src')) as $f) {
            if ($f->isFile() && str_contains((string) file_get_contents($f->getPathname()), $texto)) {
                $hallados[] = $f->getFilename();
            }
        }

        return $hallados;
    };
    igual(['Diagnostico.php'], $buscar('[A-Z][0-9]{2}'), 'formato CIE-10');
    igual(['SeccionesSoap.php'], $buscar("'analisis' =>"), 'regla de las cuatro secciones');
    igual(['NotaSoapController.php'], $buscar('422'), 'códigos HTTP');
});

prueba('SRP-03', 'El servicio solo depende de interfaces y se prueba sin base de datos', function (): void {
    foreach ((new \ReflectionClass(RegistrarNotaConDiagnostico::class))->getConstructor()->getParameters() as $p) {
        $tipo = (string) $p->getType();
        if (!interface_exists($tipo)) {
            throw new \RuntimeException("El parámetro \${$p->getName()} depende de la clase concreta {$tipo}");
        }
    }
});

prueba('SRP-04', 'La regla del diagnóstico se prueba sola, sin servicio ni repositorios', function (): void {
    igual('J18.9', Diagnostico::registrar(' j18.9 ', 'Neumonía', 'primary')->codigo, 'código normalizado');
    try {
        Diagnostico::registrar('J1', 'Neumonía', 'primary');
        throw new \RuntimeException('Aceptó un código inválido');
    } catch (DiagnosticoInvalido) {
    }
});

// ---------------------------------------------------------------------------
// Comparación con el diseño ANTES
// ---------------------------------------------------------------------------

prueba('PAR-01', 'Antes y después responden el mismo código HTTP en 8 escenarios', function (): void {
    $escenarios = [
        'válido' => peticion(),
        'sin tenant' => peticion([], ['X-Tenant-ID' => '']),
        'token inválido' => peticion([], ['Authorization' => 'Bearer x']),
        'enfermería' => peticion([], ['Authorization' => 'Bearer tok-ana']),
        'no asignado' => peticion([], ['Authorization' => 'Bearer tok-lopez']),
        'expediente cerrado' => peticion(['medical_record_id' => 'exp-rosa']),
        'sección vacía' => peticion(['subjetivo' => '']),
        'CIE-10 inválido' => peticion(['diagnostico' => ['code' => '18.9', 'description' => 'x', 'type' => 'primary']]),
    ];
    foreach ($escenarios as $nombre => $p) {
        igual(antes()->servicio->registrar($p)['status'], despues()->controller->registrar($p)['status'], $nombre);
    }
});

prueba('DEF-01', 'Defecto corregido: antes deja nota huérfana con CIE-10 inválido; después no', function (): void {
    $p = peticion(['diagnostico' => ['code' => 'ZZZ', 'description' => 'x', 'type' => 'primary']]);
    $a = antes();
    $a->servicio->registrar($p);
    igual(1, count($a->bd->notas), 'notas huérfanas en ANTES');
    $d = despues();
    $d->controller->registrar($p);
    igual(0, $d->notas->total(), 'notas huérfanas en DESPUÉS');
});

prueba('DEF-02', 'Defecto corregido: antes deja la nota si falla el diagnóstico; después revierte', function (): void {
    $a = antes(falla: true);
    $a->servicio->registrar(peticion());
    igual(1, count($a->bd->notas), 'notas sin diagnóstico en ANTES');
    $d = despues(falla: true);
    $d->controller->registrar(peticion());
    igual(0, $d->notas->total(), 'notas sin diagnóstico en DESPUÉS');
});

prueba('DEF-03', 'Defecto corregido: antes acepta el autor del cuerpo; después usa el token', function (): void {
    $p = peticion(['author_user_ref_id' => 'dr-lopez']);
    igual('dr-lopez', antes()->servicio->registrar($p)['body']['author_user_ref_id'], 'autor en ANTES');
    igual('dra-mendez', despues()->controller->registrar($p)['body']['author_user_ref_id'], 'autor en DESPUÉS');
});

// ---------------------------------------------------------------------------
// Reporte
// ---------------------------------------------------------------------------

$fallan = 0;
echo 'Notas SOAP semana 2 - verificacion SRP (PHP ' . PHP_VERSION . ")\n";
echo str_repeat('-', 78) . "\n";
foreach ($resultados as [$id, $descripcion, $error]) {
    printf("[%s] %-7s %s\n", $error === null ? 'PASA' : 'FALLA', $id, $descripcion);
    if ($error !== null) {
        $fallan++;
        echo "         -> {$error}\n";
    }
}
echo str_repeat('-', 78) . "\n";
printf("Total: %d  Pasan: %d  Fallan: %d\n", count($resultados), count($resultados) - $fallan, $fallan);
exit($fallan === 0 ? 0 : 1);
