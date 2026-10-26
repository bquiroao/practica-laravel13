<?php

declare(strict_types=1);

/*
 * Suite de CONTRATO de los puertos de persistencia. La misma función se ejecuta contra el
 * adaptador InMemory (CON-MEM-*) y contra el PDO sobre PostgreSQL (CON-PDO-*, si hay
 * credenciales). Si los dos pasan lo mismo, son sustituibles (LSP, semana 2).
 */

use Pacientes\Aplicacion\Excepciones\ClaveIdempotenciaEnUso;
use Pacientes\Dominio\Excepciones\ConflictoVersion;
use Pacientes\Dominio\Excepciones\DocumentoDuplicado;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\DatosPaciente;
use Pacientes\Dominio\Modelo\Dpi;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Persistencia\ConjuntoAdaptadores;
use Pacientes\Persistencia\FabricaEnMemoria;
use Pacientes\Persistencia\FabricaPdo;
use Pacientes\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Pacientes\Persistencia\Pdo\FabricaConexion;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;
use Pacientes\Pruebas\Dobles\Escenario;

function pacienteContrato(string $nombres, string $hospital = Escenario::H_ORIENTE, ?string $dpi = null, string $nacimiento = '1990-01-01'): Paciente
{
    return Paciente::desdeDatos($hospital, new DatosPaciente($nombres, 'Pérez Gómez', $nacimiento, 'unknown', '+502 2222 3333',
        $dpi === null ? null : 'DPI', $dpi), new DateTimeImmutable('2026-10-21'));
}

/**
 * @param Closure(): array{0: ConjuntoAdaptadores, 1: Closure(): int} $fabrica adaptadores vacíos y un contador de eventos
 */
function contratoRepositorios(string $prefijo, Closure $fabrica): void
{
    prueba("$prefijo-01", 'Contrato: registrar asigna código único y versión 1; porCodigo devuelve lo guardado y solo en su hospital', function () use ($fabrica): void {
        [$a] = $fabrica();
        $ana = $a->repositorio->registrar(pacienteContrato('Ana', dpi: Escenario::DPI_ANA));
        $beto = $a->repositorio->registrar(pacienteContrato('Beto'));
        igual(1, $ana->version, 'versión');
        afirmar($ana->codigo !== null && $ana->codigo !== $beto->codigo, 'códigos únicos');
        $leida = $a->consulta->porCodigo(Escenario::H_ORIENTE, (string) $ana->codigo);
        igual(['Ana', '+502 2222 3333', Dpi::desde(Escenario::DPI_ANA)->canonico(), Paciente::ACTIVO, '1990-01-01'],
            [$leida?->nombres, $leida?->telefono, $leida?->identificador?->canonico(), $leida?->estado, $leida?->nacimiento->format('Y-m-d')], 'ida y vuelta');
        igual(null, $a->consulta->porCodigo(Escenario::H_NORTE, (string) $ana->codigo), 'otro hospital');
        igual(null, $a->consulta->porCodigo(Escenario::H_ORIENTE, 'P-999999'), 'inexistente');
    });

    prueba("$prefijo-02", 'Contrato: buscar solo en el hospital, máx. 20, ordenado; por documento y código; comodines literales', function () use ($fabrica): void {
        [$a] = $fabrica();
        for ($i = 0; $i < 23; $i++) {
            $a->repositorio->registrar(pacienteContrato('Persona ' . chr(87 - $i), dpi: $i === 0 ? Escenario::DPI_LUIS : null, nacimiento: sprintf('1990-01-%02d', $i + 1)));
        }
        $a->repositorio->registrar(pacienteContrato('Persona Norte', Escenario::H_NORTE));

        $lista = $a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'PÉREZ'));
        igual(20, count($lista), 'límite');
        afirmar(array_filter($lista, static fn (Paciente $p): bool => $p->hospital !== Escenario::H_ORIENTE) === [], 'otro hospital en la lista');
        $ordenada = $lista;
        usort($ordenada, [Paciente::class, 'compararPorNombre']);
        igual(array_map(static fn (Paciente $p) => $p->codigo, $ordenada), array_map(static fn (Paciente $p) => $p->codigo, $lista), 'orden');
        igual(1, count($a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(tipoDocumento: 'DPI', numeroDocumento: Escenario::DPI_LUIS))), 'por documento');
        igual(1, count($a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(codigo: (string) $lista[0]->codigo))), 'por código');
        igual(1, count($a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'perez', nacimiento: '1990-01-15'))), 'con fecha exacta');
        igual(0, count($a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'perez', nacimiento: '1991'))), 'con año sin coincidencias');
        igual([], $a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'per%ez')), '% es literal');
        igual([], $a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'zzzz')), 'sin resultados');
    });

    prueba("$prefijo-03", 'Contrato: actualizar con la versión vigente da +1 y se lee; con una vieja, ConflictoVersion sin cambios', function () use ($fabrica): void {
        [$a] = $fabrica();
        $ana = $a->repositorio->registrar(pacienteContrato('Ana'));
        $a->consulta->porCodigo(Escenario::H_ORIENTE, (string) $ana->codigo); // lectura previa (calienta cualquier caché)
        igual(2, $a->repositorio->actualizar($ana->conDatosDe(pacienteContrato('Ana María')), 1)->version, 'versión + 1');
        igual('Ana María', $a->consulta->porCodigo(Escenario::H_ORIENTE, (string) $ana->codigo)?->nombres, 'se lee lo nuevo');
        $e = lanza(ConflictoVersion::class, fn () => $a->repositorio->actualizar($ana->conDatosDe(pacienteContrato('Anabel')), 1), 'versión vieja');
        igual(2, $e->detalle['version_vigente'], 'versión vigente');
        igual('Ana María', $a->consulta->porCodigo(Escenario::H_ORIENTE, (string) $ana->codigo)?->nombres, 'sin cambios');
        lanza(PacienteNoEncontrado::class, fn () => $a->repositorio->actualizar(pacienteContrato('X', Escenario::H_NORTE)->conCodigo((string) $ana->codigo, 2), 2), 'otro hospital');
    });

    prueba("$prefijo-04", 'Contrato: el documento es único en toda la red al registrar y al actualizar (DocumentoDuplicado)', function () use ($fabrica): void {
        [$a] = $fabrica();
        $a->repositorio->registrar(pacienteContrato('Ana', dpi: Escenario::DPI_ANA));
        lanza(DocumentoDuplicado::class, fn () => $a->repositorio->registrar(pacienteContrato('Otra', Escenario::H_NORTE, Escenario::DPI_ANA)), 'registro en otro hospital');
        $luis = $a->repositorio->registrar(pacienteContrato('Luis', dpi: Escenario::DPI_LUIS));
        lanza(DocumentoDuplicado::class, fn () => $a->repositorio->actualizar($luis->conDatosDe(pacienteContrato('Luis', dpi: Escenario::DPI_ANA)), 1), 'actualizar a documento ajeno');
        igual(2, $a->repositorio->actualizar($luis->conDatosDe(pacienteContrato('Luis F', dpi: Escenario::DPI_LUIS)), 1)->version, 'conservar el propio documento');
    });

    prueba("$prefijo-05", 'Contrato: el índice ve toda la red y libera el documento anterior cuando cambia', function () use ($fabrica): void {
        [$a] = $fabrica();
        $ana = $a->repositorio->registrar(pacienteContrato('Ana', Escenario::H_NORTE, Escenario::DPI_ANA, '1985-03-14'));
        $homonima = $a->repositorio->registrar(pacienteContrato('ANA', Escenario::H_ORIENTE, null, '1985-03-14'));
        igual($ana->codigo, $a->indice->titularDe(Dpi::desde('2547 89632 0101')), 'titular en otro hospital');
        igual([$ana->codigo, $homonima->codigo], $a->indice->coincidenciasProbables('ana perez gomez', new DateTimeImmutable('1985-03-14')), 'coincidencias');
        $a->repositorio->actualizar($ana->conDatosDe(pacienteContrato('Ana', Escenario::H_NORTE, Escenario::DPI_NUEVO, '1985-03-14')), 1);
        igual(null, $a->indice->titularDe(Dpi::desde(Escenario::DPI_ANA)), 'documento anterior libre');
        igual($ana->codigo, $a->indice->titularDe(Dpi::desde(Escenario::DPI_NUEVO)), 'documento nuevo');
    });

    prueba("$prefijo-06", 'Contrato: la transacción confirma paciente + evento juntos, o revierte los dos', function () use ($fabrica): void {
        [$a, $contarEventos] = $fabrica();
        $evento = static fn (?string $codigo): EventoAuditoria => new EventoAuditoria(EventoAuditoria::REGISTRADO, 'admision-01', Escenario::H_ORIENTE, $codigo, ['prueba' => true]);
        $a->transaccion->ejecutar(function () use ($a, $evento): void {
            $p = $a->repositorio->registrar(pacienteContrato('Ana', dpi: Escenario::DPI_ANA));
            $a->auditoria->registrar($evento($p->codigo));
        });
        igual(1, $contarEventos(), 'evento confirmado');
        lanza(RuntimeException::class, fn () => $a->transaccion->ejecutar(function () use ($a, $evento): void {
            $p = $a->repositorio->registrar(pacienteContrato('Luis', dpi: Escenario::DPI_LUIS));
            $a->auditoria->registrar($evento($p->codigo));
            throw new RuntimeException('falla después de escribir');
        }), 'rollback');
        igual([], $a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(tipoDocumento: 'DPI', numeroDocumento: Escenario::DPI_LUIS)), 'paciente revertido');
        igual(1, $contarEventos(), 'evento revertido');
    });

    prueba("$prefijo-07", 'Contrato: el almacén de idempotencia recuerda la respuesta, rechaza la clave repetida y participa del ROLLBACK', function () use ($fabrica): void {
        [$a] = $fabrica();
        $huella = str_repeat('a', 64);
        igual(null, $a->idempotencia->buscar(Escenario::H_ORIENTE, 'admision-01', 'clave-000001'), 'vacío');
        $a->idempotencia->guardar(Escenario::H_ORIENTE, 'admision-01', 'clave-000001', $huella, ['codigo' => 'P-000001', 'version' => 1]);
        igual(['huella' => $huella, 'datos' => ['codigo' => 'P-000001', 'version' => 1]], $a->idempotencia->buscar(Escenario::H_ORIENTE, 'admision-01', 'clave-000001'), 'ida y vuelta');
        igual(null, $a->idempotencia->buscar(Escenario::H_ORIENTE, 'admision-02', 'clave-000001'), 'otra clave por actor');
        lanza(ClaveIdempotenciaEnUso::class, fn () => $a->idempotencia->guardar(Escenario::H_ORIENTE, 'admision-01', 'clave-000001', $huella, []), 'repetida');
        lanza(RuntimeException::class, fn () => $a->transaccion->ejecutar(function () use ($a, $huella): void {
            $a->idempotencia->guardar(Escenario::H_ORIENTE, 'admision-01', 'clave-000002', $huella, ['x' => 1]);
            throw new RuntimeException('falla después de guardar');
        }), 'rollback');
        igual(null, $a->idempotencia->buscar(Escenario::H_ORIENTE, 'admision-01', 'clave-000002'), 'revertida');
    });
    prueba("$prefijo-08", 'Contrato (semana 6, R-11): la marca de protección se guarda y se lee; la búsqueda por nombre la respeta; el índice sabe quién es protegido', function () use ($fabrica): void {
        [$a] = $fabrica();
        $ana = $a->repositorio->registrar(pacienteContrato('Ana', dpi: Escenario::DPI_ANA)->conProteccion(true));
        $beto = $a->repositorio->registrar(pacienteContrato('Beto'));
        igual(true, $a->consulta->porCodigo(Escenario::H_ORIENTE, (string) $ana->codigo)?->identidadProtegida, 'ida y vuelta');
        igual([(string) $beto->codigo], array_map(static fn ($p) => $p->codigo, $a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'perez'))), 'sin permiso: solo Beto');
        igual(2, count($a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'perez')->incluyendoProtegidos(true))), 'con permiso: ambos');
        igual(1, count($a->consulta->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(tipoDocumento: 'DPI', numeroDocumento: Escenario::DPI_ANA))), 'por documento sí aparece');
        igual([(string) $ana->codigo], $a->indice->protegidos([(string) $ana->codigo, (string) $beto->codigo, 'P-999999']), 'índice');
        igual([], $a->indice->protegidos([]), 'lista vacía');
        $a->repositorio->actualizar($a->consulta->porCodigo(Escenario::H_ORIENTE, (string) $ana->codigo)->conProteccion(false), 1);
        igual([], $a->indice->protegidos([(string) $ana->codigo]), 'quitar la marca');
    });
}

// InMemory: siempre.
contratoRepositorios('CON-MEM', static function (): array {
    $a = FabricaEnMemoria::crear();
    $auditoria = $a->auditoria;

    return [$a, static fn (): int => $auditoria instanceof RegistroAuditoriaEnMemoria ? count($auditoria->eventos()) : -1];
});

// PDO: solo con PostgreSQL disponible; esquema temporal que se elimina al terminar.
$dsn = getenv('PACIENTES_DB_DSN');
if ($dsn === false || $dsn === '') {
    prueba('CON-PDO', 'Contrato contra PostgreSQL (las mismas 8 verificaciones)', static fn () => omitir('defina PACIENTES_DB_DSN para ejecutarla'));

    return;
}

$pdoContrato = FabricaConexion::crear($dsn, getenv('PACIENTES_DB_USUARIO') ?: 'postgres', getenv('PACIENTES_DB_CLAVE') ?: '');
$esquemaContrato = 'contrato_pacientes_' . bin2hex(random_bytes(4));
$pdoContrato->exec("CREATE SCHEMA $esquemaContrato");
$pdoContrato->exec("SET search_path TO $esquemaContrato");
$pdoContrato->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql'));
$proteccionContrato = new ProteccionDocumentos(random_bytes(32), random_bytes(32));

try {
    contratoRepositorios('CON-PDO', static function () use ($pdoContrato, $proteccionContrato): array {
        $pdoContrato->exec('TRUNCATE pacientes, eventos_auditoria, idempotencia_registros RESTART IDENTITY');
        $pdoContrato->exec('ALTER SEQUENCE pacientes_codigo_seq RESTART');

        return [FabricaPdo::sobre($pdoContrato, $proteccionContrato),
            static fn (): int => (int) $pdoContrato->query('SELECT count(*) FROM eventos_auditoria')->fetchColumn()];
    });
} finally {
    $pdoContrato->exec("DROP SCHEMA $esquemaContrato CASCADE");
}
