<?php

declare(strict_types=1);

use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Prescripciones\Persistencia\Pdo\FabricaConexion;
use Prescripciones\Persistencia\Pdo\PdoCatalogoMedicamentos;
use Prescripciones\Persistencia\Pdo\PdoFuenteAlergias;
use Prescripciones\Persistencia\Pdo\PdoPacientes;
use Prescripciones\Persistencia\Pdo\PdoRegistroAuditoria;
use Prescripciones\Persistencia\Pdo\PdoRepositorioPrescripciones;
use Prescripciones\Persistencia\Pdo\PdoTransaccion;

prueba('PER-01', 'R-07: con la base caída, PdoFuenteAlergias lanza FuenteAlergiasNoDisponible (nunca vacío)', function (): void {
    $e = lanza(FuenteAlergiasNoDisponible::class, static fn () => (new PdoFuenteAlergias(new PdoQueFalla()))->alergiasDe('P-001', 'hosp-norte'), 'fuente');
    afirmar(!str_contains($e->getMessage(), PdoQueFalla::SECRETO), 'el mensaje no debe llevar datos de conexión');
});

prueba('PER-02', 'Cada adaptador PDO traduce PDOException a ErrorPersistencia sin filtrar datos de conexión', function (): void {
    $pdo = new PdoQueFalla();
    $casos = [
        'repositorio.porClave' => static fn () => (new PdoRepositorioPrescripciones($pdo))->porClave('hosp-norte', 'k'),
        'repositorio.porId' => static fn () => (new PdoRepositorioPrescripciones($pdo))->porId('hosp-norte', 1),
        'catalogo.buscar' => static fn () => (new PdoCatalogoMedicamentos($pdo))->buscar('PARA500'),
        'catalogo.grupo' => static fn () => (new PdoCatalogoMedicamentos($pdo))->grupoDelAlergeno(new Alergia('X', null, 'allergy', 'mild', 'active')),
        'pacientes' => static fn () => (new PdoPacientes($pdo))->existeEnHospital('P-001', 'hosp-norte'),
        'auditoria' => static fn () => (new PdoRegistroAuditoria($pdo))->registrar('PRESCRIPCION_EMITIDA', 'h', 'u', 'r', 'exito'),
        'transaccion' => static fn () => (new PdoTransaccion($pdo))->ejecutar(static fn () => 1),
        'conexion' => static fn () => FabricaConexion::crear('pgsql:host=127.0.0.1;port=1;dbname=x;connect_timeout=2', 'u', 'clave-secreta'),
    ];
    foreach ($casos as $nombre => $cuerpo) {
        $ex = lanza(ErrorPersistencia::class, $cuerpo, $nombre);
        afirmar(!str_contains($ex->getMessage(), 'password') && !str_contains($ex->getMessage(), 'clave-secreta'), "$nombre filtra datos");
    }
});

prueba('PER-03', 'Error de persistencia de punta a punta: emitir con PDO caído → 503 sin excepción', function (): void {
    $config = require dirname(__DIR__, 2) . '/config/config.php';
    $casos = construirCasosDeUso(\Prescripciones\Persistencia\FabricaAdaptadores::pdo(new PdoQueFalla(), $config));
    $r = $casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]));
    igual([Resultado::NO_DISPONIBLE, 'ERROR_PERSISTENCIA'], [$r->tipo, $r->codigo], 'resultado');
});

prueba('PER-04', 'Sentencias preparadas: ningún adaptador concatena valores en el SQL ni usa query()/exec() con datos', function (): void {
    $hallazgos = [];
    foreach (glob(dirname(__DIR__, 2) . '/src/Persistencia/Pdo/*.php') as $archivo) {
        $codigo = (string) file_get_contents($archivo);
        if (preg_match('/->query\(/', $codigo)) {
            $hallazgos[] = basename($archivo) . ': usa query()';
        }
        if (preg_match('/->exec\([^)]*\$/', $codigo)) {
            $hallazgos[] = basename($archivo) . ': usa exec() con una variable';
        }
        // Un valor interpolado o concatenado dentro de un literal SQL: "… $x …" o '…' . $x . '…' junto a palabras SQL.
        if (preg_match('/(SELECT|INSERT|WHERE|VALUES)[^;]*?(\{\$|"\s*\.\s*\$|\'\s*\.\s*\$)/s', $codigo)) {
            $hallazgos[] = basename($archivo) . ': concatena un valor en el SQL';
        }
    }
    igual([], $hallazgos, 'hallazgos');
});

prueba('PER-05', 'La transacción revierte y relanza si el trabajo falla (sin base: doble de PDO en memoria)', function (): void {
    $pdo = new class extends \PDO {
        public array $llamadas = [];
        private bool $abierta = false;

        public function __construct()
        {
        }

        public function beginTransaction(): bool
        {
            $this->llamadas[] = 'BEGIN';

            return $this->abierta = true;
        }

        public function commit(): bool
        {
            $this->llamadas[] = 'COMMIT';
            $this->abierta = false;

            return true;
        }

        public function rollBack(): bool
        {
            $this->llamadas[] = 'ROLLBACK';
            $this->abierta = false;

            return true;
        }

        public function inTransaction(): bool
        {
            return $this->abierta;
        }
    };
    $t = new PdoTransaccion($pdo);
    igual(7, $t->ejecutar(static fn () => 7), 'resultado');
    lanza(ErrorPersistencia::class, static fn () => $t->ejecutar(static fn () => throw new \PDOException('x')), 'PDOException → ErrorPersistencia');
    lanza(\DomainException::class, static fn () => $t->ejecutar(static fn () => throw new \DomainException('y')), 'otra excepción se relanza igual');
    igual(['BEGIN', 'COMMIT', 'BEGIN', 'ROLLBACK', 'BEGIN', 'ROLLBACK'], $pdo->llamadas, 'secuencia');
});

prueba('PER-06', 'La política estática decide por «usuario|hospital» (stand-in de RBAC)', function (): void {
    $p = new PoliticaAccesoEstatica(['dr-lopez|hosp-norte' => ['prescripcion.emitir']]);
    igual([true, false, false], [$p->permite('dr-lopez', 'prescripcion.emitir', 'hosp-norte'), $p->permite('dr-lopez', 'prescripcion.emitir', 'hosp-sur'),
        $p->permite('dr-lopez', 'prescripcion.override', 'hosp-norte')], 'decisiones');
});
