<?php
// Llama 3 rutas de la API del grupo por el kernel de Laravel, sin servidor web (necesita `composer install` y las bases de .env).
// Uso desde la raíz del repo: php docs/modulos/verificacion-bd-postgresql/semana-05/evidencia/rutas-por-kernel.php
$raiz = dirname(__DIR__, 5);
require "$raiz/vendor/autoload.php";
$kernel = (require "$raiz/bootstrap/app.php")->make(Illuminate\Contracts\Http\Kernel::class);
foreach ([['POST', '/api/v1/auth/login'], ['POST', '/api/v1/auth/register'], ['GET', '/api/v1/auth/me']] as [$metodo, $ruta]) {
    $r = $kernel->handle(Illuminate\Http\Request::create($ruta, $metodo, [], [], [], ['HTTP_X_TENANT_ID' => '1', 'HTTP_ACCEPT' => 'application/json']));
    echo "$metodo $ruta -> {$r->getStatusCode()} " . (json_decode($r->getContent(), true)['message'] ?? '') . "\n";
}
