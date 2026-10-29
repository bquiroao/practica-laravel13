<?php
declare(strict_types=1);

// Semana 4 (parte B): entrada MVC sin servidor, llamando al Router como lo hace public/index.php.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/base.php';

$web = fn () => armarWeb(armarCasosDeUso(baseDePrueba()));
$penicilina = ['alergeno' => 'Penicilina', 'tipo' => 'allergy', 'severidad' => 'life_threatening', 'reaccion' => '<script>alert(1)</script>'];

return [
    'T-19 GET de la alerta responde 200 y muestra el nivel con texto, no solo con color' => function () use ($web) {
        $r = $web()->despachar('GET', '/pacientes/PAC-00451/alerta');
        Arnes::igual(200, $r->estado, 'estado');
        Arnes::afirmar(str_contains($r->cuerpo, '[MODERADA]'), 'falta el texto del nivel');
    },
    'T-20 la vista escapa lo que escribió el usuario (sin <script> en la página)' => function () use ($web, $penicilina) {
        $router = $web();
        Arnes::igual(303, $router->despachar('POST', '/pacientes/PAC-00451/alergias', $penicilina)->estado, 'registro');
        $cuerpo = $router->despachar('GET', '/pacientes/PAC-00451/alerta')->cuerpo;
        Arnes::afirmar(!str_contains($cuerpo, '<script>') && str_contains($cuerpo, '&lt;script&gt;'), 'la reacción no se escapó');
    },
    'T-21 error de dominio en el formulario: 422, mensaje con código y lo escrito se conserva' => function () use ($web) {
        $r = $web()->despachar('POST', '/pacientes/PAC-00451/alergias', ['alergeno' => 'Látex', 'severidad' => 'severe', 'inicio' => '2099-01-01']);
        Arnes::igual(422, $r->estado, 'estado');
        Arnes::afirmar(str_contains($r->cuerpo, 'FECHA_INICIO_FUTURA') && str_contains($r->cuerpo, 'value="Látex"'), 'falta mensaje o valor');
    },
    'T-22 controladores y formulario sin SQL ni reglas de negocio (revisión del código fuente)' => function () {
        foreach (glob(__DIR__ . '/../src/Presentacion/Web/{Controlador*,Formulario*}.php', GLOB_BRACE) as $archivo) {
            $codigo = file_get_contents($archivo);
            Arnes::afirmar(!preg_match('/\bPDO\b|SELECT |INSERT |UPDATE |Severidad::|NivelAlerta::|->peso\(/', $codigo), basename($archivo) . ' tiene SQL o reglas');
        }
    },
    'T-23 POST de reconocimiento redirige (303) y la alerta muestra que ya fue reconocida' => function () use ($web, $penicilina) {
        $router = $web();
        $router->despachar('POST', '/pacientes/PAC-00451/alergias', $penicilina);
        preg_match('/name="huella" value="([0-9a-f]+)"/', $router->despachar('GET', '/pacientes/PAC-00451/alerta')->cuerpo, $m);
        Arnes::igual(303, $router->despachar('POST', '/pacientes/PAC-00451/alerta/reconocimiento', ['huella' => $m[1] ?? ''])->estado, 'estado');
        Arnes::afirmar(str_contains($router->despachar('GET', '/pacientes/PAC-00451/alerta')->cuerpo, 'Usted ya reconoció'), 'no quedó reconocida');
    },
    'T-24 paciente inexistente → 404 con código; ruta desconocida → 404' => function () use ($web) {
        $router = $web();
        $r = $router->despachar('GET', '/pacientes/PAC-99999/alerta');
        Arnes::afirmar($r->estado === 404 && str_contains($r->cuerpo, 'PACIENTE_NO_ENCONTRADO'), 'paciente');
        Arnes::igual(404, $router->despachar('GET', '/no-existe')->estado, 'ruta');
    },
];
