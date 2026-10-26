<?php

declare(strict_types=1);

/*
 * Evidencia verificable de la semana 2 (Pacientes, LSP).
 *  - CA-xx : criterios de aceptación contra el diseño DESPUÉS.
 *  - LSP-xx: las mismas pruebas de contrato pasan con TODAS las implementaciones del después.
 *  - ANT-xx: esas pruebas detectan las violaciones del diseño ANTES y sus consecuencias.
 *  - PAR-01: donde el antes no tenía defectos, ambos diseños responden igual.
 *
 * Uso:  php docs/modulos/pacientes/semana-02/ejemplo-lsp/verificar.php
 * Sale con código 0 si todas las pruebas pasan, 1 si alguna falla.
 */

require __DIR__ . '/despues/autoload.php';
require __DIR__ . '/despues/composicion.php';
require __DIR__ . '/pruebas/contratos.php';

use Pacientes\Adaptadores\ReplicaLecturaPacientes;
use Pacientes\Adaptadores\RepositorioPacientesConCache;
use Pacientes\Antes\IdentificadorTemporal;
use Pacientes\Antes\ReplicaPacientes;
use Pacientes\Antes\RepositorioPacientes as RepositorioAntes;
use Pacientes\Antes\RepositorioPacientesConCache as CacheAntes;
use Pacientes\Antes\ServicioPacientes as ServicioAntes;
use Pacientes\Dominio\DatosPaciente;
use Pacientes\Dominio\Dpi;
use Pacientes\Dominio\Paciente;
use Pacientes\Dominio\Pasaporte;
use Pacientes\Dominio\Respuesta;
use Pacientes\Dominio\SolicitudActualizacion;
use Pacientes\Dominio\SolicitudRegistro;
use Pacientes\EnMemoria\IndiceMaestroEnMemoria;
use Pacientes\EnMemoria\PoliticaAccesoEnMemoria;
use Pacientes\EnMemoria\RegistroAuditoriaEnMemoria;
use Pacientes\EnMemoria\RepositorioPacientesEnMemoria;
use Pacientes\EnMemoria\TransaccionEnMemoria;
use Pacientes\Servicios\ControlAcceso;
use Pacientes\Servicios\ServicioActualizacionPacientes;
use Pacientes\Servicios\ServicioBusquedaPacientes;
use Pacientes\Servicios\ServicioRegistroPacientes;

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

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new \RuntimeException($mensaje);
    }
}

function respuesta(Respuesta $r, int $http, ?string $error = null): void
{
    igual($http, $r->http, 'HTTP');
    igual($error, $r->error(), 'código de error');
}

// ---------------------------------------------------------------------------
// Datos de prueba (ficticios)
// ---------------------------------------------------------------------------

const DPI_ANA = '2547896320101';
const DPI_LUIS = '1876543210901';
const DPI_NUEVO = '4455667780105';

function datosAna(array $cambios = []): DatosPaciente
{
    return new DatosPaciente(...array_merge([
        'nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez', 'nacimiento' => '1985-03-14', 'sexo' => 'female',
        'telefono' => '+502 5555 1234', 'tipoDocumento' => 'DPI', 'numeroDocumento' => DPI_ANA,
    ], $cambios));
}

function datosLuis(): DatosPaciente
{
    return new DatosPaciente('Luis Fernando', 'García Ruiz', '1978-11-02', 'male', null, 'DPI', DPI_LUIS);
}

function registrar(object $modulo, DatosPaciente $datos, string $actor = 'admision', string $hospital = H_ORIENTE): Respuesta
{
    return $modulo->registro->registrar(new SolicitudRegistro($actor, $hospital, $datos));
}

function actualizar(object $servicio, string $codigo, int $version, DatosPaciente $datos, ?string $motivo = null, string $actor = 'admision'): Respuesta
{
    return $servicio->actualizar(new SolicitudActualizacion($actor, H_ORIENTE, $codigo, $version, $datos, $motivo));
}

/** El servicio único del diseño ANTES sobre el repositorio indicado. */
function componerAntes(string $repositorio = 'base'): object
{
    $repo = match ($repositorio) {
        'base' => new RepositorioAntes(),
        'cache' => new CacheAntes(),
        'replica' => ReplicaPacientes::copiaDe(new RepositorioAntes()),
    };
    $indice = new IndiceMaestroEnMemoria();
    $bitacora = new RegistroAuditoriaEnMemoria();

    return (object) [
        'servicio' => new ServicioAntes($repo, $indice, $bitacora, new TransaccionEnMemoria([$repo, $indice, $bitacora]),
            new PoliticaAccesoEnMemoria(permisosDemo()), new DateTimeImmutable('2026-10-21')),
        'repositorio' => $repo,
    ];
}

// ---------------------------------------------------------------------------
// Criterios de aceptación (diseño DESPUÉS)
// ---------------------------------------------------------------------------

prueba('CA-01', 'Registro válido: 201, código, versión 1 y PACIENTE_REGISTRADO con DPI enmascarado', function (): void {
    $m = componerPacientes();
    $r = registrar($m, datosAna());
    respuesta($r, 201);
    igual('P-000001', $r->cuerpo['codigo'], 'código');
    igual(1, $r->cuerpo['version'], 'versión');
    igual(false, $r->cuerpo['identidad_provisional'], 'identidad provisional');
    $eventos = $m->bitacora->eventos('PACIENTE_REGISTRADO');
    igual(1, count($eventos), 'eventos');
    igual('*********0101', $eventos[0]->detalle['datos']['identificador'], 'identificador en la auditoría');
    afirmar(!str_contains(json_encode($eventos[0]->detalle), DPI_ANA), 'el DPI completo aparece en la auditoría');
});

prueba('CA-02', 'Datos inválidos: 422 con cada campo que falla y sin guardar nada', function (): void {
    $m = componerPacientes();
    $r = registrar($m, new DatosPaciente('', 'López', '2030-01-01', 'x', 'abc', 'DPI', '123'));
    respuesta($r, 422, 'DATOS_INVALIDOS');
    igual(['nombres', 'nacimiento', 'sexo', 'telefono', 'documento'], array_keys($r->cuerpo['campos']), 'campos inválidos');
    respuesta(registrar($m, datosAna(['nacimiento' => '1985-02-30'])), 422, 'DATOS_INVALIDOS');
    respuesta(registrar($m, datosAna(['numeroDocumento' => '1234567899901'])), 422, 'DATOS_INVALIDOS'); // departamento 99
    igual([], $m->bitacora->eventos(), 'eventos');
});

prueba('CA-03', 'Mismo DPI (escrito con espacios): 409 PACIENTE_EXISTENTE con el código y el DPI enmascarado', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna());
    $r = registrar($m, datosAna(['nombres' => 'Ana', 'numeroDocumento' => '2547 89632 0101']));
    respuesta($r, 409, 'PACIENTE_EXISTENTE');
    igual('P-000001', $r->cuerpo['codigo'], 'código existente');
    igual('*********0101', $r->cuerpo['identificador'], 'identificador');
});

prueba('CA-04', 'Coincidencia probable: 409; admisión no confirma (403); sin motivo 422; supervisor con motivo 201', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna());
    $parecida = new DatosPaciente('ana lucia', 'LOPEZ  PEREZ', '1985-03-14', 'female');
    $r = registrar($m, $parecida);
    respuesta($r, 409, 'POSIBLE_DUPLICADO');
    igual(['P-000001'], $r->cuerpo['candidatos'], 'candidatos');
    respuesta($m->registro->registrar(new SolicitudRegistro('admision', H_ORIENTE, $parecida, true, 'otra persona')), 403, 'ACCESO_DENEGADO');
    respuesta($m->registro->registrar(new SolicitudRegistro('supervisor', H_ORIENTE, $parecida, true, '  ')), 422, 'MOTIVO_REQUERIDO');
    $ok = $m->registro->registrar(new SolicitudRegistro('supervisor', H_ORIENTE, $parecida, true, 'Homónima: otra madre y otra dirección'));
    respuesta($ok, 201);
    $evento = $m->bitacora->eventos('PACIENTE_REGISTRADO')[1];
    igual('Homónima: otra madre y otra dirección', $evento->detalle['motivo'], 'motivo auditado');
    igual(['P-000001'], $evento->detalle['coincidencias_confirmadas'], 'coincidencias auditadas');
});

prueba('CA-05', 'Dos pacientes sin documento: ambos 201 (identidad provisional) y se pueden corregir', function (): void {
    $m = componerPacientes();
    $juan = registrar($m, new DatosPaciente('Juan', 'Desconocido', '2000-01-01', 'male'));
    $maria = registrar($m, new DatosPaciente('María', 'Desconocida', '1990-01-01', 'female'));
    respuesta($juan, 201);
    respuesta($maria, 201);
    igual(true, $juan->cuerpo['identidad_provisional'], 'identidad provisional');
    $r = actualizar($m->actualizacion, 'P-000001', 1, new DatosPaciente('Juan José', 'Desconocido', '2000-01-01', 'male'), 'Lo identificó un familiar');
    respuesta($r, 200);
});

prueba('CA-06', 'Búsqueda: criterio corto 422; solo el hospital activo, máx. 20, datos mínimos y auditoría sin datos', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna(['apellidos' => 'Pérez Ramos']));
    for ($i = 1; $i <= 22; $i++) {
        registrar($m, new DatosPaciente('Paciente ' . $i, 'Pérez', sprintf('1990-01-%02d', min($i, 28)), 'unknown'));
    }
    registrar($m, new DatosPaciente('Norteño', 'Pérez', '1990-01-01', 'male'), 'admision_norte', H_NORTE);

    respuesta($m->busqueda->buscar('clinico', H_ORIENTE, ['nombre' => 'pe']), 422, 'CRITERIO_INSUFICIENTE');
    $r = $m->busqueda->buscar('clinico', H_ORIENTE, ['nombre' => 'PÉREZ']);
    respuesta($r, 200);
    igual(20, $r->cuerpo['total'], 'total');
    afirmar(!in_array('Norteño Pérez', array_column($r->cuerpo['resultados'], 'nombre'), true), 'aparece un paciente de otro hospital');
    igual(['codigo', 'nombre', 'nacimiento', 'identificador'], array_keys($r->cuerpo['resultados'][0]), 'campos del resultado');
    $porDpi = $m->busqueda->buscar('clinico', H_ORIENTE, ['tipoDocumento' => 'DPI', 'numeroDocumento' => DPI_ANA]);
    igual(1, $porDpi->cuerpo['total'], 'búsqueda por DPI');
    igual('*********0101', $porDpi->cuerpo['resultados'][0]['identificador'], 'identificador en el resultado');
    $auditoria = json_encode(array_map(static fn ($e) => $e->detalle, $m->bitacora->eventos('PACIENTE_BUSCADO')));
    igual(2, count($m->bitacora->eventos('PACIENTE_BUSCADO')), 'búsquedas auditadas (la de 422 no)');
    afirmar(!str_contains($auditoria, DPI_ANA) && !str_contains(mb_strtolower($auditoria), 'perez'), 'la auditoría guarda datos en claro');
});

prueba('CA-07', 'Detalle con versión; de otro hospital da el mismo 404 que uno inexistente', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna());
    $r = $m->busqueda->detalle('clinico', H_ORIENTE, 'P-000001');
    respuesta($r, 200);
    igual(1, $r->cuerpo['paciente']['version'], 'versión');
    igual('*********0101', $r->cuerpo['paciente']['identificador'], 'identificador');
    $otroHospital = $m->busqueda->detalle('admision_norte', H_NORTE, 'P-000001');
    $inexistente = $m->busqueda->detalle('admision_norte', H_NORTE, 'P-999999');
    respuesta($otroHospital, 404, 'PACIENTE_NO_ENCONTRADO');
    igual($inexistente->cuerpo, $otroHospital->cuerpo, 'cuerpo del 404');
    igual(1, count($m->bitacora->eventos('PACIENTE_CONSULTADO')), 'consultas auditadas');
});

prueba('CA-08', 'Edición concurrente: la primera guarda (versión 2); la segunda recibe 409 y no borra la primera', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna());
    respuesta(actualizar($m->actualizacion, 'P-000001', 1, datosAna(['telefono' => '+502 4444 0001'])), 200);
    $r = actualizar($m->actualizacion, 'P-000001', 1, datosAna(['telefono' => '+502 3333 0002']));
    respuesta($r, 409, 'VERSION_DESACTUALIZADA');
    igual(2, $r->cuerpo['version_vigente'], 'versión vigente');
    igual('+502 4444 0001', $m->almacen->porCodigo(H_ORIENTE, 'P-000001')->telefono, 'teléfono guardado');
    igual(1, count($m->bitacora->eventos('PACIENTE_ACTUALIZADO')), 'actualizaciones auditadas');
});

prueba('CA-09', 'Identidad: sin motivo 422; DPI de otro paciente 409; con motivo 200 y auditoría antes/después', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna());
    registrar($m, datosLuis());
    respuesta(actualizar($m->actualizacion, 'P-000001', 1, datosAna(['nombres' => 'Ana Lucía María'])), 422, 'MOTIVO_REQUERIDO');
    respuesta(actualizar($m->actualizacion, 'P-000001', 1, datosAna(['numeroDocumento' => DPI_LUIS]), 'Corrección'), 409, 'IDENTIFICADOR_EN_USO');
    respuesta(actualizar($m->actualizacion, 'P-000001', 1, datosAna(['numeroDocumento' => DPI_NUEVO]), 'DPI reemplazado por RENAP'), 200);
    $evento = $m->bitacora->eventos('PACIENTE_ACTUALIZADO')[0];
    igual('*********0101', $evento->detalle['antes']['identificador'], 'antes');
    igual('*********0105', $evento->detalle['despues']['identificador'], 'después');
    igual('DPI reemplazado por RENAP', $evento->detalle['motivo'], 'motivo');
    igual('P-000001', $m->indice->titularDe(Dpi::desde(DPI_NUEVO)->claveBusqueda()), 'MPI con el DPI nuevo');
    igual(null, $m->indice->titularDe(Dpi::desde(DPI_ANA)->claveBusqueda()), 'MPI liberó el DPI anterior');
});

prueba('CA-10', 'Paciente fallecido: admisión recibe 403 REQUIERE_SUPERVISOR; el supervisor sí edita', function (): void {
    $m = componerPacientes();
    registrar($m, datosAna());
    $m->almacen->actualizar($m->almacen->porCodigo(H_ORIENTE, 'P-000001')->conEstado(Paciente::FALLECIDO), 1);
    respuesta(actualizar($m->actualizacion, 'P-000001', 2, datosAna(['telefono' => null])), 403, 'REQUIERE_SUPERVISOR');
    respuesta(actualizar($m->actualizacion, 'P-000001', 2, datosAna(['telefono' => null]), null, 'supervisor'), 200);
});

prueba('CA-11', 'Bitácora caída: registro y actualización responden 500 y no queda nada a medias', function (): void {
    $m = componerPacientes(auditoriaFalla: true);
    respuesta(registrar($m, datosAna()), 500, 'ERROR_INTERNO');
    igual(null, $m->almacen->porCodigo(H_ORIENTE, 'P-000001'), 'paciente guardado');
    igual(null, $m->indice->titularDe(Dpi::desde(DPI_ANA)->claveBusqueda()), 'DPI en el MPI');

    $luis = $m->almacen->registrar(Paciente::desdeDatos(H_ORIENTE, datosLuis(), new DateTimeImmutable('2026-10-21')));
    respuesta(actualizar($m->actualizacion, (string) $luis->codigo, 1, new DatosPaciente('Luis', 'García Ruiz', '1978-11-02', 'male', null, 'DPI', DPI_LUIS), 'Corrección'), 500, 'ERROR_INTERNO');
    igual('Luis Fernando', $m->almacen->porCodigo(H_ORIENTE, (string) $luis->codigo)->nombres, 'nombre guardado');
    igual(1, $m->almacen->porCodigo(H_ORIENTE, (string) $luis->codigo)->version, 'versión guardada');
});

prueba('CA-12', 'Acceso: sin hospital 400; sin permiso 403 en registrar, buscar y editar', function (): void {
    $m = componerPacientes();
    respuesta(registrar($m, datosAna(), 'admision', ''), 400, 'HOSPITAL_REQUERIDO');
    respuesta(registrar($m, datosAna(), 'clinico'), 403, 'ACCESO_DENEGADO');
    respuesta($m->busqueda->buscar('admision', H_NORTE, ['nombre' => 'lopez']), 403, 'ACCESO_DENEGADO');
    registrar($m, datosAna());
    respuesta(actualizar($m->actualizacion, 'P-000001', 1, datosAna(), null, 'clinico'), 403, 'ACCESO_DENEGADO');
});

// ---------------------------------------------------------------------------
// LSP: las mismas pruebas de contrato con todas las implementaciones del DESPUÉS
// ---------------------------------------------------------------------------

$fabricaDpi = static fn (int $persona, int $escritura): array => match ([$persona, $escritura]) {
    [1, 1] => [Dpi::desde(DPI_ANA), DPI_ANA],
    [1, 2] => [Dpi::desde('2547 89632-0101'), DPI_ANA],
    default => [Dpi::desde(DPI_LUIS), DPI_LUIS],
};
$fabricaPasaporte = static fn (int $persona, int $escritura): array => match ([$persona, $escritura]) {
    [1, 1] => [Pasaporte::desde('X1234567', 'GTM'), 'X1234567'],
    [1, 2] => [Pasaporte::desde('x123 4567', 'gtm'), 'X1234567'],
    default => [Pasaporte::desde('X7654321', 'GTM'), 'X7654321'],
};
$sembrar = static fn (object $repositorio, array $semilla): array => array_map(static fn (Paciente $p): string => (string) $repositorio->registrar($p)->codigo, $semilla);

prueba('LSP-01', 'Contrato Identificador: Dpi y Pasaporte cumplen las 4 cláusulas', function () use ($fabricaDpi, $fabricaPasaporte): void {
    igual([], contratoIdentificador($fabricaDpi), 'Dpi');
    igual([], contratoIdentificador($fabricaPasaporte), 'Pasaporte');
});

prueba('LSP-02', 'Contrato ConsultaPacientes: repositorio, réplica y caché cumplen las 8 cláusulas', function () use ($sembrar): void {
    igual([], contratoConsultaPacientes(static function (array $s) use ($sembrar): array {
        $r = new RepositorioPacientesEnMemoria();

        return [$r, $sembrar($r, $s)];
    }), 'RepositorioPacientesEnMemoria');
    igual([], contratoConsultaPacientes(static function (array $s) use ($sembrar): array {
        $r = new RepositorioPacientesEnMemoria();
        $codigos = $sembrar($r, $s);

        return [new ReplicaLecturaPacientes($r), $codigos];
    }), 'ReplicaLecturaPacientes');
    igual([], contratoConsultaPacientes(static function (array $s) use ($sembrar): array {
        $r = new RepositorioPacientesConCache(new RepositorioPacientesEnMemoria());

        return [$r, $sembrar($r, $s)];
    }), 'RepositorioPacientesConCache');
});

prueba('LSP-03', 'Contrato RepositorioPacientes: repositorio y caché cumplen las 8 cláusulas de escritura', function (): void {
    igual([], contratoRepositorioPacientes(static fn () => new RepositorioPacientesEnMemoria()), 'RepositorioPacientesEnMemoria');
    igual([], contratoRepositorioPacientes(static fn () => new RepositorioPacientesConCache(new RepositorioPacientesEnMemoria())), 'RepositorioPacientesConCache');
});

prueba('LSP-04', 'Los servicios no usan instanceof ni dependen de implementaciones concretas', function (): void {
    foreach (glob(__DIR__ . '/despues/src/Servicios/*.php') as $archivo) {
        $codigo = (string) file_get_contents($archivo);
        foreach (token_get_all($codigo) as $token) {
            afirmar(!is_array($token) || $token[0] !== T_INSTANCEOF, basename($archivo) . ' usa instanceof');
        }
        foreach (['EnMemoria\\', 'Adaptadores\\', 'Dpi', 'Pasaporte'] as $concreto) {
            afirmar(!str_contains($codigo, $concreto), basename($archivo) . " nombra $concreto");
        }
    }
    foreach ([ServicioRegistroPacientes::class, ServicioBusquedaPacientes::class, ServicioActualizacionPacientes::class] as $clase) {
        foreach ((new ReflectionClass($clase))->getConstructor()->getParameters() as $parametro) {
            $tipo = (string) $parametro->getType();
            afirmar(!str_starts_with($tipo, 'Pacientes\\EnMemoria') && !str_starts_with($tipo, 'Pacientes\\Adaptadores'), "$clase recibe $tipo");
        }
    }
});

prueba('LSP-05', 'La réplica sirve para buscar, pero el sistema de tipos impide inyectarla donde se escribe', function (): void {
    $almacen = new RepositorioPacientesEnMemoria();
    $replica = new ReplicaLecturaPacientes($almacen);
    $acceso = new ControlAcceso(new PoliticaAccesoEnMemoria(permisosDemo()));
    $bitacora = new RegistroAuditoriaEnMemoria();
    new ServicioBusquedaPacientes($replica, $bitacora, $acceso); // se acepta
    try {
        new ServicioRegistroPacientes($replica, new IndiceMaestroEnMemoria(), $bitacora, new TransaccionEnMemoria([]), $acceso, new DateTimeImmutable());
        throw new RuntimeException('se aceptó la réplica en el servicio de registro');
    } catch (TypeError) {
        // esperado: ReplicaLecturaPacientes no es RepositorioPacientes
    }
    afirmar(!method_exists($replica, 'registrar') && !method_exists($replica, 'actualizar'), 'la réplica expone métodos de escritura');
});

prueba('LSP-06', 'Sustitución en contexto: el mismo escenario da respuestas idénticas con cada implementación', function (): void {
    $escenarioLectura = static function (object $m): array {
        registrar($m, datosAna());
        registrar($m, datosLuis());

        return array_map(static fn (Respuesta $r): array => [$r->http, $r->cuerpo], [
            $m->busqueda->buscar('clinico', H_ORIENTE, ['nombre' => 'lopez']),
            $m->busqueda->buscar('clinico', H_ORIENTE, ['tipoDocumento' => 'DPI', 'numeroDocumento' => DPI_LUIS]),
            $m->busqueda->detalle('clinico', H_ORIENTE, 'P-000002'),
            $m->busqueda->detalle('admision_norte', H_NORTE, 'P-000002'),
        ]);
    };
    $base = $escenarioLectura(componerPacientes());
    igual($base, $escenarioLectura(componerPacientes('replica')), 'búsqueda desde la réplica');
    igual($base, $escenarioLectura(componerPacientes('principal', conCache: true)), 'búsqueda con caché');

    $escenarioEscritura = static function (object $m): array {
        registrar($m, datosAna());
        $m->busqueda->detalle('admision', H_ORIENTE, 'P-000001'); // calienta la caché

        return array_map(static fn (Respuesta $r): array => [$r->http, $r->error()], [
            actualizar($m->actualizacion, 'P-000001', 1, datosAna(['telefono' => '+502 4444 0001'])),
            actualizar($m->actualizacion, 'P-000001', 1, datosAna(['telefono' => '+502 3333 0002'])),
            actualizar($m->actualizacion, 'P-000001', 2, datosAna(['telefono' => '+502 3333 0002'])),
        ]);
    };
    $esperado = [[200, null], [409, 'VERSION_DESACTUALIZADA'], [200, null]];
    igual($esperado, $escenarioEscritura(componerPacientes()), 'escritura con el repositorio');
    igual($esperado, $escenarioEscritura(componerPacientes('principal', conCache: true)), 'escritura con caché');
});

// ---------------------------------------------------------------------------
// ANTES: las mismas pruebas de contrato detectan las violaciones
// ---------------------------------------------------------------------------

prueba('ANT-01', 'V1: IdentificadorTemporal implementa la interfaz pero viola I1 e I3 (clave vacía y compartida)', function (): void {
    $violaciones = contratoIdentificador(static fn (int $p, int $e): array => [new IdentificadorTemporal(), '']);
    igual(['I1 la clave de búsqueda es vacía', 'I3 dos personas distintas comparten la misma clave'], $violaciones, 'violaciones');
});

prueba('ANT-02', 'V2: ReplicaPacientes cumple la lectura, pero viola R1 (registrar lanza LogicException)', function () use ($sembrar): void {
    igual([], contratoConsultaPacientes(static function (array $s) use ($sembrar): array {
        $base = new RepositorioAntes();
        $codigos = $sembrar($base, $s);

        return [ReplicaPacientes::copiaDe($base), $codigos];
    }), 'lectura');
    $violaciones = contratoRepositorioPacientes(static fn () => new ReplicaPacientes());
    igual(1, count($violaciones), 'número de violaciones');
    afirmar(str_starts_with($violaciones[0], 'R1 registrar lanza LogicException'), 'violación esperada: ' . $violaciones[0]);
});

prueba('ANT-03', 'V3: la caché por herencia viola R5 (pierde ediciones) y R7 (acepta otro hospital)', function (): void {
    $violaciones = contratoRepositorioPacientes(static fn () => new CacheAntes());
    igual(2, count($violaciones), 'número de violaciones: ' . implode(' | ', $violaciones));
    afirmar(str_starts_with($violaciones[0], 'R5') && str_starts_with($violaciones[1], 'R7'), implode(' | ', $violaciones));
});

prueba('ANT-04', 'El servicio del antes pregunta por tipos concretos (3 instanceof) para esquivar las violaciones', function (): void {
    $tokens = token_get_all((string) file_get_contents(__DIR__ . '/antes/ServicioPacientes.php'));
    $instanceof = array_filter($tokens, static fn ($t): bool => is_array($t) && $t[0] === T_INSTANCEOF);
    igual(3, count($instanceof), 'instanceof en el código (sin contar comentarios)');
});

prueba('ANT-05', 'Consecuencia de V1: corregir el nombre de un paciente sin documento da 409 falso en el antes', function (): void {
    $antes = componerAntes();
    $juan = new DatosPaciente('Juan', 'Desconocido', '2000-01-01', 'male');
    respuesta($antes->servicio->registrar(new SolicitudRegistro('admision', H_ORIENTE, $juan)), 201);
    respuesta($antes->servicio->registrar(new SolicitudRegistro('admision', H_ORIENTE, new DatosPaciente('María', 'Desconocida', '1990-01-01', 'female'))), 201);
    $r = actualizar($antes->servicio, 'P-000001', 1, new DatosPaciente('Juan José', 'Desconocido', '2000-01-01', 'male'), 'Lo identificó un familiar');
    respuesta($r, 409, 'IDENTIFICADOR_EN_USO'); // el después responde 200 (CA-05)
});

prueba('ANT-06', 'Consecuencia de V3: con la caché del antes la segunda edición concurrente borra la primera', function (): void {
    $antes = componerAntes('cache');
    respuesta($antes->servicio->registrar(new SolicitudRegistro('admision', H_ORIENTE, datosAna())), 201);
    respuesta(actualizar($antes->servicio, 'P-000001', 1, datosAna(['telefono' => '+502 4444 0001'])), 200);
    respuesta(actualizar($antes->servicio, 'P-000001', 1, datosAna(['telefono' => '+502 3333 0002'])), 200); // debió ser 409 (CA-08)
    igual('+502 3333 0002', $antes->repositorio->porCodigo(H_ORIENTE, 'P-000001')->telefono, 'teléfono que quedó');
});

// ---------------------------------------------------------------------------
// Paridad: donde el antes no tenía defectos, la refactorización no cambia el resultado
// ---------------------------------------------------------------------------

prueba('PAR-01', 'Antes (repositorio base, pacientes con documento) y después responden igual en 8 escenarios', function (): void {
    $antes = componerAntes()->servicio;
    $despues = componerPacientes();
    $escenarios = [
        [fn () => $antes->registrar(new SolicitudRegistro('admision', H_ORIENTE, datosAna())), fn () => registrar($despues, datosAna())],
        [fn () => $antes->registrar(new SolicitudRegistro('admision', H_ORIENTE, datosAna())), fn () => registrar($despues, datosAna())],
        [fn () => $antes->registrar(new SolicitudRegistro('admision', H_ORIENTE, datosAna(['sexo' => 'x']))), fn () => registrar($despues, datosAna(['sexo' => 'x']))],
        [fn () => $antes->registrar(new SolicitudRegistro('clinico', H_ORIENTE, datosLuis())), fn () => registrar($despues, datosLuis(), 'clinico')],
        [fn () => $antes->buscar('admision', H_ORIENTE, ['nombre' => 'lopez']), fn () => $despues->busqueda->buscar('admision', H_ORIENTE, ['nombre' => 'lopez'])],
        [fn () => actualizar($antes, 'P-000001', 1, datosAna(['telefono' => null])), fn () => actualizar($despues->actualizacion, 'P-000001', 1, datosAna(['telefono' => null]))],
        [fn () => actualizar($antes, 'P-000001', 1, datosAna()), fn () => actualizar($despues->actualizacion, 'P-000001', 1, datosAna())],
        [fn () => actualizar($antes, 'P-000099', 1, datosAna()), fn () => actualizar($despues->actualizacion, 'P-000099', 1, datosAna())],
    ];
    foreach ($escenarios as $i => [$enAntes, $enDespues]) {
        $a = $enAntes();
        $d = $enDespues();
        igual([$a->http, $a->error()], [$d->http, $d->error()], 'escenario ' . ($i + 1));
    }
});

// ---------------------------------------------------------------------------
// Reporte
// ---------------------------------------------------------------------------

$fallidas = 0;
echo 'Pacientes semana 2 - verificacion LSP (PHP ' . PHP_VERSION . ')' . PHP_EOL;
echo str_repeat('-', 100) . PHP_EOL;
foreach ($resultados as [$id, $descripcion, $error]) {
    echo sprintf('[%s] %-7s %s', $error === null ? 'PASA' : 'FALLA', $id, $descripcion) . PHP_EOL;
    if ($error !== null) {
        $fallidas++;
        echo '        -> ' . $error . PHP_EOL;
    }
}
echo str_repeat('-', 100) . PHP_EOL;
echo sprintf('Total: %d  Pasan: %d  Fallan: %d', count($resultados), count($resultados) - $fallidas, $fallidas) . PHP_EOL;

exit($fallidas === 0 ? 0 : 1);
