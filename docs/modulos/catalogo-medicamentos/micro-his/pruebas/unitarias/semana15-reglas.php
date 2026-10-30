<?php
declare(strict_types=1);

// Semana 15 · Pruebas unitarias U-01..U-05 de reglas del catálogo y defectos D-01 y D-03.

use Catalogo\Application\RegistrarMedicamento;
use Catalogo\Domain\Errores\ErrorValidacion;
use Catalogo\Domain\Modelo\ClaveEquivalencia;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Concentracion;
use Catalogo\Domain\Modelo\FichaMedicamento;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Modelo\UnidadConcentracion;
use Catalogo\Domain\Modelo\Vigencia;
use Catalogo\Domain\Repositorio\EscrituraCatalogo;
use Catalogo\Domain\Repositorio\RepositorioMedicamentos;
use Catalogo\Persistence\DatosEjemplo;
use Catalogo\Persistence\GeneradorUuidV4;
use Catalogo\Persistence\RepositorioMedicamentosEnMemoria;
use Catalogo\Persistence\RepositorioMedicamentosPdo;

return [
    'U-01 RN-03 vigencia: un día antes, el primer día, el último y el día hasta (límites)' => function () {
        $v = new Vigencia(Fabrica::fecha('2026-07-01'), Fabrica::fecha('2026-09-01'));
        $esperado = ['2026-06-30' => false, '2026-07-01' => true, '2026-08-31' => true, '2026-09-01' => false];
        foreach ($esperado as $fecha => $vigente) {
            Afirmar::igual($vigente, $v->vigenteEn(Fabrica::fecha($fecha)), "vigente el $fecha");
        }
    },
    'U-02 RN-04 la clave normaliza espacios internos, mayúsculas y letras acentuadas en mayúscula' => function () {
        $c = Concentracion::intentar('500', UnidadConcentracion::MG);
        $f = Catalogo\Domain\Modelo\FormaFarmaceutica::CAPSULA;
        $o = Catalogo\Domain\Modelo\ViaAdministracion::ORAL;
        Afirmar::igual(ClaveEquivalencia::de('ÁCIDO   Acetilsalicílico', $c, $f, $o)->valor, ClaveEquivalencia::de(' ácido acetilsalicílico ', $c, $f, $o)->valor, 'misma clave');
    },
    'U-03 RN-05 concentración: equivalencias "500" = "500.000" = "0500" y coma decimal' => function () {
        $valores = array_map(fn ($v) => Concentracion::intentar($v, UnidadConcentracion::MG)->valor, ['500', '500.000', '0500', '500,0']);
        Afirmar::igual(['500', '500', '500', '500'], $valores, 'normalización');
        Afirmar::igual('0.001', Concentracion::intentar('0.001', UnidadConcentracion::MG)->valor, 'mínimo positivo');
    },
    'U-04 RN-01 cerrar una versión no cambia su código, versión ni ficha' => function () {
        $m = Fabrica::medicamento('MED-000102');
        $cerrada = $m->darDeBaja(Fabrica::fecha('2026-10-15'), 'Retiro ficticio');
        Afirmar::igual([$m->id, $m->codigo->valor, $m->version], [$cerrada->id, $cerrada->codigo->valor, $cerrada->version], 'identidad');
        Afirmar::equivalente($m->ficha, $cerrada->ficha, 'ficha');
        Afirmar::igual(null, $m->vigencia->hasta, 'el original sigue intacto (inmutable)');
    },
    'U-05 RNF-06 PDO e InMemory implementan la misma interfaz Repository con los mismos métodos' => function () {
        $metodos = fn (string $c) => array_map(fn (ReflectionMethod $m) => $m->getName(), (new ReflectionClass($c))->getMethods(ReflectionMethod::IS_PUBLIC));
        Afirmar::verdadero(is_subclass_of(RepositorioMedicamentosPdo::class, RepositorioMedicamentos::class), 'PDO implementa la interfaz');
        Afirmar::verdadero(is_subclass_of(RepositorioMedicamentosEnMemoria::class, RepositorioMedicamentos::class), 'InMemory implementa la interfaz');
        $publicos = fn (string $c) => array_values(array_diff($metodos($c), ['__construct']));
        $pdo = $publicos(RepositorioMedicamentosPdo::class);
        $mem = $publicos(RepositorioMedicamentosEnMemoria::class);
        sort($pdo);
        sort($mem);
        Afirmar::igual($pdo, $mem, 'mismos métodos públicos');
    },
    '[DEFECTO D-01] RF-02 con concentración 0 y sin unidad se informan los dos errores' => function () {
        [, $e] = FichaMedicamento::validar(Fabrica::datos(['concentracion_valor' => '0', 'concentracion_unidad' => '']));
        Afirmar::verdadero(isset($e['concentracion_unidad']), 'error de unidad');
        Afirmar::verdadero(isset($e['concentracion_valor']), 'error del valor (hoy no se informa)');
    },
    '[DEFECTO D-03] RF-01 dos altas simultáneas: la segunda debe recibir el siguiente código libre' => function () {
        $repo = new RepositorioMedicamentosEnMemoria();
        DatosEjemplo::cargar($repo, new GeneradorUuidV4());
        // Escritura que, justo después de calcular el código, deja que otra persona registre ese mismo código.
        $carrera = new class ($repo) implements EscrituraCatalogo {
            public function __construct(private RepositorioMedicamentosEnMemoria $r) {}
            public function siguienteCodigo(string $h): CodigoMedicamento
            {
                $c = $this->r->siguienteCodigo($h);
                $this->r->agregar(Fabrica::medicamento($c->valor, Fabrica::HOY, ['principio_activo' => 'Cetirizina'], $h));
                return $c;
            }
            public function agregar(Medicamento $m): void { $this->r->agregar($m); }
            public function reemplazarVigente(Medicamento $a, Medicamento $n): void { $this->r->reemplazarVigente($a, $n); }
            public function cerrar(Medicamento $c): void { $this->r->cerrar($c); }
            public function eliminar(string $h, CodigoMedicamento $c): void { $this->r->eliminar($h, $c); }
        };
        $uc = new RegistrarMedicamento($repo, $carrera, new IdsSecuenciales(), new RelojFijo());
        $m = $uc->ejecutar(Fabrica::NORTE, Fabrica::datos(['principio_activo' => 'Loratadina', 'vigente_desde' => '']), 'qf.norte');
        Afirmar::igual('MED-000108', $m->codigo->valor, 'código asignado (hoy: 409 CONFLICTO_CONCURRENTE)');
    },
];
