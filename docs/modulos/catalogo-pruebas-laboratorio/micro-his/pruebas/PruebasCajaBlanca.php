<?php
declare(strict_types=1);

// Semana 15 — Caja blanca: cada rama de RegistrarPrueba::ejecutar y de Vigencia::errores.
use Lab\Application\RegistrarPrueba;
use Lab\Domain\DatosInvalidos;
use Lab\Domain\Vigencia;
use Lab\Domain\VigenciaSuperpuesta;

$alta = static function (RepositorioEspia $repo, string $archivo, array $cambios = []) {
    return (new RegistrarPrueba($repo, new RelojFijo()))->ejecutar(datos($archivo, $cambios), 'HOSP-01', 'jefe.lab');
};

return [
    'CB-01 RegistrarPrueba, rama sin versión previa: versión 1 y sin cierre' => function () use ($alta): void {
        $repo = new RepositorioEspia();
        afirmar($alta($repo, 'hem-001')->version === 1 && count($repo->filas) === 1, 'Debe existir solo la versión 1');
    },
    'CB-02 RegistrarPrueba, rama con versión previa que NO se superpone: versión 2 y la anterior intacta' => function () use ($alta): void {
        $repo = new RepositorioEspia();
        $v1 = $alta($repo, 'glu-001-v1', ['vigente_hasta' => '2027-01-10']);
        afirmar($alta($repo, 'glu-001-v2')->version === 2, 'Debe crear la versión 2');
        afirmar($repo->filas[$v1->id]->vigencia->hasta?->format('Y-m-d') === '2027-01-10', 'v1 no debe cambiar');
    },
    'CB-03 RegistrarPrueba, rama superpuesta con desde ≤ vigente: excepción sin guardar' => function () use ($alta): void {
        $repo = new RepositorioEspia();
        $alta($repo, 'glu-001-v1');
        esperarExcepcion(VigenciaSuperpuesta::class, fn () => $alta($repo, 'glu-001-v1', ['vigente_desde' => '2027-01-03']));
        afirmar($repo->guardados === 1, 'Solo debe haberse guardado la versión 1');
    },
    'CB-04 RegistrarPrueba, rama superpuesta con desde posterior: cierre el día previo' => function () use ($alta): void {
        $repo = new RepositorioEspia();
        $v1 = $alta($repo, 'glu-001-v1');
        $alta($repo, 'glu-001-v2');
        afirmar($repo->filas[$v1->id]->vigencia->hasta?->format('Y-m-d') === '2027-01-31', 'v1 debe cerrar el 2027-01-31');
    },
    'CB-05 Vigencia::errores, las cuatro condiciones de fecha' => function (): void {
        $hoy = new DateTimeImmutable('2027-01-02');
        $casos = [
            'desde vacía' => [['vigente_desde' => ''], 'vigente_desde'],
            'desde anterior a hoy' => [['vigente_desde' => '2027-01-01'], 'vigente_desde'],
            'hasta con formato inválido' => [['vigente_desde' => '2027-01-04', 'vigente_hasta' => '31/01/2027'], 'vigente_hasta'],
            'hasta anterior a desde' => [['vigente_desde' => '2027-01-04', 'vigente_hasta' => '2027-01-03'], 'vigente_hasta'],
        ];
        foreach ($casos as $nombre => [$datos, $campo]) {
            afirmar(array_keys(Vigencia::errores($datos, $hoy)) === [$campo], "Caso «$nombre» debe marcar solo $campo");
        }
        afirmar(Vigencia::errores(['vigente_desde' => '2027-01-02', 'vigente_hasta' => '2027-01-02'], $hoy) === [], 'Desde = hasta = hoy es válido');
    },
];
