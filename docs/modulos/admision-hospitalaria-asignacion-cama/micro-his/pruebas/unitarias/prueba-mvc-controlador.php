<?php
declare(strict_types=1);

// Controlador MVC con el almacén InMemory: flujo web completo sin base de datos.

use Admision\Presentation\UsuariosFicticios;
use Admision\Presentation\Web\AdmisionController;
use Admision\Presentation\Web\Peticion;
use Admision\Presentation\Web\Vista;
use Pruebas\Afirmar;
use Pruebas\Datos;
use Pruebas\Dobles\GeneradorIdSecuencial;

$nuevoControlador = fn () => new AdmisionController(
    (require __DIR__ . '/../../bootstrap.php')(null, 'memoria'),
    new UsuariosFicticios(Datos::crudos()['usuarios']),
    new GeneradorIdSecuencial(),
    new Vista(__DIR__ . '/../../src/Presentation/Web/vistas'),
);
$campos = fn (string $paciente, string $cama, string $usuario = 'usr-adm-01') => [
    'usuario' => $usuario, 'paciente' => $paciente, 'servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL',
    'aislamiento' => '0', 'origen' => 'EMERGENCIA', 'cama' => $cama,
];

return [
    'Web: GET /camas lista B-201 y B-202, no B-203 (CA-05)' => function () use ($nuevoControlador) {
        $r = $nuevoControlador()->camas(new Peticion('GET', '/camas', ['usuario' => 'usr-adm-01', 'paciente' => 'PAC-00451', 'servicio' => 'MEDICINA_INTERNA', 'tipo' => 'GENERAL']));
        Afirmar::igual(200, $r->estado);
        Afirmar::verdadero(str_contains($r->cuerpo, 'B-201') && str_contains($r->cuerpo, 'B-202') && !str_contains($r->cuerpo, 'B-203'), 'lista esperada');
    },
    'Web: POST /admisiones responde 201 y la cama asignada (CA-01)' => function () use ($nuevoControlador, $campos) {
        $r = $nuevoControlador()->registrar(new Peticion('POST', '/admisiones', [], $campos('PAC-00451', Datos::B201)));
        Afirmar::igual(201, $r->estado);
        Afirmar::verdadero(str_contains($r->cuerpo, 'Admisión ACTIVA') && str_contains($r->cuerpo, 'B-201'), 'resultado visible');
    },
    'Web: cama tomada responde 409 y vuelve a la lista actualizada (CA-07, CU-05)' => function () use ($nuevoControlador, $campos) {
        $c = $nuevoControlador();
        $c->registrar(new Peticion('POST', '/admisiones', [], $campos('PAC-00451', Datos::B201)));
        $r = $c->registrar(new Peticion('POST', '/admisiones', [], $campos('PAC-00452', Datos::B201)));
        Afirmar::igual(409, $r->estado);
        Afirmar::verdadero(str_contains($r->cuerpo, 'Elija otra cama') && str_contains($r->cuerpo, Datos::B202), 'ofrece B-202');
        Afirmar::verdadero(!str_contains($r->cuerpo, 'value="' . Datos::B201 . '"'), 'B-201 ya no se ofrece');
    },
    'Web: MEDICO recibe 403 y un código inválido 422 con la salida escapada (CA-08, CA-02)' => function () use ($nuevoControlador, $campos) {
        Afirmar::igual(403, $nuevoControlador()->registrar(new Peticion('POST', '/admisiones', [], $campos('PAC-00451', Datos::B201, 'usr-med-01')))->estado);
        $r = $nuevoControlador()->registrar(new Peticion('POST', '/admisiones', [], $campos('<b>PAC</b>', Datos::B201)));
        Afirmar::igual(422, $r->estado);
        Afirmar::verdadero(str_contains($r->cuerpo, '&lt;b&gt;PAC&lt;/b&gt;') && !str_contains($r->cuerpo, '<b>PAC'), 'lo capturado se conserva escapado');
    },
];
