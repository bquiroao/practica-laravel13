<?php
declare(strict_types=1);

// Semana 15 · Caja blanca: cobertura de condiciones y de caminos (ver semana-15/README.md §4).

use Catalogo\Application\RegistrarNuevaVersion;
use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;
use Catalogo\Domain\Modelo\CriterioBusqueda;
use Catalogo\Domain\Modelo\Vigencia;

$v = fn (string $d, ?string $h = null) => new Vigencia(Fabrica::fecha($d), $h === null ? null : Fabrica::fecha($h));

return [
    'CB-01 Vigencia::seCruzaCon - las 4 combinaciones de "hasta" nulo y cada condición verdadera y falsa' => function () use ($v) {
        // [este, otra, esperado, qué condición se ejercita]
        $casos = [
            [$v('2026-01-01'), $v('2026-02-01'), true, 'ambas abiertas: c1 y c2 por hasta nulo'],
            [$v('2026-01-01', '2026-02-01'), $v('2026-03-01'), false, 'c2 falsa: la otra empieza después de que esta termina'],
            [$v('2026-03-01'), $v('2026-01-01', '2026-02-01'), false, 'c1 falsa: esta empieza después de que la otra termina'],
            [$v('2026-01-01', '2026-03-01'), $v('2026-02-01', '2026-04-01'), true, 'ambas cerradas que se traslapan: c1 y c2 por comparación'],
            [$v('2026-01-01', '2026-02-01'), $v('2026-02-01', '2026-03-01'), false, 'contiguas: el límite [desde, hasta)'],
        ];
        foreach ($casos as [$a, $b, $esperado, $que]) {
            Afirmar::igual($esperado, $a->seCruzaCon($b), $que);
        }
    },
    'CB-02 RegistrarNuevaVersion::ejecutar - los 7 caminos independientes' => function () {
        $repo = new CatalogoFalso();
        $repo->versiones[] = Fabrica::medicamento('MED-000102');
        $repo->versiones[] = Fabrica::medicamento('MED-000105', '2026-01-01', ['principio_activo' => 'Omeprazol', 'concentracion_valor' => '20'])->darDeBaja(Fabrica::fecha('2026-09-01'), 'Baja ficticia');
        $repo->versiones[] = Fabrica::medicamento('MED-000104', '2026-01-01', ['principio_activo' => 'Ibuprofeno', 'forma_farmaceutica' => 'TABLETA']);
        $uc = new RegistrarNuevaVersion($repo, $repo, $repo, new IdsSecuenciales(), new RelojFijo());
        $ok = Fabrica::datos(['presentacion' => 'Frasco con 30 cápsulas', 'vigente_desde' => '2026-11-01']);
        $c1 = Afirmar::lanza(ErrorValidacion::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-12', $ok, 'qf.norte'));
        Afirmar::igual(['codigo'], array_keys($c1->detalles), 'C1 código mal formado');
        Afirmar::lanza(ErrorValidacion::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000102', ['nombre' => 'x'] + $ok, 'qf.norte'));        // C2 datos inválidos
        Afirmar::lanza(ErrorNoEncontrado::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000999', $ok, 'qf.norte'));                        // C3 sin historial
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000105', Fabrica::datos(['principio_activo' => 'Omeprazol', 'concentracion_valor' => '20', 'vigente_desde' => '2026-11-01']), 'qf.norte'), 'YA_DADO_DE_BAJA'); // C4
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000102', ['vigente_desde' => '2026-01-01'] + $ok, 'qf.norte'), 'SOLAPE_VIGENCIA'); // C5
        Afirmar::lanza(ErrorReglaNegocio::class, fn () => $uc->ejecutar(Fabrica::NORTE, 'MED-000104', $ok, 'qf.norte'), 'DUPLICADO');           // C6 igual a MED-000102
        Afirmar::igual(2, $uc->ejecutar(Fabrica::NORTE, 'MED-000102', $ok, 'qf.norte')->version, 'C7 éxito');
    },
    'CB-03 CriterioBusqueda::desdeFiltros - cada decisión verdadera y falsa' => function () {
        $hoy = Fabrica::fecha(Fabrica::HOY);
        $campos = fn (array $f) => array_keys(Afirmar::lanza(ErrorValidacion::class, fn () => CriterioBusqueda::desdeFiltros($f, $hoy))->detalles);
        Afirmar::igual(['q'], $campos(['q' => 'a']), 'D1 texto de 1 carácter');
        Afirmar::igual(['q'], $campos(['q' => str_repeat('a', 61)]), 'D1 texto de 61');
        Afirmar::igual(['forma'], $campos(['forma' => 'GRAGEA']), 'D2 forma desconocida (sin pedir texto)');
        Afirmar::igual(['q'], $campos([]), 'D3 ni texto ni forma');
        Afirmar::igual(['fecha'], $campos(['q' => 'amoxi', 'fecha' => '2026-13-01']), 'D4 fecha inválida');
        $c = CriterioBusqueda::desdeFiltros(['forma' => 'TABLETA'], $hoy);
        Afirmar::igual([null, 'TABLETA', Fabrica::HOY], [$c->texto, $c->forma?->value, $c->fecha->format('Y-m-d')], 'solo forma, fecha por defecto = hoy');
        Afirmar::igual('amoxi', CriterioBusqueda::desdeFiltros(['q' => '  AMOXI '], $hoy)->texto, 'texto recortado y en minúsculas');
    },
];
