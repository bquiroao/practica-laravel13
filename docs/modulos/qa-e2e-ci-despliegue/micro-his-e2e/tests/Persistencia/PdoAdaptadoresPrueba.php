<?php

declare(strict_types=1);

use E2e\Aplicacion\Puertos\AlmacenNoDisponible;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Version;
use E2e\Persistencia\Archivos\AlmacenEvidenciasEnDisco;
use E2e\Persistencia\Pdo\FabricaConexion;
use E2e\Persistencia\Pdo\PdoRegistroAuditoria;
use E2e\Persistencia\Pdo\PdoRepositorioEjecuciones;
use E2e\Persistencia\Pdo\PdoRepositorioEscenarios;
use E2e\Persistencia\Pdo\PdoRepositorioEvaluaciones;
use E2e\Persistencia\Pdo\PdoRepositorioVersiones;
use E2e\Persistencia\Pdo\PdoTransaccion;

prueba('PER-01', 'Base caída: cada adaptador PDO lanza ErrorPersistencia SIN el host ni la contraseña del mensaje del driver', function (): void {
    $pdo = new PdoQueFalla();
    $operaciones = [
        'escenarios' => static fn () => (new PdoRepositorioEscenarios($pdo))->delTenant('t'),
        'versiones' => static fn () => (new PdoRepositorioVersiones($pdo))->crear(Version::registrar('t', '1.0.0', 'abcdef1', 'sha256:' . str_repeat('a', 64), ['x1'], 'u')),
        'ejecuciones' => static fn () => (new PdoRepositorioEjecuciones($pdo))->resultadoPorClave('t', 'k'),
        'evaluaciones' => static fn () => (new PdoRepositorioEvaluaciones($pdo))->deVersion('t', '1.0.0'),
        'auditoria' => static fn () => (new PdoRegistroAuditoria($pdo))->registrar('A', 't', 'u', 'r'),
        'transaccion' => static fn () => (new PdoTransaccion($pdo))->ejecutar(static fn () => null),
    ];
    foreach ($operaciones as $nombre => $op) {
        $e = lanza(ErrorPersistencia::class, $op, $nombre);
        afirmar(!str_contains($e->getMessage(), 's3cr3t') && !str_contains($e->getMessage(), '10.0.0.5'), "$nombre filtra datos del driver");
    }
});

prueba('PER-02', 'FabricaConexion: un DSN inválido da ErrorPersistencia sin el DSN ni la clave', function (): void {
    $e = lanza(ErrorPersistencia::class, static fn () => FabricaConexion::crear('pgsql:host=127.0.0.1;port=1;dbname=nada', 'usuario', 'clave-secreta'), 'conexión');
    afirmar(!str_contains($e->getMessage(), 'clave-secreta') && !str_contains($e->getMessage(), 'port=1'), 'filtra el DSN o la clave');
});

prueba('PER-03', 'Sentencias preparadas: ningún adaptador PDO interpola variables en el SQL (solo «?»)', function (): void {
    foreach (glob(dirname(__DIR__, 2) . '/src/Persistencia/Pdo/*.php') ?: [] as $archivo) {
        $codigo = (string) file_get_contents($archivo);
        preg_match_all('/(consulta|prepare|exec|query)\(\s*("[^"]*\$[a-zA-Z_][^"]*"|\'[^\']*\'\s*\.\s*\$(?!this)\w+)/', $codigo, $m);
        igual([], $m[0], basename($archivo) . ': SQL con datos concatenados');
    }
});

prueba('PER-04', 'Almacén en disco: direccionado por contenido, idempotente y verificado; un archivo alterado deja de confirmarse', function (): void {
    $dir = sys_get_temp_dir() . '/e2e-almacen-' . uniqid('', true);       // semana 6: con el PID se reusaba la carpeta de otra corrida
    $alm = new AlmacenEvidenciasEnDisco($dir);
    $a = $alm->guardar('traza', 'traza de prueba');
    $b = $alm->guardar('traza', 'traza de prueba');
    igual([hash('sha256', 'traza de prueba'), $a->sha256, true], [$a->sha256, $b->sha256, $alm->confirmada($a->sha256)], 'guardar dos veces');
    file_put_contents($dir . '/' . substr($a->sha256, 0, 2) . '/' . $a->sha256, 'alterada');
    igual([false, false], [$alm->confirmada($a->sha256), $alm->confirmada('../../etc/passwd')], 'alterada y ruta manipulada');
    $roto = new AlmacenEvidenciasEnDisco(__FILE__);                                 // un archivo como directorio: no se puede escribir
    lanza(AlmacenNoDisponible::class, static fn () => $roto->guardar('log', 'x'), 'directorio imposible');
});

prueba('PER-05', 'Almacén en disco (semana 6): un artefacto corrupto se REPARA al volver a guardarlo con los bytes correctos; no queda bloqueado para siempre', function (): void {
    $dir = sys_get_temp_dir() . '/e2e-almacen-' . uniqid('', true);
    $alm = new AlmacenEvidenciasEnDisco($dir);
    $a = $alm->guardar('traza', 'traza original');
    file_put_contents($dir . '/' . substr($a->sha256, 0, 2) . '/' . $a->sha256, 'corrompida');
    igual(false, $alm->confirmada($a->sha256), 'corrompida');
    igual([$a->sha256, true], [$alm->guardar('traza', 'traza original')->sha256, $alm->confirmada($a->sha256)], 'reparada');
});
