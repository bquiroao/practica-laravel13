<?php

declare(strict_types=1);

use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Persistencia\Pdo\PdoRepositorioNotas;
use NotasSoap\Persistencia\Pdo\PdoTransaccion;
use NotasSoap\Persistencia\Pdo\PdoVerificadorAcceso;
use NotasSoap\Pruebas\Dobles\Escenario;
use NotasSoap\Pruebas\Dobles\PdoEspia;
use NotasSoap\Pruebas\Dobles\PdoQueFalla;
use NotasSoap\Pruebas\Dobles\RepositorioDiagnosticosQueFalla;

$notaDePrueba = static fn (): NotaSoap => NotaSoap::registrar('n-1', 'exp-carlos', 'epi-100', 'dra-mendez',
    new SeccionesSoap('S', 'O', 'A', 'P'), new DateTimeImmutable('2026-10-12T09:30:00-06:00'));

prueba('PER-01', 'Si PDO falla, el repositorio lanza ErrorPersistencia con el SQLSTATE y no deja salir PDOException', function () use ($notaDePrueba): void {
    $pdo = new PdoQueFalla('08006');
    $e = lanza(ErrorPersistencia::class, fn () => (new PdoRepositorioNotas($pdo))->guardar($notaDePrueba()), 'guardar con la base caída');
    igual('08006', $e->sqlState, 'SQLSTATE');
    afirmar($e->getPrevious() instanceof PDOException, 'conserva la causa original');
    afirmar(str_contains($pdo->sentencias[0], ':contenido'), 'la sentencia usa marcadores, no valores concatenados');
});

prueba('PER-02', 'Un fallo al verificar el acceso también se traduce a ErrorPersistencia', function (): void {
    $e = lanza(ErrorPersistencia::class, fn () => (new PdoVerificadorAcceso(new PdoQueFalla('57P01')))->puedeEscribir(new UsuarioClinico('dra-mendez', 'hosp-oriente', ['medico']), 'epi-100'), 'verificar acceso');
    igual('57P01', $e->sqlState, 'SQLSTATE');
});

prueba('PER-03', 'PdoTransaccion confirma si todo sale bien y revierte si el trabajo falla', function (): void {
    $pdo = new PdoEspia();
    igual('ok', (new PdoTransaccion($pdo))->ejecutar(fn (): string => 'ok'), 'resultado');
    igual(['BEGIN', 'COMMIT'], $pdo->llamadas, 'camino feliz');

    $pdo = new PdoEspia();
    lanza(ErrorPersistencia::class, fn () => (new PdoTransaccion($pdo))->ejecutar(fn () => throw new ErrorPersistencia('falla', '23503')), 'trabajo que falla');
    igual(['BEGIN', 'ROLLBACK'], $pdo->llamadas, 'reversión');
});

prueba('PER-04', 'Si el COMMIT falla, se revierte y se informa ErrorPersistencia', function (): void {
    $pdo = new PdoEspia();
    $pdo->fallarCommit = true;
    $e = lanza(ErrorPersistencia::class, fn () => (new PdoTransaccion($pdo))->ejecutar(fn (): int => 1), 'commit fallido');
    igual('40001', $e->sqlState, 'SQLSTATE');
    igual(['BEGIN', 'ROLLBACK'], $pdo->llamadas, 'reversión');
});

prueba('PER-05', 'Error al guardar el diagnóstico: la nota tampoco queda guardada (R-06, CA-10)', function (): void {
    $e = new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla());
    lanza(ErrorPersistencia::class, fn () => $e->registrar->ejecutar(Escenario::comando()), 'diagnóstico que falla');
    igual([], $e->notas->todas(), 'sin nota huérfana');
    igual([], $e->auditoria->eventos(), 'sin auditoría');
});

prueba('PER-06', 'Por HTTP el error de persistencia es 500 genérico, sin SQL ni SQLSTATE en la respuesta (RNF-03)', function (): void {
    $e = new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla());
    $r = $e->kernel->manejar(Escenario::peticion());
    igual(500, $r->estado, 'estado');
    igual('error_persistencia', $r->cuerpo['error']['code'], 'código');
    $texto = (string) json_encode($r->cuerpo);
    afirmar(!str_contains($texto, '08006') && !str_contains(strtolower($texto), 'insert'), 'no expone detalles internos');
});
