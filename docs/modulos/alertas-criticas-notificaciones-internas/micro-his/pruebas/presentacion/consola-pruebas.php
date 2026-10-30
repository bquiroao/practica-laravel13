<?php
/** Presentación por consola con dobles (sin base): salida y códigos de retorno. @var Pruebas $t */
declare(strict_types=1);

require_once __DIR__ . '/../dobles/RepositorioDoble.php';
require_once __DIR__ . '/../../bootstrap.php';

use AlertasCriticas\Persistence\{NotificadorSimulado, RelojFijo};
use AlertasCriticas\Presentation\ConsolaAlertas;

$consola = fn () => new ConsolaAlertas(contenedorAlertas([
    'reloj' => new RelojFijo('2026-10-29 10:00:00'),
    'repositorio' => new RepositorioDoble(),
    'notificador' => new NotificadorSimulado(),
]));
$correr = function (ConsolaAlertas $c, array $args): array {
    ob_start();
    $codigo = $c->ejecutar($args);
    return [$codigo, ob_get_clean()];
};
$registro = ['registrar', '--tenant=HOSP-GUA-01', '--paciente=PAC-00451', '--servicio=MED-INT',
    '--origen=ingreso-resultados-laboratorio', '--id-origen=LAB-77801', '--prioridad=CRITICA',
    '--descripcion=Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)'];

$t->prueba('Consola registrar muestra la alerta ENVIADA y devuelve 0', function () use ($t, $consola, $correr, $registro) {
    [$codigo, $salida] = $correr($consola(), $registro);
    $t->igual(0, $codigo);
    $t->verdadero(str_contains($salida, 'Alerta registrada:') && str_contains($salida, 'PAC-00451 · CRITICA · nivel 0 · ENVIADA'), $salida);
});

$t->prueba('Consola traduce una regla incumplida a código 2 con el ID de la regla', function () use ($t, $consola, $correr, $registro) {
    [$codigo, $salida] = $correr($consola(), array_merge($registro, ['--paciente=Juan']));
    $t->igual(2, $codigo);
    $t->verdadero(str_contains($salida, 'Regla incumplida [RN-01]'), $salida);
});

$t->prueba('Consola: alerta de otro tenant da código 3', function () use ($t, $consola, $correr) {
    [$codigo] = $correr($consola(), ['acusar', '--id=00000000-0000-4000-8000-000000000000', '--usuario=enf.turno21', '--tenant=HOSP-XEL-02']);
    $t->igual(3, $codigo);
});

$t->prueba('Consola: comando desconocido muestra ayuda y devuelve 1', function () use ($t, $consola, $correr) {
    [$codigo, $salida] = $correr($consola(), ['borrar-todo']);
    $t->igual([1, true], [$codigo, str_contains($salida, 'Uso: php consola.php')]);
});
