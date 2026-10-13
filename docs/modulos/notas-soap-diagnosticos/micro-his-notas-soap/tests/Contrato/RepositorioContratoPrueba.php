<?php

declare(strict_types=1);

/*
 * Pruebas de contrato del patrón Repository: el MISMO conjunto de pruebas corre contra los dos
 * adaptadores (en memoria y PDO sobre SQLite). Si un adaptador se comportara distinto, el caso de
 * uso no podría cambiarlo por el otro sin sorpresas (principio de sustitución de Liskov).
 * Nota: SQLite informa toda violación de integridad como 23000 y PostgreSQL como 23505/23503;
 * por eso el contrato exige la CLASE 23, no el código exacto.
 */

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Persistencia\EnMemoria\RepositorioDiagnosticosEnMemoria;
use NotasSoap\Persistencia\EnMemoria\RegistroIdempotenciaEnMemoria;
use NotasSoap\Persistencia\EnMemoria\RepositorioNotasEnMemoria;
use NotasSoap\Persistencia\Pdo\PdoRegistroIdempotencia;
use NotasSoap\Persistencia\Pdo\FabricaConexion;
use NotasSoap\Persistencia\Pdo\PdoRepositorioDiagnosticos;
use NotasSoap\Persistencia\Pdo\PdoRepositorioNotas;

/** @return array<string, callable(): array{0: RepositorioNotas, 1: RepositorioDiagnosticos}> */
$adaptadores = [
    'memoria' => static function (): array {
        $notas = new RepositorioNotasEnMemoria();

        return [$notas, new RepositorioDiagnosticosEnMemoria($notas), new RegistroIdempotenciaEnMemoria()];
    },
    'pdo-sqlite' => static function (): array {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            omitir('la extensión pdo_sqlite no está habilitada');
        }
        $pdo = FabricaConexion::crear('sqlite::memory:', '', '');
        $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/Integracion/esquema-sqlite.sql'));
        $pdo->exec((string) file_get_contents(dirname(__DIR__, 2) . '/database/datos-demo.sql'));

        return [new PdoRepositorioNotas($pdo), new PdoRepositorioDiagnosticos($pdo), new PdoRegistroIdempotencia($pdo)];
    },
];

$nota = static fn (string $id = 'n-1', string $exp = 'exp-carlos', string $cuando = '2026-10-12T09:30:00-06:00'): NotaSoap => NotaSoap::registrar($id, $exp, 'epi-100', 'dra-mendez',
    new SeccionesSoap('Tos y fiebre', 'T 38.6 °C', 'Probable neumonía', 'Radiografía'), new DateTimeImmutable($cuando));

foreach ($adaptadores as $nombre => $crear) {
    prueba("REP-01 $nombre", 'guardar y buscar devuelven la misma nota (secciones, estado, versión, autor)', function () use ($crear, $nota): void {
        [$notas] = $crear();
        $notas->guardar($nota());
        $n = $notas->buscar('n-1');
        afirmar($n !== null, 'la nota debe existir');
        igual(['Probable neumonía', 'draft', 1, 'dra-mendez', 'exp-carlos'], [$n->secciones->analisis, $n->estado, $n->version, $n->autorId, $n->expedienteId], 'nota leída');
    });

    prueba("REP-02 $nombre", 'buscar una nota que no existe devuelve null', function () use ($crear): void {
        [$notas] = $crear();
        igual(null, $notas->buscar('no-existe'), 'resultado');
    });

    prueba("REP-03 $nombre", 'guardar dos veces el mismo id es un error de persistencia de integridad (SQLSTATE clase 23)', function () use ($crear, $nota): void {
        [$notas] = $crear();
        $notas->guardar($nota());
        $e = lanza(ErrorPersistencia::class, fn () => $notas->guardar($nota()), 'id duplicado');
        afirmar(str_starts_with((string) $e->sqlState, '23'), 'SQLSTATE de integridad, obtenido ' . var_export($e->sqlState, true));
    });

    prueba("REP-04 $nombre", 'registrarFirma guarda la firma; sobre una nota ya firmada lanza NotaFirmada (R-07)', function () use ($crear, $nota): void {
        [$notas] = $crear();
        $notas->guardar($nota());
        $firmada = $nota()->firmar(new DateTimeImmutable('2026-10-12T10:00:00-06:00'));
        $notas->registrarFirma($firmada);
        $leida = $notas->buscar('n-1');
        igual(['signed', '2026-10-12T10:00:00-06:00'], [$leida?->estado, $leida?->firmadaEn?->format(DATE_ATOM)], 'firma guardada');
        lanza(NotaFirmada::class, fn () => $notas->registrarFirma($firmada), 'segunda firma');
    });

    prueba("REP-05 $nombre", 'deNota devuelve solo los diagnósticos de esa nota, ordenados por id', function () use ($crear, $nota): void {
        [$notas, $dx] = $crear();
        $notas->guardar($nota('n-1'));
        $notas->guardar($nota('n-2'));
        $dx->guardar(Diagnostico::registrar('d-2', $nota('n-1'), 'R50.9', 'Fiebre', 'secondary'));
        $dx->guardar(Diagnostico::registrar('d-1', $nota('n-1'), 'J18.9', 'Neumonía', 'primary'));
        $dx->guardar(Diagnostico::registrar('d-3', $nota('n-2'), 'J20.9', 'Bronquitis', 'differential'));
        igual(['d-1', 'd-2'], array_map(static fn (Diagnostico $d): string => $d->id, $dx->deNota('n-1')), 'diagnósticos de n-1');
        igual(['J18.9', 'primary'], [$dx->deNota('n-1')[0]->codigo, $dx->deNota('n-1')[0]->tipo->value], 'datos del primero');
    });

    prueba("REP-06 $nombre", 'un diagnóstico de una nota inexistente es un error de persistencia de integridad (SQLSTATE clase 23)', function () use ($crear, $nota): void {
        [, $dx] = $crear();
        $e = lanza(ErrorPersistencia::class, fn () => $dx->guardar(Diagnostico::registrar('d-1', $nota('fantasma'), 'J18.9', 'Neumonía', 'primary')), 'llave foránea');
        afirmar(str_starts_with((string) $e->sqlState, '23'), 'SQLSTATE de integridad, obtenido ' . var_export($e->sqlState, true));
    });

    prueba("REP-07 $nombre", 'deExpediente devuelve solo las notas de ese expediente, en orden de creación y luego por id', function () use ($crear, $nota): void {
        [$notas] = $crear();
        $notas->guardar($nota('n-3', 'exp-carlos', '2026-10-12T11:00:00-06:00'));
        $notas->guardar($nota('n-2', 'exp-carlos', '2026-10-12T09:30:00-06:00'));
        $notas->guardar($nota('n-1', 'exp-carlos', '2026-10-12T09:30:00-06:00'));
        $notas->guardar($nota('n-9', 'exp-rosa', '2026-10-12T08:00:00-06:00'));
        igual(['n-1', 'n-2', 'n-3'], array_map(static fn (NotaSoap $n): string => $n->id, $notas->deExpediente('exp-carlos')), 'orden');
        igual([], $notas->deExpediente('exp-nadie'), 'sin notas');
    });

    prueba("REP-08 $nombre", 'RegistroIdempotencia: buscar una clave nueva da null; guardada devuelve huella y nota; repetida es error de integridad', function () use ($crear, $nota): void {
        [$notas, , $idem] = $crear();
        $notas->guardar($nota());
        igual(null, $idem->buscar('hosp-oriente', 'dra-mendez', 'clave-rep-0001'), 'clave nueva');
        $huella = str_repeat('a', 64);
        $idem->guardar('hosp-oriente', 'dra-mendez', 'clave-rep-0001', $huella, 'n-1');
        igual(['huella' => $huella, 'nota_id' => 'n-1'], $idem->buscar('hosp-oriente', 'dra-mendez', 'clave-rep-0001'), 'guardada');
        igual(null, $idem->buscar('hosp-oriente', 'dr-lopez', 'clave-rep-0001'), 'es por usuario');
        $e = lanza(ErrorPersistencia::class, fn () => $idem->guardar('hosp-oriente', 'dra-mendez', 'clave-rep-0001', $huella, 'n-1'), 'duplicada');
        afirmar(str_starts_with((string) $e->sqlState, '23'), 'SQLSTATE de integridad');
    });
}
