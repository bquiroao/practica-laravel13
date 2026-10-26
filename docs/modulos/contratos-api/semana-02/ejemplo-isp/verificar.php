<?php
declare(strict_types=1);

// Evidencia ISP: (1) los flujos cumplen los criterios CA-01..CA-07; (2) la consulta da el mismo
// resultado con el repositorio y con una caché que solo implementa la interfaz de lectura;
// (3) por reflexión, cada servicio depende solo de los métodos que usa.
require __DIR__ . '/antes.php';
require __DIR__ . '/despues.php';

$fallos = 0;
function verificar(bool $ok, string $texto): void { global $fallos; $fallos += $ok ? 0 : 1; echo ($ok ? 'OK    ' : 'FALLA ') . $texto . "\n"; }

$repo = new RepositorioDocumentos();
$pub = new ServicioPublicacion($repo);
$rev = new ServicioRevision($repo);
verificar($pub->enviar('guia-citas', '1.0.0', ['medico'], 'ana')['status'] === 201, 'CA-01 enviar 1.0.0 con audiencia = 201 (en revisión)');
verificar($pub->enviar('guia-citas', 'uno', ['medico'], 'ana')['status'] === 422, 'CA-02 versión sin formato x.y.z = 422');
verificar($pub->enviar('guia-citas', '1.0.1', [], 'ana')['status'] === 422, 'CA-02 sin audiencia = 422');
verificar($rev->aprobar(1, 'ana')['status'] === 403, 'CA-04 el autor aprueba lo propio = 403 (E5)');
verificar($rev->aprobar(1, 'luis')['status'] === 200, 'CA-04 otro revisor aprueba = 200');
verificar($pub->enviar('guia-citas', '1.0.0', ['medico'], 'ana')['status'] === 409, 'CA-03 repetir la versión vigente = 409 (E3)');
verificar($pub->enviar('guia-citas', '1.1.0', ['medico'], 'ana')['status'] === 201, 'CA-01 enviar 1.1.0 = 201');
$rev->aprobar(2, 'luis');
verificar($rev->marcarObsoleta(1)['status'] === 200, 'CA-07 marcar obsoleta la 1.0.0 = 200 (no se borra)');

foreach (['repositorio' => $repo, 'caché de solo lectura' => new CacheLectura($repo)] as $nombre => $lector) {
    $c = new ServicioConsulta($lector);
    verificar($c->consultar('guia-citas', null, ['medico']) === ['status' => 200, 'version' => '1.1.0', 'aviso_obsoleta' => false], "[$nombre] CA-05 médico ve la vigente 1.1.0");
    verificar($c->consultar('guia-citas', '1.0.0', ['medico'])['aviso_obsoleta'] === true, "[$nombre] CA-07 la 1.0.0 se ve con aviso de obsoleta");
    verificar($c->consultar('guia-citas', null, ['enfermeria'])['status'] === 404, "[$nombre] CA-06 rol fuera de la audiencia = 404");
    verificar($c->consultar('no-existe', null, ['medico'])['status'] === 404, "[$nombre] CA-06 documento inexistente = 404");
}

$metodos = fn (string $clase) => array_map(fn ($p) => count((new ReflectionClass($p->getType()->getName()))->getMethods()), (new ReflectionMethod($clase, '__construct'))->getParameters())[0];
foreach (['ServicioConsulta' => 1, 'ServicioPublicacion' => 2, 'ServicioRevision' => 4, 'ServicioConsultaAntes' => 7] as $clase => $esperado) {
    verificar($metodos($clase) === $esperado, "$clase depende de una interfaz de $esperado método(s)");
}
verificar(class_implements(CacheLectura::class) === ['LectorDocumentos' => 'LectorDocumentos'], 'CacheLectura implementa solo LectorDocumentos');
$inutiles = 0;
$cacheAntes = (new ReflectionClass(CacheLecturaAntes::class))->newInstanceWithoutConstructor();   // esos 6 métodos no usan el origen
foreach ([fn () => $cacheAntes->vigente('x'), fn () => $cacheAntes->guardarEnRevision('x', '1.0.0', [], 'a'), fn () => $cacheAntes->enRevision(1),
          fn () => $cacheAntes->publicar(1), fn () => $cacheAntes->rechazar(1, 'x'), fn () => $cacheAntes->marcarObsoleta(1)] as $llamada) {
    try { $llamada(); } catch (LogicException) { $inutiles++; }
}
verificar($inutiles === 6, "CacheLecturaAntes tiene $inutiles métodos obligados que solo lanzan «no soportado»");

echo $fallos === 0 ? "RESULTADO: todo correcto\n" : "RESULTADO: $fallos fallos\n";
exit($fallos === 0 ? 0 : 1);
