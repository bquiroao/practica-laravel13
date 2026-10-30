<?php

declare(strict_types=1);

use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\FabricaEvidencias;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Persistencia\Archivos\AlmacenEvidenciasArchivos;
use Qa\Persistencia\Pdo\FabricaConexion;
use Qa\Persistencia\Pdo\PdoRegistroAuditoria;
use Qa\Persistencia\Pdo\PdoRepositorioCampanas;
use Qa\Persistencia\Pdo\PdoRepositorioCasos;
use Qa\Persistencia\Pdo\PdoRepositorioDefectos;
use Qa\Persistencia\Pdo\PdoTransaccion;

prueba('PER-01', 'Base caída: cada adaptador PDO lanza ErrorPersistencia SIN el host ni la contraseña del mensaje del driver', function (): void {
    $pdo = new PdoQueFalla();
    $llamadas = [
        'casos.porId' => static fn () => (new PdoRepositorioCasos($pdo))->porId('hosp-norte', 'TC-101'),
        'casos.criticosDe' => static fn () => (new PdoRepositorioCasos($pdo))->criticosDe('hosp-norte', ['expediente']),
        'campanas.porId' => static fn () => (new PdoRepositorioCampanas($pdo))->porId('hosp-norte', 1),
        'campanas.intentoPorClave' => static fn () => (new PdoRepositorioCampanas($pdo))->intentoPorClave('hosp-norte', 'clave-0001'),
        'defectos.criticosAbiertos' => static fn () => (new PdoRepositorioDefectos($pdo))->criticosAbiertos('hosp-norte', ['TC-107']),
        'auditoria.registrar' => static fn () => (new PdoRegistroAuditoria($pdo))->registrar('X', 'hosp-norte', 'u', 'r'),
        'transaccion' => static fn () => (new PdoTransaccion($pdo))->ejecutar(static fn () => 1),
    ];
    foreach ($llamadas as $que => $llamada) {
        $e = lanza(ErrorPersistencia::class, $llamada, $que);
        afirmar(!str_contains($e->getMessage(), 's3cr3t') && !str_contains($e->getMessage(), '10.0.0.5'), "$que filtra el mensaje del driver");
    }
});

prueba('PER-02', 'Listas vacías no van a la base (ni un IN () inválido): críticos, varios casos y críticos abiertos', function (): void {
    $pdo = new PdoQueFalla();   // si consultara, lanzaría
    igual([[], [], 0], [(new PdoRepositorioCasos($pdo))->criticosDe('hosp-norte', []), (new PdoRepositorioCasos($pdo))->variosPorId('hosp-norte', []),
        (new PdoRepositorioDefectos($pdo))->criticosAbiertos('hosp-norte', [])], 'sin consultas');
});

prueba('PER-03', 'Transacción: si el COMMIT falla se hace rollback y sale ErrorPersistencia; si el trabajo lanza, se propaga tal cual', function (): void {
    $pdo = new PdoQueFallaAlConfirmar();
    lanza(ErrorPersistencia::class, static fn () => (new PdoTransaccion($pdo))->ejecutar(static fn () => 1), 'commit');
    igual(true, $pdo->revertida, 'rollback');
    $pdo2 = new PdoQueFallaAlConfirmar();
    lanza(\DomainException::class, static fn () => (new PdoTransaccion($pdo2))->ejecutar(static fn () => throw new \DomainException('regla')), 'regla');
    igual(true, $pdo2->revertida, 'rollback por excepción del trabajo');
});

prueba('PER-04', 'FabricaConexion: DSN inválido → ErrorPersistencia sin el DSN ni la clave', function (): void {
    $e = lanza(ErrorPersistencia::class, static fn () => FabricaConexion::crear('pgsql:host=127.0.0.1;port=1;dbname=nada;connect_timeout=1', 'postgres', 'clave-secreta'), 'conexión');
    afirmar(!str_contains($e->getMessage(), 'clave-secreta') && !str_contains($e->getMessage(), 'port=1'), 'el mensaje no lleva el DSN ni la clave');
});

prueba('PER-05', 'Sentencias preparadas: ningún adaptador PDO concatena datos en el SQL (solo «?» y nombres de columna)', function (): void {
    $violaciones = [];
    foreach (glob(dirname(__DIR__, 2) . '/src/Persistencia/Pdo/*.php') as $archivo) {
        $codigo = (string) file_get_contents($archivo);
        // Un literal SQL seguido de «. $variable» o con «{$...}» interpolado sería una inyección posible.
        if (preg_match('/(SELECT|INSERT|UPDATE|DELETE)[^;]*?[\'"]\s*\.\s*\$(?!this->|enlaces)/s', $codigo) || preg_match('/(SELECT|INSERT|UPDATE|DELETE)[^"]*\{\$/', $codigo)) {
            $violaciones[] = basename($archivo);
        }
        if (str_contains($codigo, '->query(') || preg_match('/->exec\(\s*[\'"](SELECT|INSERT|UPDATE|DELETE)/', $codigo)) {
            $violaciones[] = basename($archivo) . ' (query/exec)';
        }
    }
    igual([], $violaciones, 'archivos');
});

prueba('PER-06', 'Almacén en disco: direccionado por contenido, idempotente, verifica lo escrito y rechaza rutas manipuladas', function (): void {
    $raiz = sys_get_temp_dir() . '/qa-almacen-' . bin2hex(random_bytes(4));
    $almacen = new AlmacenEvidenciasArchivos($raiz);
    $ev = FabricaEvidencias::crear('captura', Escenario::PNG, Clasificacion::GENERAL);
    $u1 = $almacen->guardar('hosp-norte', $ev);
    $u2 = $almacen->guardar('hosp-norte', $ev);
    igual(['hosp-norte/' . $ev->huella(), $u1, Escenario::PNG], [$u1, $u2, $almacen->leer($u1)], 'guardar y leer');
    lanza(AlmacenNoDisponible::class, static fn () => $almacen->leer('../../etc/passwd'), 'ruta manipulada');
    lanza(AlmacenNoDisponible::class, static fn () => $almacen->guardar('../x', $ev), 'tenant manipulado');
    $sinPermiso = new AlmacenEvidenciasArchivos($raiz . '/' . $u1 . '/no-es-carpeta');   // la «raíz» queda debajo de un archivo
    lanza(AlmacenNoDisponible::class, static fn () => $sinPermiso->guardar('hosp-norte', $ev), 'almacén que no puede escribir');
    @unlink($raiz . '/' . $u1);
    @rmdir($raiz . '/hosp-norte');
    @rmdir($raiz);
});

prueba('PER-07', 'Los adaptadores implementan los puertos del dominio y de la aplicación (sustituibles por los dobles)', function (): void {
    igual(true, is_subclass_of(PdoRepositorioCasos::class, \Qa\Dominio\Puertos\RepositorioCasos::class), 'casos');
    igual(true, is_subclass_of(PdoRepositorioCampanas::class, \Qa\Dominio\Puertos\RepositorioCampanas::class), 'campañas');
    igual(true, is_subclass_of(PdoRepositorioDefectos::class, \Qa\Dominio\Puertos\RepositorioDefectos::class), 'defectos');
    igual(true, is_subclass_of(PdoRegistroAuditoria::class, \Qa\Aplicacion\Puertos\RegistroAuditoria::class), 'auditoría');
    igual(false, (new \ReflectionClass(CasoPrueba::class))->getParentClass(), 'el modelo no hereda de nada de infraestructura');
});
