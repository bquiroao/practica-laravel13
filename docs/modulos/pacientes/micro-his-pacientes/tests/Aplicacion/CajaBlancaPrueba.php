<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas de CAJA BLANCA del flujo «registro, búsqueda y actualización segura de un paciente».
 * Diseñadas LEYENDO el código: caminos básicos (McCabe), ramas y su orden, y MC/DC de las condiciones
 * compuestas. Cobertura medida con phpdbg: docs/modulos/pacientes/semana-15/herramientas/cobertura-caja-blanca.php
 */

use Pacientes\Aplicacion\Comandos\ComandoActualizar;
use Pacientes\Aplicacion\Comandos\ComandoRegistrar;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\Capacidades;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\DatosPaciente;
use Pacientes\Dominio\Modelo\Dpi;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Modelo\Identificador;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\IndiceDuplicados;
use Pacientes\Dominio\Servicios\PoliticaConfidencialidad;
use Pacientes\Dominio\Servicios\PoliticaDuplicados;
use Pacientes\Dominio\Servicios\PoliticaEdicion;
use Pacientes\Dominio\Servicios\RevisionDeFicha;
use Pacientes\Persistencia\EnMemoria\IndiceDuplicadosEnMemoria;
use Pacientes\Persistencia\EnMemoria\RepositorioPacientesEnMemoria;
use Pacientes\Pruebas\Dobles\Escenario;

/** [estado, código de error] de un Resultado, para comparar caminos en una línea. */
$cbSalida = static fn (Resultado $r): array => [$r->estado, $r->error];

prueba('CB-01', 'Caminos básicos de RegistrarPaciente::ejecutar (V(G) = 7): permiso, idempotencia (3 decisiones), revisión, transacción', function () use ($cbSalida): void {
    $registrar = static fn (object $e, string $actor = Escenario::ADMISION, ?string $clave = null, array $datos = []): Resultado =>
        $e->casos['registrar']->ejecutar(new ComandoRegistrar($actor, Escenario::H_ORIENTE, $datos ?: Escenario::datosAna(), false, null, $clave));
    $estado = static fn (object $e): array => [count($e->repositorio->todos()), count($e->bitacora->eventos(EventoAuditoria::REGISTRADO))];

    // C1 · con permiso, sin clave, ficha válida → 201; no se guarda ninguna clave
    $e = Escenario::crear();
    igual([Resultado::CREADO, null], $cbSalida($registrar($e)), 'C1');
    igual([1, 1], $estado($e), 'C1 pacientes y eventos');

    // C2 · sin pacientes.registrar → 403 antes de mirar la idempotencia o los datos
    $e = Escenario::crear();
    igual([ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO'], $cbSalida($registrar($e, Escenario::CLINICO, 'clave-cb01-c2', ['nombres' => ''])), 'C2');
    igual([[0, 0], null], [$estado($e), $e->idempotencia->buscar(Escenario::H_ORIENTE, Escenario::CLINICO, 'clave-cb01-c2')], 'C2 sin efectos');

    // C3 · clave nueva → 201 y la clave queda guardada en la misma transacción
    $e = Escenario::crear();
    igual([Resultado::CREADO, null], $cbSalida($registrar($e, clave: 'clave-cb01-c3')), 'C3');
    afirmar($e->idempotencia->buscar(Escenario::H_ORIENTE, Escenario::ADMISION, 'clave-cb01-c3') !== null, 'C3: la clave no se guardó');

    // C4 · misma clave y misma huella → la respuesta original, sin un segundo paciente ni evento
    $repetida = $registrar($e, clave: 'clave-cb01-c3');
    igual([Resultado::CREADO, 'P-000001', true], [$repetida->estado, $repetida->datos['codigo'], $repetida->datos['repetida'] ?? false], 'C4');
    igual([1, 1], $estado($e), 'C4 pacientes y eventos');

    // C5 · misma clave, otros datos → 422 IDEMPOTENCY_KEY_REUTILIZADA
    igual([ErrorDominio::INVALIDO, 'IDEMPOTENCY_KEY_REUTILIZADA'], $cbSalida($registrar($e, clave: 'clave-cb01-c3', datos: Escenario::datosLuis())), 'C5');
    igual([1, 1], $estado($e), 'C5 pacientes y eventos');

    // C6 · la revisión de la ficha lanza ErrorDominio → 422 y no se guarda nada
    $e = Escenario::crear();
    igual([ErrorDominio::INVALIDO, 'DATOS_INVALIDOS'], $cbSalida($registrar($e, datos: Escenario::datosAna(['sexo' => 'x']))), 'C6');
    igual([0, 0], $estado($e), 'C6 sin efectos');

    // C7 · la bitácora falla DENTRO de la transacción → 503 y se deshace todo, incluida la clave
    $e = Escenario::crear();
    $e->bitacora->fallar = true;
    igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], $cbSalida($registrar($e, clave: 'clave-cb01-c7')), 'C7');
    igual([[0, 0], null], [$estado($e), $e->idempotencia->buscar(Escenario::H_ORIENTE, Escenario::ADMISION, 'clave-cb01-c7')], 'C7 rollback');
    $e->bitacora->fallar = false;
    $reintento = $registrar($e, clave: 'clave-cb01-c7');
    igual([Resultado::CREADO, false], [$reintento->estado, $reintento->datos['repetida'] ?? false], 'C7: el reintento crea la ficha (la clave no quedó a medias)');
});

/** Índice espía: cuenta las consultas de titular para saber si se tomó la rama de duplicados. */
final class IndiceEspiaCb implements IndiceDuplicados
{
    public int $consultasTitular = 0;

    public function __construct(private readonly IndiceDuplicados $real)
    {
    }

    public function titularDe(Identificador $documento): ?string
    {
        $this->consultasTitular++;

        return $this->real->titularDe($documento);
    }

    public function coincidenciasProbables(string $nombreNormalizado, \DateTimeImmutable $nacimiento): array
    {
        return $this->real->coincidenciasProbables($nombreNormalizado, $nacimiento);
    }

    public function protegidos(array $codigos): array
    {
        return $this->real->protegidos($codigos);
    }
}

prueba('CB-02', 'Ramas y ORDEN de RevisionDeFicha::paraEdicion: visibilidad → datos → protección/documento ausentes → estado y motivo → protección → documento ajeno', function (): void {
    $hoy = new DateTimeImmutable('2026-10-21');
    $almacen = new RepositorioPacientesEnMemoria();
    $espia = new IndiceEspiaCb(new IndiceDuplicadosEnMemoria($almacen));
    $revision = new RevisionDeFicha(new PoliticaEdicion(), new PoliticaDuplicados($espia), new PoliticaConfidencialidad());
    $luis = $almacen->registrar(Paciente::desdeDatos(Escenario::H_ORIENTE, new DatosPaciente('Luis', 'García', '1978-11-02', 'male', null, 'DPI', Escenario::DPI_LUIS), $hoy));
    $ana = static fn (bool $protegida = false, string $estado = Paciente::ACTIVO): Paciente => Paciente::reconstituir('P-000009', Escenario::H_ORIENTE,
        'Ana Lucía', 'López Pérez', new DateTimeImmutable('1985-03-14'), 'female', '+502 5555 1234', Dpi::desde(Escenario::DPI_ANA), $estado, 1, $protegida);
    $datos = static fn (array $c = []): DatosPaciente => new DatosPaciente(...array_merge(['nombres' => 'Ana Lucía', 'apellidos' => 'López Pérez',
        'nacimiento' => '1985-03-14', 'sexo' => 'female', 'telefono' => '+502 5555 1234', 'tipoDocumento' => 'DPI', 'numeroDocumento' => Escenario::DPI_ANA], $c));
    $todo = new Capacidades(true, true, true, true);
    $nada = Capacidades::ninguna();
    $ver = new Capacidades(verProtegidos: true);
    $resultado = static function (Paciente $actual, DatosPaciente $d, Capacidades $puede, string $motivo = '') use ($revision, $hoy, $espia): array {
        $espia->consultasTitular = 0;
        try {
            $nuevo = $revision->paraEdicion($actual, $d, $hoy, $puede, $motivo);
            return ['ok', $espia->consultasTitular, $nuevo];
        } catch (ErrorDominio $e) {
            return [$e->codigo, $espia->consultasTitular, null];
        }
    };
    $sin = static fn (array $r): array => [$r[0], $r[1]];

    // R1 · visibilidad ANTES que datos: un protegido no revela qué campos están mal
    igual(['ACCESO_DENEGADO', 0], $sin($resultado($ana(true), $datos(['sexo' => 'x']), $nada)), 'R1 protegido + datos inválidos, sin permiso');
    // R2 · datos inválidos
    igual(['DATOS_INVALIDOS', 0], $sin($resultado($ana(), $datos(['sexo' => 'x']), $nada)), 'R2');
    // R3 · protección ausente (null) = sin cambio; explícita = cambio
    igual(true, $resultado($ana(true), $datos(), $ver)[2]?->identidadProtegida, 'R3a: el campo ausente conserva la protección');
    igual(['ACCESO_DENEGADO', 0], $sin($resultado($ana(true), $datos(['identidadProtegida' => false]), $ver, 'motivo')), 'R3b: quitarla sin proteger');
    // R4 · documento ausente = sin cambio (B-4): no es cambio de identidad y NO consulta el índice
    $r4 = $resultado($ana(), $datos(['tipoDocumento' => null, 'numeroDocumento' => null, 'documentoEnviado' => false]), $nada);
    igual(['ok', 0, '*********0101'], [$r4[0], $r4[1], $r4[2]?->identificador?->enmascarado()], 'R4');
    // R5 · documento enviado como vacío = quitarlo = cambio de identidad → exige motivo
    igual(['MOTIVO_REQUERIDO', 0], $sin($resultado($ana(), $datos(['tipoDocumento' => null, 'numeroDocumento' => null]), $nada)), 'R5');
    // R6 · estado restringido se evalúa ANTES que el cambio de protección
    igual(['REQUIERE_SUPERVISOR', 0], $sin($resultado($ana(false, Paciente::FALLECIDO), $datos(['identidadProtegida' => true]), $ver, 'motivo')), 'R6');
    // R7 · cambio de protección: con permiso, sin motivo
    igual(['MOTIVO_REQUERIDO', 0], $sin($resultado($ana(), $datos(['identidadProtegida' => true]), $todo)), 'R7');
    // R8 · documento de otro paciente → 409, tras consultar el índice una vez
    igual(['IDENTIFICADOR_EN_USO', 1], $sin($resultado($ana(), $datos(['numeroDocumento' => Escenario::DPI_LUIS]), $nada, 'Corrección del DPI')), 'R8');
    // R9 · solo el teléfono: no es cambio de identidad → corto circuito, 0 consultas
    igual(['ok', 0], $sin($resultado($ana(), $datos(['telefono' => '+502 4444 0000']), $nada)), 'R9');
    // R10 · cambio de nombre con motivo → pasa, y verifica el documento (1 consulta)
    igual(['ok', 1], $sin($resultado($ana(), $datos(['nombres' => 'Ana María']), $nada, 'Corrección según DPI')), 'R10');
    igual('P-000001', $luis->codigo, 'el paciente de referencia');
});

prueba('CB-03', 'MC/DC de dos condiciones compuestas: coincideCon() por nombre «(P o no Q) y R y (S o T)» y cambiaIdentidadRespectoDe() «A o B o C»', function (): void {
    $p = static fn (bool $protegida): Paciente => Paciente::reconstituir('P-000001', Escenario::H_ORIENTE, 'Ana Lucía', 'López Pérez',
        new DateTimeImmutable('1985-03-14'), 'female', null, null, Paciente::ACTIVO, 1, $protegida);
    $c = static fn (string $nombre, ?string $nacimiento, bool $incluir): CriterioBusqueda =>
        CriterioBusqueda::desde(nombre: $nombre, nacimiento: $nacimiento)->incluyendoProtegidos($incluir);
    // P = incluir protegidos · Q = es protegido · R = el nombre contiene el texto · S = sin nacimiento · T = nacimiento coincide
    $casos = [
        // [caso, criterio (fija P, R, S y T), paciente (fija Q), esperado] — tabla en semana-15-pruebas-pacientes.md §3
        [1, $c('lopez', null, false), $p(false), true],      // base
        [2, $c('lopez', null, false), $p(true), false],      // efecto de Q (frente a 1)
        [3, $c('lopez', null, true), $p(true), true],        // efecto de P (frente a 2)
        [4, $c('perez lopez', null, false), $p(false), false], // efecto de R (frente a 1)
        [5, $c('lopez', '1985-03', false), $p(false), true],  // S falsa, T sostiene el resultado
        [6, $c('lopez', '1985-04', false), $p(false), false], // efecto de T (frente a 5) y de S (frente a 1)
    ];
    foreach ($casos as [$n, $criterio, $paciente, $esperado]) {
        igual($esperado, $criterio->coincideCon($paciente), "coincideCon caso $n");
    }

    $base = Paciente::reconstituir('P-000001', Escenario::H_ORIENTE, 'Ana', 'López', new DateTimeImmutable('1985-03-14'), 'female', null,
        Dpi::desde(Escenario::DPI_ANA), Paciente::ACTIVO, 1);
    $variante = static fn (string $nombres = 'Ana', string $nacimiento = '1985-03-14', ?string $dpi = Escenario::DPI_ANA): Paciente =>
        Paciente::reconstituir('P-000001', Escenario::H_ORIENTE, $nombres, 'López', new DateTimeImmutable($nacimiento), 'female', null,
            $dpi === null ? null : Dpi::desde($dpi), Paciente::ACTIVO, 1);
    // A = nombre normalizado distinto · B = nacimiento distinto · C = documento canónico distinto (null-safe)
    igual([false, true, true, true], [
        $variante()->cambiaIdentidadRespectoDe($base),                          // F F F
        $variante(nombres: 'Ana María')->cambiaIdentidadRespectoDe($base),      // V F F → efecto de A
        $variante(nacimiento: '1985-03-15')->cambiaIdentidadRespectoDe($base),  // F V F → efecto de B
        $variante(dpi: Escenario::DPI_NUEVO)->cambiaIdentidadRespectoDe($base), // F F V → efecto de C
    ], 'MC/DC de A o B o C');
    igual([true, false], [$variante(dpi: null)->cambiaIdentidadRespectoDe($base), $variante(dpi: null)->cambiaIdentidadRespectoDe($variante(dpi: null))],
        'C con null: quitar el documento cambia; dos provisionales no');
});

prueba('CB-04', 'Todos los puntos de salida de ActualizarPaciente::ejecutar: permiso → carga → revisión → versión → transacción', function () use ($cbSalida): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::datosAna());
    $actualizar = static fn (array $cambios = [], int $version = 1, ?string $motivo = null, string $actor = Escenario::ADMISION, string $codigo = 'P-000001'): Resultado =>
        $e->casos['actualizar']->ejecutar(new ComandoActualizar($actor, Escenario::H_ORIENTE, $codigo, $version, Escenario::datosAna($cambios), $motivo));
    $vigente = static fn (): Paciente => $e->repositorio->real->porCodigo(Escenario::H_ORIENTE, 'P-000001');

    // S1 · sin pacientes.editar → 403 SIN cargar la ficha: con porCodigo en falla, sigue siendo 403 y no 503
    $e->repositorio->fallarEn = ['porCodigo'];
    igual([ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO'], $cbSalida($actualizar(actor: Escenario::CLINICO)), 'S1');
    // S2 · la carga falla → 503
    igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], $cbSalida($actualizar()), 'S2');
    $e->repositorio->fallarEn = [];
    // S3 · no existe en el hospital activo (?? throw) → 404
    igual([ErrorDominio::NO_ENCONTRADO, 'PACIENTE_NO_ENCONTRADO'], $cbSalida($actualizar(codigo: 'P-000099')), 'S3');
    // S4 · la revisión rechaza → 422, sin escribir
    igual([ErrorDominio::INVALIDO, 'MOTIVO_REQUERIDO'], $cbSalida($actualizar(['nombres' => 'Ana María'])), 'S4');
    // S5 · versión vieja → 409 desde el repositorio, dentro de la transacción
    $conflicto = $actualizar(['telefono' => '+502 4444 0000'], version: 7);
    igual([ErrorDominio::CONFLICTO, 'VERSION_DESACTUALIZADA', 1], [$conflicto->estado, $conflicto->error, $conflicto->datos['version_vigente']], 'S5');
    // S6 · la bitácora falla → 503 y se deshace la escritura
    $e->bitacora->fallar = true;
    igual([Resultado::NO_DISPONIBLE, 'SERVICIO_NO_DISPONIBLE'], $cbSalida($actualizar(['telefono' => '+502 4444 0000'])), 'S6');
    $e->bitacora->fallar = false;
    igual([1, '+502 5555 1234', 0], [$vigente()->version, $vigente()->telefono, count($e->bitacora->eventos(EventoAuditoria::ACTUALIZADO))],
        'S4–S6 no dejaron cambios ni eventos');
    // S7 · camino feliz (minúsculas en el código) → 200, versión 2, un evento con antes y después
    $ok = $actualizar(['telefono' => '+502 4444 0000'], codigo: 'p-000001');
    igual([Resultado::OK, 2], [$ok->estado, $ok->datos['version']], 'S7');
    $evento = $e->bitacora->eventos(EventoAuditoria::ACTUALIZADO)[0];
    igual(['+502 5555 1234', '+502 4444 0000', null], [$evento->detalle['antes']['telefono'], $evento->detalle['despues']['telefono'], $evento->detalle['motivo']], 'S7 evento');
});
