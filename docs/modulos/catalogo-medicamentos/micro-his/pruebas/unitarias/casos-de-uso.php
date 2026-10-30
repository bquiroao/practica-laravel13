<?php
declare(strict_types=1);

// Casos de uso con dobles: camino feliz, reglas de dominio y error de persistencia.

use Catalogo\Application\BuscarMedicamentosVigentes;
use Catalogo\Application\DarDeBajaMedicamento;
use Catalogo\Application\EliminarRegistroSinUso;
use Catalogo\Application\RegistrarMedicamento;
use Catalogo\Application\RegistrarNuevaVersion;
use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;

$conDatos = function (): CatalogoFalso {
    $repo = new CatalogoFalso();
    $repo->versiones[] = Fabrica::medicamento('MED-000102');
    $repo->versiones[] = Fabrica::medicamento('MED-000105', '2026-01-01', ['nombre' => 'Omeprazol 20 mg cápsula', 'principio_activo' => 'Omeprazol', 'concentracion_valor' => '20'])
        ->darDeBaja(Fabrica::fecha('2026-09-01'), 'Sustituido (dato ficticio)');
    return $repo;
};
$registrar = fn ($repo) => new RegistrarMedicamento($repo, $repo, new IdsSecuenciales(), new RelojFijo());

return [
    'CU-01 camino feliz: asigna el siguiente código y versión 1 (CA-01)' => function () use ($conDatos, $registrar) {
        $repo = $conDatos();
        $m = $registrar($repo)->ejecutar(Fabrica::NORTE, Fabrica::datos(['nombre' => 'Loratadina 10 mg tableta', 'principio_activo' => 'Loratadina', 'concentracion_valor' => '10', 'forma_farmaceutica' => 'TABLETA', 'vigente_desde' => '']), 'qf.norte');
        Afirmar::igual('MED-000106', $m->codigo->valor, 'código (el doble tiene hasta MED-000105)');
        Afirmar::igual(1, $m->version, 'versión');
        Afirmar::igual(Fabrica::HOY, $m->vigencia->desde->format('Y-m-d'), 'sin fecha, empieza hoy');
        Afirmar::igual(['equivalenteVigente', 'agregar'], $repo->llamadas, 'llamadas al repositorio');
    },
    'CU-01 datos inválidos: 422 sin tocar el repositorio (CA-02)' => function () use ($conDatos, $registrar) {
        $repo = $conDatos();
        $e = Afirmar::lanza(ErrorValidacion::class, fn () => $registrar($repo)->ejecutar(Fabrica::NORTE, Fabrica::datos(['nombre' => str_repeat('x', 151), 'vigente_desde' => '15/10/2026']), 'qf.norte'));
        Afirmar::igual(['nombre', 'vigente_desde'], array_keys($e->detalles), 'errores de ficha y fecha juntos');
        Afirmar::igual([], $repo->llamadas, 'llamadas');
    },
    'RN-04 rechaza el duplicado vigente e informa el código existente (CA-04)' => function () use ($conDatos, $registrar) {
        $e = Afirmar::lanza(ErrorReglaNegocio::class, fn () => $registrar($conDatos())->ejecutar(Fabrica::NORTE, Fabrica::datos(['principio_activo' => 'amoxicilina ', 'vigente_desde' => Fabrica::HOY]), 'qf.norte'), 'DUPLICADO');
        Afirmar::igual('MED-000102', $e->detalles['codigo_existente'], 'código existente');
    },
    'RN-04 acepta la misma clave si la otra ya terminó su vigencia (CA-05)' => function () use ($conDatos, $registrar) {
        $datos = Fabrica::datos(['nombre' => 'Omeprazol 20 mg cápsula', 'principio_activo' => 'Omeprazol', 'concentracion_valor' => '20', 'vigente_desde' => Fabrica::HOY]);
        Afirmar::igual(1, $registrar($conDatos())->ejecutar(Fabrica::NORTE, $datos, 'qf.norte')->version, 'se registra');
        $antes = $datos + [];
        $antes['vigente_desde'] = '2026-05-01';
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $registrar($conDatos())->ejecutar(Fabrica::NORTE, $antes, 'qf.norte'), 'DUPLICADO');
    },
    'RN-04 la clave es por hospital: el Sur puede tener la misma (RNF-02)' => function () use ($conDatos, $registrar) {
        Afirmar::igual('MED-000001', $registrar($conDatos())->ejecutar(Fabrica::SUR, Fabrica::datos(), 'qf.sur')->codigo->valor, 'primer código del Sur');
    },
    'CU-03 nueva versión: reemplaza en una sola operación y conserva el código (CA-09)' => function () use ($conDatos) {
        $repo = $conDatos();
        $uc = new RegistrarNuevaVersion($repo, $repo, $repo, new IdsSecuenciales(), new RelojFijo());
        $v2 = $uc->ejecutar(Fabrica::NORTE, 'MED-000102', Fabrica::datos(['presentacion' => 'Frasco con 30 cápsulas', 'vigente_desde' => '2026-11-01']), 'qf.norte');
        Afirmar::igual(2, $v2->version, 'versión');
        Afirmar::verdadero(in_array('reemplazarVigente', $repo->llamadas, true), 'usa la operación atómica');
        $vigentesEnNov = array_filter($repo->historial(Fabrica::NORTE, $v2->codigo), fn ($m) => $m->vigenteEn(Fabrica::fecha('2026-11-01')));
        Afirmar::igual(1, count($vigentesEnNov), 'una sola versión vigente el 2026-11-01');
    },
    'CU-03 nueva versión equivalente a OTRO código vigente es DUPLICADO' => function () use ($conDatos) {
        $repo = $conDatos();
        $repo->versiones[] = Fabrica::medicamento('MED-000104', '2026-01-01', ['nombre' => 'Ibuprofeno 400 mg tableta', 'principio_activo' => 'Ibuprofeno', 'concentracion_valor' => '400', 'forma_farmaceutica' => 'TABLETA']);
        $uc = new RegistrarNuevaVersion($repo, $repo, $repo, new IdsSecuenciales(), new RelojFijo());
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000104', Fabrica::datos(['vigente_desde' => '2026-11-01']), 'qf.norte'), 'DUPLICADO');
    },
    'CU-04 la baja saca al medicamento de la búsqueda de hoy pero no del pasado (CA-06)' => function () use ($conDatos) {
        $repo = $conDatos();
        $buscar = new BuscarMedicamentosVigentes($repo, new RelojFijo());
        Afirmar::igual(0, count($buscar->ejecutar(Fabrica::NORTE, ['q' => 'omepra'])), 'hoy no aparece');
        Afirmar::igual(1, count($buscar->ejecutar(Fabrica::NORTE, ['q' => 'omepra', 'fecha' => '2026-08-31'])), 'el 2026-08-31 sí');
        (new DarDeBajaMedicamento($repo, $repo, new RelojFijo()))->ejecutar(Fabrica::NORTE, 'MED-000102', '', 'Retiro del formulario (ficticio)');
        Afirmar::igual(0, count($buscar->ejecutar(Fabrica::NORTE, ['q' => 'amoxi'])), 'dado de baja hoy');
        Afirmar::igual(1, count($repo->historial(Fabrica::NORTE, Catalogo\Domain\Modelo\CodigoMedicamento::desdeTexto('MED-000102'))), 'no se borró');
    },
    'RN-02 no elimina un código usado en prescripciones (CA-11)' => function () use ($conDatos) {
        $repo = $conDatos();
        $uc = new EliminarRegistroSinUso($repo, new UsoFalso(['MED-000102']), $repo);
        $e = Afirmar::lanza(ErrorReglaNegocio::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000102'), 'EN_USO');
        Afirmar::igual('baja', $e->detalles['accion_sugerida'], 'sugiere baja');
        Afirmar::verdadero(!in_array('eliminar', $repo->llamadas, true), 'no llamó a eliminar');
        $uc->ejecutar(Fabrica::NORTE, 'MED-000105');
        Afirmar::lanza(ErrorNoEncontrado::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000105'));
    },
    'Error de persistencia: se propaga como ErrorPersistencia sin datos parciales (CA-13)' => function () use ($conDatos) {
        $lectura = $conDatos();
        $uc = new RegistrarMedicamento($lectura, new EscrituraQueFalla(), new IdsSecuenciales(), new RelojFijo());
        $e = Afirmar::lanza(ErrorPersistencia::class, fn () => $uc->ejecutar(Fabrica::NORTE, Fabrica::datos(['principio_activo' => 'Cefalexina']), 'qf.norte'), 'PERSISTENCIA');
        Afirmar::verdadero(!str_contains($e->getMessage(), 'conexión'), 'el mensaje no muestra el detalle técnico');
        Afirmar::igual(2, count($lectura->versiones), 'no quedó nada guardado');
    },
];
