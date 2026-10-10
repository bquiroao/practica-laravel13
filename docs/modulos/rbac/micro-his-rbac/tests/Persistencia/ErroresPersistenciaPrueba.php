<?php

declare(strict_types=1);

// Errores de persistencia con dobles: PDO que falla, transacción espía y repositorios que fallan.

use Rbac\Aplicacion\Comandos\ComandoAutorizar;
use Rbac\Dominio\Excepciones\ErrorPersistencia;
use Rbac\Dominio\Excepciones\MembresiaDuplicada;
use Rbac\Dominio\Modelo\EventoAuditoria;
use Rbac\Dominio\Modelo\Membresia;
use Rbac\Persistencia\Archivo\RegistroAuditoriaArchivo;
use Rbac\Persistencia\Pdo\PdoCatalogoPermisos;
use Rbac\Persistencia\Pdo\PdoDirectorioUsuarios;
use Rbac\Persistencia\Pdo\PdoRegistroAuditoria;
use Rbac\Persistencia\Pdo\PdoRepositorioMembresias;
use Rbac\Persistencia\Pdo\PdoTransaccion;
use Rbac\Pruebas\Dobles\Escenario;
use Rbac\Pruebas\Dobles\PdoEspia;
use Rbac\Pruebas\Dobles\PdoQueFalla;
use Rbac\Pruebas\Dobles\RegistroAuditoriaEnMemoria;

$membresiaNueva = static fn (): Membresia => new Membresia('u1', 'h1', 'medico', 'active', new DateTimeImmutable('2026-11-15'));

prueba('PER-01', 'Adaptador PDO: una PDOException (08006) se traduce a ErrorPersistencia sin perder la causa', function (): void {
    $pdo = new PdoQueFalla('08006');
    $e = lanza(ErrorPersistencia::class, fn () => (new PdoRepositorioMembresias($pdo))->membresiasDe('ana', 'h1'), 'lectura');
    igual('08006', $e->sqlState, 'SQLSTATE');
    afirmar($e->getPrevious() instanceof PDOException, 'debe conservar la PDOException original');
    afirmar(str_contains($pdo->sentencias[0] ?? '', ':usuario') && str_contains($pdo->sentencias[0], ':hospital'), 'la consulta debe usar parámetros con nombre');
});

prueba('PER-02', 'Adaptador PDO: violación de unicidad (23505) se traduce a MembresiaDuplicada', function () use ($membresiaNueva): void {
    lanza(MembresiaDuplicada::class, fn () => (new PdoRepositorioMembresias(new PdoQueFalla('23505')))->guardar($membresiaNueva()), 'insert duplicado');
});

prueba('PER-03', 'Todos los adaptadores PDO traducen sus fallas a ErrorPersistencia', function (): void {
    $pdo = new PdoQueFalla('08006');
    $evento = new EventoAuditoria('ACCESO_DENEGADO', 'c1', 'ana', 'h1', ['motivo' => 'X'], new DateTimeImmutable());
    lanza(ErrorPersistencia::class, fn () => (new PdoCatalogoPermisos($pdo))->rolConcede('medico', 'x'), 'catálogo');
    lanza(ErrorPersistencia::class, fn () => (new PdoDirectorioUsuarios($pdo))->estadoDe('ana'), 'directorio');
    lanza(ErrorPersistencia::class, fn () => (new PdoRegistroAuditoria($pdo))->registrar($evento), 'bitácora (escritura)');
    lanza(ErrorPersistencia::class, fn () => (new PdoRegistroAuditoria($pdo))->buscarPorCorrelacion('c1'), 'bitácora (lectura)');
});

prueba('PER-04', 'PdoTransaccion: COMMIT si todo sale bien; ROLLBACK si el trabajo o el COMMIT fallan', function (): void {
    $pdo = new PdoEspia();
    (new PdoTransaccion($pdo))->ejecutar(static function (): void {
    });
    igual(['BEGIN', 'COMMIT'], $pdo->llamadas, 'éxito');

    $pdo = new PdoEspia();
    lanza(DomainException::class, fn () => (new PdoTransaccion($pdo))->ejecutar(static fn () => throw new DomainException('x')), 'trabajo falla');
    igual(['BEGIN', 'ROLLBACK'], $pdo->llamadas, 'rollback tras error del trabajo');

    $pdo = new PdoEspia();
    $pdo->fallarCommit = true;
    lanza(ErrorPersistencia::class, fn () => (new PdoTransaccion($pdo))->ejecutar(static function (): void {
    }), 'commit falla');
    igual(['BEGIN', 'ROLLBACK'], $pdo->llamadas, 'rollback tras error del commit');
});

prueba('PER-05', 'AsignarRol con escritura fallida: 500 y sin ROL_ASIGNADO', function (): void {
    $e = new Escenario();
    $e->membresias->fallarEscritura = true;
    $antes = $e->membresias->total();
    igual(500, $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'laboratorio'))->codigoHttp, 'HTTP');
    igual($antes, $e->membresias->total(), 'membresías');
    igual(0, count($e->auditoria->eventosDeTipo(EventoAuditoria::ROL_ASIGNADO)), 'eventos ROL_ASIGNADO');
});

prueba('PER-06', 'AsignarRol con bitácora caída: la transacción se revierte (500, sin membresía nueva)', function (): void {
    $e = new Escenario();
    $antes = $e->membresias->total();
    // La autorización del admin pasa; la falla ocurre al escribir ROL_ASIGNADO dentro de la transacción.
    $e->auditoria->fallar = true;
    $r = $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'nuevo-01', 'laboratorio'));
    igual(500, $r->codigoHttp, 'HTTP');
    igual($antes, $e->membresias->total(), 'membresías tras rollback');
});

prueba('PER-07', 'Carrera entre dos solicitudes: la base rechaza el duplicado y el caso de uso responde 409', function (): void {
    $e = new Escenario();
    $e->membresias->ocultarDuplicados = true;
    $r = $e->asignarRol->ejecutar(Escenario::comandoAsignar('admin-01', 'ana', 'enfermeria'));
    igual(409, $r->codigoHttp, 'HTTP');
    igual('ASIGNACION_DUPLICADA', $r->codigo, 'código');
});

prueba('PER-08', 'Autorizar con la base caída: fail-closed (403) y evento con motivo ERROR_EVALUACION', function (): void {
    $e = new Escenario();
    $e->membresias->fallarLectura = true;
    $r = $e->autorizar->ejecutar(Escenario::comandoAutorizar('marta', 'expediente.leer'));
    igual(403, $r->codigoHttp, 'HTTP');
    igual('ERROR_EVALUACION', $e->auditoria->buscarPorCorrelacion((string) $r->correlationId)?->datos['motivo'] ?? null, 'motivo');
});

prueba('PER-09', 'Bitácora principal caída: el evento va al respaldo en archivo y la operación sigue denegada', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'microhis');
    try {
        $respaldo = new RegistroAuditoriaArchivo((string) $ruta);
        $principal = new RegistroAuditoriaEnMemoria();
        $principal->fallar = true;
        $evento = new EventoAuditoria('ACCESO_DENEGADO', 'corr-x', 'ana', 'h1', ['motivo' => 'PERMISO_NO_ASIGNADO'], new DateTimeImmutable());
        lanza(ErrorPersistencia::class, fn () => $principal->registrar($evento), 'principal');
        $respaldo->registrar($evento);
        igual('PERMISO_NO_ASIGNADO', $respaldo->buscarPorCorrelacion('corr-x')?->datos['motivo'] ?? null, 'evento leído del archivo');

        $e = new Escenario();
        $e->auditoria->fallar = true;
        $r = $e->autorizar->ejecutar(Escenario::comandoAutorizar('ana', 'prescripciones.crear'));
        igual(403, $r->codigoHttp, 'HTTP con bitácora caída');
        igual(EventoAuditoria::ACCESO_DENEGADO, $e->respaldo->buscarPorCorrelacion((string) $r->correlationId)?->tipo, 'evento en respaldo');
    } finally {
        @unlink((string) $ruta);
    }

    lanza(ErrorPersistencia::class, fn () => (new RegistroAuditoriaArchivo(sys_get_temp_dir() . '/no-existe-' . bin2hex(random_bytes(4)) . '/x.jsonl'))
        ->registrar(new EventoAuditoria('ACCESO_DENEGADO', 'c', 'a', 'h', [], new DateTimeImmutable())), 'respaldo no escribible');
});

prueba('PER-10', 'Capas completas sobre PDO caído: Aplicación + adaptadores PDO reales → 403 y evento en el respaldo', function (): void {
    $pdo = new PdoQueFalla('08006');
    $respaldo = new RegistroAuditoriaEnMemoria();
    $casos = construirCasosDeUso(
        new PdoRepositorioMembresias($pdo),
        new PdoCatalogoPermisos($pdo),
        new PdoDirectorioUsuarios($pdo),
        new PdoRegistroAuditoria($pdo),
        $respaldo,
        new PdoTransaccion($pdo),
        static fn (): string => 'corr-pdo',
        static fn (): DateTimeImmutable => new DateTimeImmutable(Escenario::AHORA),
    );
    $r = $casos['autorizar']->ejecutar(new ComandoAutorizar('marta', 'hosp-oriente', 'hosp-oriente', 'expediente.leer', 'r', '10.20.5.14'));
    igual(403, $r->codigoHttp, 'HTTP');
    igual('ERROR_EVALUACION', $respaldo->buscarPorCorrelacion('corr-pdo')?->datos['motivo'] ?? null, 'evento en respaldo');
});
