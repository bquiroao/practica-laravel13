<?php

declare(strict_types=1);

use Pacientes\Dominio\Excepciones\ConflictoVersion;
use Pacientes\Dominio\Excepciones\DocumentoDuplicado;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Excepciones\PacienteNoEncontrado;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\DatosPaciente;
use Pacientes\Dominio\Modelo\Dpi;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Persistencia\Pdo\FabricaConexion;
use Pacientes\Persistencia\Pdo\PdoIndiceDuplicados;
use Pacientes\Persistencia\Pdo\PdoRegistroAuditoria;
use Pacientes\Persistencia\Pdo\PdoRepositorioPacientes;
use Pacientes\Persistencia\Pdo\PdoTransaccion;
use Pacientes\Persistencia\Seguridad\ProteccionDocumentos;
use Pacientes\Pruebas\Dobles\Escenario;
use Pacientes\Pruebas\Dobles\PdoGuionado;
use Pacientes\Pruebas\Dobles\PdoQueFalla;

$proteccion = new ProteccionDocumentos(str_repeat('h', 32), str_repeat('c', 32));
$ana = Paciente::desdeDatos(Escenario::H_ORIENTE, new DatosPaciente('Ana Lucía', 'López Pérez', '1985-03-14', 'female',
    '+502 5555 1234', 'DPI', Escenario::DPI_ANA), new DateTimeImmutable('2026-10-21'));
$filaAna = static fn (int $version = 1) => [
    'code' => 'P-000001', 'hospital_id' => Escenario::H_ORIENTE, 'given_name' => 'Ana Lucía', 'family_name' => 'López Pérez',
    'birth_date' => '1985-03-14', 'sex' => 'female', 'contact' => '+502 5555 1234', 'status' => 'active',
    'documento_cifrado' => $proteccion->cifrar(Dpi::desde(Escenario::DPI_ANA)), 'version' => $version,
];

prueba('PER-01', 'Registrar: un INSERT preparado con parámetros con nombre; el DPI no viaja en claro', function () use ($proteccion, $ana): void {
    $pdo = new PdoGuionado([['patron' => '/^INSERT INTO pacientes/', 'filas' => [['code' => 'P-000007', 'version' => 1]]]]);
    $guardado = (new PdoRepositorioPacientes($pdo, $proteccion))->registrar($ana);

    igual(['P-000007', 1], [$guardado->codigo, $guardado->version], 'código y versión devueltos');
    igual(1, count($pdo->ejecutadas), 'sentencias');
    $p = $pdo->ejecutadas[0]['parametros'];
    afirmar(!str_contains((string) json_encode($p), Escenario::DPI_ANA) && !str_contains($pdo->ejecutadas[0]['sql'], Escenario::DPI_ANA), 'el DPI completo viaja a la base');
    igual($proteccion->hash(Dpi::desde(Escenario::DPI_ANA)), $p['documento_hash'], 'hash');
    igual('*********0101', $p['documento_enmascarado'], 'enmascarado');
    igual(Dpi::desde(Escenario::DPI_ANA)->canonico(), $proteccion->descifrar((string) $p['documento_cifrado'])->canonico(), 'cifrado reversible');
    igual(['ana lucia lopez perez', 'lopez perez'], [$p['nombre_normalizado'], $p['apellidos_normalizados']], 'normalizados');
});

prueba('PER-02', 'Búsqueda por nombre: comodines del usuario escapados, límite 20 como parámetro y filas hidratadas', function () use ($proteccion, $filaAna): void {
    $pdo = new PdoGuionado([['patron' => "/LIKE :patron ESCAPE '!'.*to_char\\(birth_date/s", 'filas' => [$filaAna()]]]);
    $lista = (new PdoRepositorioPacientes($pdo, $proteccion))->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'PÉR%_ez', nacimiento: '1985'));

    igual(['hospital' => Escenario::H_ORIENTE, 'limite' => 20, 'patron' => '%per!%!_ez%', 'fecha' => '1985%'], $pdo->ejecutadas[0]['parametros'], 'parámetros');
    igual(1, count($lista), 'resultados');
    igual(['P-000001', '1985-03-14', '*********0101', 1], [$lista[0]->codigo, $lista[0]->nacimiento->format('Y-m-d'),
        $lista[0]->identificador?->enmascarado(), $lista[0]->version], 'paciente hidratado');
});

prueba('PER-03', 'Conexión caída (08006) en cualquier operación → ErrorPersistencia; ninguna PDOException sube', function () use ($proteccion, $ana): void {
    $pdo = new PdoQueFalla('08006');
    $repo = new PdoRepositorioPacientes($pdo, $proteccion);
    $indice = new PdoIndiceDuplicados($pdo, $proteccion);
    $operaciones = [
        'porCodigo' => fn () => $repo->porCodigo(Escenario::H_ORIENTE, 'P-000001'),
        'buscar' => fn () => $repo->buscar(Escenario::H_ORIENTE, CriterioBusqueda::desde(nombre: 'lopez')),
        'registrar' => fn () => $repo->registrar($ana),
        'actualizar' => fn () => $repo->actualizar($ana->conCodigo('P-000001', 1), 1),
        'titularDe' => fn () => $indice->titularDe(Dpi::desde(Escenario::DPI_ANA)),
        'coincidencias' => fn () => $indice->coincidenciasProbables('ana', new DateTimeImmutable('1985-03-14')),
        'auditoria' => fn () => (new PdoRegistroAuditoria($pdo))->registrar(new EventoAuditoria('PACIENTE_BUSCADO', 'a', 'h', null, [])),
    ];
    foreach ($operaciones as $nombre => $operacion) {
        $e = lanza(ErrorPersistencia::class, $operacion, $nombre);
        igual('08006', $e->sqlState, "$nombre sqlState");
        afirmar($e->getPrevious() instanceof PDOException, "$nombre conserva la causa");
    }
});

prueba('PER-04', 'Violación de unicidad (23505) al insertar o actualizar → DocumentoDuplicado (409 DOCUMENTO_EN_USO)', function () use ($proteccion, $ana): void {
    $repo = new PdoRepositorioPacientes(new PdoGuionado([['patron' => '/^INSERT/', 'error' => '23505'], ['patron' => '/^UPDATE/', 'error' => '23505']]), $proteccion);
    $e = lanza(DocumentoDuplicado::class, fn () => $repo->registrar($ana), 'INSERT');
    igual([ErrorDominio::CONFLICTO, 'DOCUMENTO_EN_USO'], [$e->categoria, $e->codigo], 'error');
    lanza(DocumentoDuplicado::class, fn () => $repo->actualizar($ana->conCodigo('P-000001', 1), 1), 'UPDATE');
});

prueba('PER-05', 'UPDATE … WHERE version: sin filas y versión 8 → ConflictoVersion; sin filas y sin paciente → no encontrado', function () use ($proteccion, $ana): void {
    $pdo = new PdoGuionado([['patron' => '/^UPDATE pacientes .*AND version = :version/s', 'filas' => []], ['patron' => '/^SELECT version/', 'filas' => [['version' => 8]]]]);
    $e = lanza(ConflictoVersion::class, fn () => (new PdoRepositorioPacientes($pdo, $proteccion))->actualizar($ana->conCodigo('P-000001', 7), 7), 'conflicto');
    igual(8, $e->detalle['version_vigente'], 'versión vigente');
    igual([7, 'P-000001'], [$pdo->ejecutadas[0]['parametros']['version'], $pdo->ejecutadas[0]['parametros']['codigo']], 'parámetros del UPDATE');

    $pdo = new PdoGuionado([['patron' => '/^UPDATE/', 'filas' => []], ['patron' => '/^SELECT version/', 'filas' => []]]);
    lanza(PacienteNoEncontrado::class, fn () => (new PdoRepositorioPacientes($pdo, $proteccion))->actualizar($ana->conCodigo('P-000099', 1), 1), 'inexistente');

    $pdo = new PdoGuionado([['patron' => '/^UPDATE/', 'filas' => [['version' => 8]]]]);
    igual(8, (new PdoRepositorioPacientes($pdo, $proteccion))->actualizar($ana->conCodigo('P-000001', 7), 7)->version, 'éxito: versión + 1');
});

prueba('PER-06', 'PdoTransaccion: COMMIT caído → ROLLBACK y ErrorPersistencia; error de dominio → ROLLBACK y se relanza', function (): void {
    $pdo = new PdoGuionado();
    igual('ok', (new PdoTransaccion($pdo))->ejecutar(fn () => 'ok'), 'resultado');
    igual(['BEGIN', 'COMMIT'], $pdo->transaccion, 'camino feliz');

    $pdo = new PdoGuionado();
    $pdo->fallarCommit = true;
    lanza(ErrorPersistencia::class, fn () => (new PdoTransaccion($pdo))->ejecutar(fn () => 'ok'), 'commit');
    igual(['BEGIN', 'ROLLBACK'], $pdo->transaccion, 'rollback tras commit fallido');

    $pdo = new PdoGuionado();
    lanza(ConflictoVersion::class, fn () => (new PdoTransaccion($pdo))->ejecutar(fn () => throw new ConflictoVersion(3)), 'dominio');
    igual(['BEGIN', 'ROLLBACK'], $pdo->transaccion, 'rollback tras error de dominio');
});

prueba('PER-07', 'Protección del documento: HMAC determinista, cifrado con nonce, descifra, y falla con otra clave', function () use ($proteccion): void {
    $dpi = Dpi::desde(Escenario::DPI_ANA);
    igual($proteccion->hash($dpi), $proteccion->hash(Dpi::desde('2547 89632 0101')), 'hash determinista');
    afirmar($proteccion->hash($dpi) !== $proteccion->hash(Dpi::desde(Escenario::DPI_LUIS)), 'hash distinto por documento');
    afirmar($proteccion->hash($dpi) !== (new ProteccionDocumentos(str_repeat('x', 32), str_repeat('c', 32)))->hash($dpi), 'hash depende de la clave');
    $c1 = $proteccion->cifrar($dpi);
    afirmar($c1 !== $proteccion->cifrar($dpi), 'dos cifrados iguales: falta el nonce');
    igual($dpi->canonico(), $proteccion->descifrar($c1)->canonico(), 'ida y vuelta');
    lanza(ErrorPersistencia::class, fn () => (new ProteccionDocumentos(str_repeat('h', 32), str_repeat('z', 32)))->descifrar($c1), 'otra clave');
    lanza(ErrorPersistencia::class, fn () => $proteccion->descifrar('no-es-base64!!'), 'dato dañado');
    lanza(InvalidArgumentException::class, fn () => new ProteccionDocumentos('corta', str_repeat('c', 32)), 'clave corta');
});

prueba('PER-08', 'Conexión imposible → ErrorPersistencia sin exponer la clave; la bitácora usa CAST(:detalle AS jsonb)', function (): void {
    $e = lanza(ErrorPersistencia::class, fn () => FabricaConexion::crear('driverinexistente:host=x', 'usuario', 'clave-secreta'), 'conexión');
    afirmar(!str_contains($e->getMessage(), 'clave-secreta'), 'el mensaje expone la clave');

    $pdo = new PdoGuionado([['patron' => '/^INSERT INTO eventos_auditoria .*CAST\(:detalle AS jsonb\)/s']]);
    (new PdoRegistroAuditoria($pdo))->registrar(new EventoAuditoria('PACIENTE_ACTUALIZADO', 'admision-01', Escenario::H_ORIENTE, 'P-000001', ['motivo' => 'Corrección']));
    igual('{"motivo":"Corrección"}', $pdo->ejecutadas[0]['parametros']['detalle'], 'detalle como JSON');
});
