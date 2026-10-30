<?php
declare(strict_types=1);

use Admision\Presentation\Api\AutenticadorTokens;
use Admision\Presentation\Api\ErrorApi;
use Admision\Presentation\UsuariosFicticios;
use Pruebas\Afirmar;
use Pruebas\Datos;

$autenticador = new AutenticadorTokens(Datos::crudos()['tokens'], new UsuariosFicticios(Datos::crudos()['usuarios']));

return [
    'El token define usuario, rol y hospital; el id de solicitud se conserva (RNF-02)' => function () use ($autenticador) {
        $u = $autenticador->contexto('Bearer tok-adm-02', 'req-0001');
        Afirmar::igual(['usr-adm-02', 'ADMISIONISTA', 'HOSP-02', 'req-0001'], [$u->usuarioId, $u->rol->value, $u->tenantId->valor, $u->solicitudId]);
    },
    'Sin Bearer, con token desconocido o mal formado: NO_AUTENTICADO' => function () use ($autenticador) {
        foreach (['', 'tok-adm-01', 'Bearer tok-otro', 'Bearer tok adm', 'Basic dXNy'] as $cabecera) {
            $e = Afirmar::lanza(ErrorApi::class, fn () => $autenticador->contexto($cabecera, 'req-0001'));
            Afirmar::igual('NO_AUTENTICADO', $e->codigo());
        }
    },
];
