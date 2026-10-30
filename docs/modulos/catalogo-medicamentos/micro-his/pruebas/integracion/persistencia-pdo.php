<?php
declare(strict_types=1);

// Adaptador PDO contra la base de prueba real (PostgreSQL 16, puerto 55445).

use Catalogo\Application\EliminarRegistroSinUso;
use Catalogo\Application\RegistrarMedicamento;
use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Limites;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Persistence\ConexionPdo;
use Catalogo\Persistence\RepositorioMedicamentosPdo;
use Catalogo\Persistence\UsoPrescripcionesPdo;

$codigos = fn (array $lista) => array_map(fn (Medicamento $m) => $m->codigo->valor . 'v' . $m->version, $lista);
$buscar = fn (RepositorioMedicamentosPdo $r, array $f, string $h = Fabrica::NORTE) => $r->buscarVigentes($h, CriterioBusqueda::desdeFiltros($f, Fabrica::fecha(Fabrica::HOY)));

return [
    'Camino feliz con base: el alta se guarda y se lee igual (CA-01)' => function () {
        $repo = BaseDePrueba::reiniciada();
        $uc = new RegistrarMedicamento($repo, $repo, new IdsSecuenciales(), new RelojFijo());
        $m = $uc->ejecutar(Fabrica::NORTE, Fabrica::datos(['nombre' => 'Loratadina 10 mg tableta', 'principio_activo' => 'Loratadina', 'concentracion_valor' => '10', 'forma_farmaceutica' => 'TABLETA', 'vigente_desde' => Fabrica::HOY]), 'qf.norte');
        Afirmar::igual('MED-000107', $m->codigo->valor, 'siguiente código del Norte');
        $leido = $repo->historial(Fabrica::NORTE, $m->codigo)[0];
        Afirmar::equivalente($m->ficha, $leido->ficha, 'ficha leída de la base');
        Afirmar::igual('10', $leido->ficha->concentracion->valor, 'numeric(9,3) se normaliza');
    },
    'RN-03 búsqueda: solo vigentes, sin mayúsculas y por fecha (CA-06, CA-07)' => function () use ($codigos, $buscar) {
        $repo = BaseDePrueba::reiniciada();
        Afirmar::igual(['MED-000103v1', 'MED-000102v1'], $codigos($buscar($repo, ['q' => 'AMOXI'])), 'orden por nombre (C)');
        Afirmar::igual([], $codigos($buscar($repo, ['q' => 'omepra'])), 'dado de baja');
        Afirmar::igual(['MED-000104v1'], $codigos($buscar($repo, ['q' => 'ibupro', 'fecha' => '2026-03-10'])), 'versión 1 en marzo');
        Afirmar::igual(['MED-000104v2'], $codigos($buscar($repo, ['q' => 'ibupro', 'fecha' => '2026-07-01'])), 'versión 2 desde julio');
        Afirmar::igual(['MED-000104v2', 'MED-000101v1'], $codigos($buscar($repo, ['forma' => 'TABLETA'])), 'por forma, orden por nombre');
        Afirmar::igual(['MED-000103v1'], $codigos($buscar($repo, ['q' => 'med-000103'])), 'por código');
    },
    'RN-03 % y _ se buscan como texto literal (CA-08)' => function () use ($codigos, $buscar) {
        $repo = BaseDePrueba::reiniciada();
        // Como comodín, "% s" también traería la suspensión (MED-000103) y "a_o" traería "Amoxicilina".
        Afirmar::igual(['MED-000106v1'], $codigos($buscar($repo, ['q' => '% s'])), '"% s" literal');
        Afirmar::igual([], $codigos($buscar($repo, ['q' => 'a_o'])), '"a_o" no es comodín');
        Afirmar::igual([], $codigos($buscar($repo, ['q' => '!%'])), 'el carácter de escape también es literal');
    },
    'RNF-02 el otro hospital no ve ni cruza datos' => function () use ($codigos, $buscar) {
        $repo = BaseDePrueba::reiniciada();
        Afirmar::igual(['MED-000101v1'], $codigos($buscar($repo, ['q' => 'parace'], Fabrica::SUR)), 'el Sur solo ve su paracetamol');
        Afirmar::igual('Caja con 50 tabletas', $buscar($repo, ['q' => 'parace'], Fabrica::SUR)[0]->ficha->presentacion, 'es la ficha del Sur');
        Afirmar::igual(null, $repo->vigentePorCodigo(Fabrica::SUR, CodigoMedicamento::desdeTexto('MED-000102'), Fabrica::fecha(Fabrica::HOY)), 'MED-000102 no existe en el Sur');
    },
    'RNF-04 la base rechaza un solape de versiones con 409, no con 500' => function () {
        $repo = BaseDePrueba::reiniciada();
        $intruso = Fabrica::medicamento('MED-000102', '2026-05-01', ['principio_activo' => 'Cefalexina']);
        $directo = new Medicamento($intruso->id, Fabrica::NORTE, $intruso->codigo, 2, $intruso->ficha, $intruso->vigencia, 'qf.norte');
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $repo->agregar($directo), 'SOLAPE_VIGENCIA');
    },
    'RNF-04 la base rechaza un duplicado vigente con 409 (segunda línea de RN-04)' => function () {
        $repo = BaseDePrueba::reiniciada();
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $repo->agregar(Fabrica::medicamento('MED-000150', '2026-06-01')), 'DUPLICADO');
    },
    'RN-01 reemplazarVigente es atómico: si falla la nueva, la anterior sigue abierta' => function () {
        $repo = BaseDePrueba::reiniciada();
        $v1 = $repo->historial(Fabrica::NORTE, CodigoMedicamento::desdeTexto('MED-000103'))[0];
        [$cerrada, $nueva] = $v1->nuevaVersion(Fabrica::uuid(), Fabrica::ficha(), Fabrica::fecha('2026-11-01'), 'qf.norte'); // igual a MED-000102
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $repo->reemplazarVigente($cerrada, $nueva), 'DUPLICADO');
        Afirmar::igual(null, $repo->historial(Fabrica::NORTE, $v1->codigo)[0]->vigencia->hasta, 'la versión 1 sigue abierta');
        Afirmar::igual(1, count($repo->historial(Fabrica::NORTE, $v1->codigo)), 'no quedó versión 2');
    },
    'RN-02 con base: no elimina MED-000101 usado en PRE-000231 (CA-11)' => function () {
        $repo = BaseDePrueba::reiniciada();
        $uc = new EliminarRegistroSinUso($repo, new UsoPrescripcionesPdo(BaseDePrueba::pdo()), $repo);
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000101'), 'EN_USO');
        $uc->ejecutar(Fabrica::SUR, 'MED-000101'); // en el Sur nunca se usó
        Afirmar::igual(1, count($repo->historial(Fabrica::NORTE, CodigoMedicamento::desdeTexto('MED-000101'))), 'el del Norte sigue');
    },
    'RN-05 las columnas de la base coinciden con Limites del dominio' => function () {
        $esperado = ['codigo' => Limites::CODIGO, 'nombre' => Limites::NOMBRE_MAX, 'principio_activo' => Limites::PRINCIPIO_MAX,
            'concentracion_unidad' => Limites::UNIDAD_MAX, 'forma_farmaceutica' => Limites::FORMA_MAX, 'via_administracion' => Limites::VIA_MAX,
            'presentacion' => Limites::PRESENTACION_MAX, 'registro_sanitario' => Limites::REGISTRO_SANITARIO_MAX,
            'motivo_cierre' => Limites::MOTIVO_MAX, 'creado_por' => Limites::USUARIO_MAX, 'clave_equivalencia' => Limites::CLAVE_MAX];
        $st = BaseDePrueba::pdo()->prepare("SELECT column_name, character_maximum_length, numeric_precision, numeric_scale
            FROM information_schema.columns WHERE table_name = :t");
        $st->execute(['t' => 'medicamentos']);
        $columnas = array_column($st->fetchAll(), null, 'column_name');
        foreach ($esperado as $columna => $largo) {
            Afirmar::igual($largo, $columnas[$columna]['character_maximum_length'], "largo de $columna");
        }
        Afirmar::igual(Limites::CONCENTRACION_ENTEROS + Limites::CONCENTRACION_DECIMALES, $columnas['concentracion_valor']['numeric_precision'], 'precisión');
        Afirmar::igual(Limites::CONCENTRACION_DECIMALES, $columnas['concentracion_valor']['numeric_scale'], 'escala');
    },
    'RN-05 el valor máximo del dominio cabe en la base (límite superior)' => function () {
        $repo = BaseDePrueba::reiniciada();
        $max = Fabrica::medicamento('MED-000190', '2099-12-31', ['nombre' => str_repeat('ñ', 150), 'principio_activo' => str_repeat('á', 120),
            'concentracion_valor' => '999999.999', 'presentacion' => str_repeat('x', 120), 'registro_sanitario' => str_repeat('A', 30)]);
        $repo->agregar($max);
        Afirmar::equivalente($max->ficha, $repo->historial(Fabrica::NORTE, $max->codigo)[0]->ficha, 'se guarda sin recortes');
    },
    'Error de persistencia con base caída: ErrorPersistencia (CA-13)' => function () {
        $config = require __DIR__ . '/../../config.php';
        $caida = ['puerto' => 1] + $config['bd'];
        Afirmar::lanza(ErrorPersistencia::class, fn () => ConexionPdo::crear($caida, $config['bd']['base_prueba']), 'PERSISTENCIA');
    },
];
