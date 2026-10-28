<?php
declare(strict_types=1);

// Casos de uso con dobles de prueba (sin base de datos).
use Lab\Application\RegistrarPrueba;
use Lab\Application\VerDetallePrueba;
use Lab\Domain\DatosInvalidos;
use Lab\Domain\ErrorPersistencia;
use Lab\Domain\PruebaNoVigente;
use Lab\Domain\VigenciaSuperpuesta;

return [
    'T-01 Camino feliz: alta de HEM-001 crea la versión 1 (CU-01)' => function (): void {
        $repo = new RepositorioEspia();
        $p = (new RegistrarPrueba($repo, new RelojFijo()))->ejecutar(datos('hem-001'), 'HOSP-01', 'jefe.lab');
        afirmar($p->version === 1 && $repo->guardados === 1, 'Debe guardar una vez la versión 1');
        afirmar($p->requisito->contenedor === 'Tubo tapa lila (EDTA)', 'Debe conservar los requisitos de muestra');
    },
    'T-02 Regla RN-05: versión con el mismo desde se rechaza por vigencia superpuesta' => function (): void {
        $repo = new RepositorioEspia();
        $caso = new RegistrarPrueba($repo, new RelojFijo());
        $caso->ejecutar(datos('glu-001-v1'), 'HOSP-01', 'jefe.lab');
        esperarExcepcion(VigenciaSuperpuesta::class, fn () => $caso->ejecutar(datos('glu-001-v1'), 'HOSP-01', 'jefe.lab'));
        afirmar($repo->guardados === 1, 'No debe guardar la versión rechazada');
    },
    'T-03 Regla RN-05: versión que empieza después cierra la anterior el día previo' => function (): void {
        $repo = new RepositorioEspia();
        $caso = new RegistrarPrueba($repo, new RelojFijo());
        $v1 = $caso->ejecutar(datos('glu-001-v1'), 'HOSP-01', 'jefe.lab');
        $v2 = $caso->ejecutar(datos('glu-001-v2'), 'HOSP-01', 'jefe.lab');
        afirmar($v2->version === 2, 'La nueva versión debe ser la 2');
        afirmar($repo->filas[$v1->id]->vigencia->hasta?->format('Y-m-d') === '2027-01-31', 'v1 debe cerrar el 2027-01-31');
    },
    'T-04 Reglas RN-03 y RN-04: devuelve todos los errores por campo' => function (): void {
        $e = esperarExcepcion(DatosInvalidos::class, fn () => (new RegistrarPrueba(new RepositorioEspia(), new RelojFijo()))
            ->ejecutar(datos('glu-001-v1', ['volumen_minimo_ml' => 0, 'vigente_hasta' => '2027-01-01']), 'HOSP-01', 'jefe.lab'));
        afirmar(isset($e->errores['volumen_minimo_ml'], $e->errores['vigente_hasta']), 'Faltan errores por campo');
    },
    'T-05 Error de persistencia (RN-07): la falla del repositorio llega como ErrorPersistencia' => function (): void {
        esperarExcepcion(ErrorPersistencia::class, fn () => (new RegistrarPrueba(new RepositorioQueFalla(), new RelojFijo()))
            ->ejecutar(datos('hem-001'), 'HOSP-01', 'jefe.lab'));
    },
    'T-06 RN-06: detalle de una prueba antes de su vigencia responde no vigente con su vigencia (CU-05)' => function (): void {
        $repo = new RepositorioEspia();
        (new RegistrarPrueba($repo, new RelojFijo()))->ejecutar(datos('hem-001'), 'HOSP-01', 'jefe.lab');
        $e = esperarExcepcion(PruebaNoVigente::class, fn () => (new VerDetallePrueba($repo, new RelojFijo()))
            ->ejecutar('HOSP-01', 'HEM-001', '2027-01-03'));
        afirmar($e->vigencia?->desde->format('Y-m-d') === '2027-01-04', 'Debe informar la vigencia de la prueba');
    },
];
