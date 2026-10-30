<?php
declare(strict_types=1);

// Semana 7 — Comprueba el refactor «la política de alertas sale de la persistencia».
//   ANTES = el código real del Micro-HIS (RegistrarToma + TomasPdo).  DESPUÉS = ejemplo-refactor/despues/.
//   CAR: el comportamiento se conserva (misma respuesta y mismas filas en 4 escenarios).  CAM: el cambio solicitado entra sin tocar el resto.  ACO: el acoplamiento medido.
// Uso:  php docs/modulos/signos-vitales-alertas/semana-07/verificar-refactor.php
use SignosVitales\Aplicacion\{RegistrarToma, Usuario};
use SignosVitales\Dominio\{EvaluadorUmbrales, Medicion, RangoSigno, Toma};
use SignosVitales\Persistencia\TomasPdo;
use SignosVitales\Refactor\{PoliticaEnviaAnormales, PoliticaEstandar, RegistrarTomaDespues, RepositorioTomasDespues};

$micro = dirname(__DIR__) . '/micro-his-signos-vitales';
require "$micro/bootstrap.php";
require __DIR__ . '/ejemplo-refactor/despues/Alerta.php';
require __DIR__ . '/ejemplo-refactor/despues/RegistrarTomaDespues.php';
$fallas = 0;
function ver(string $id, bool $ok, string $que): void { $GLOBALS['fallas'] += $ok ? 0 : 1; printf("%-7s %-5s %s\n", $id, $ok ? 'PASA' : 'FALLA', $que); }

$base = function () use ($micro): PDO { $p = new PDO('sqlite::memory:'); $p->exec((string) file_get_contents("$micro/sql/esquema.sql")); return $p; };
$reloj = fn () => new DateTimeImmutable('2026-10-25 08:00'); $id = fn () => 'toma-1'; $ana = new Usuario('enf-ana', 'enfermeria', 'hosp-1');
$alertas = fn (PDO $p) => $p->query('SELECT tipo, nivel, valor, estado_envio FROM alertas_signos_vitales ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$antes = function (array $entrada) use ($base, $reloj, $id, $ana, $alertas) { $p = $base(); $r = (new RegistrarToma(new TomasPdo($p), new TomasPdo($p), $reloj, $id))->ejecutar($entrada, $ana); return [$r, $alertas($p)]; };
$despues = function (array $entrada, $politica = null) use ($base, $reloj, $id, $ana, $alertas) {
    $p = $base(); $pdo = new TomasPdo($p);
    $r = (new RegistrarTomaDespues(new RepositorioTomasDespues($p), $pdo, $politica ?? new PoliticaEstandar(), $reloj, $id, EvaluadorUmbrales::estandar()))->ejecutar($entrada, $ana);
    return [$r, $alertas($p)];
};
$e = fn (array $m, string $p = 'pac-adulto', string $ep = 'epi-1') => ['paciente_id' => $p, 'episodio_id' => $ep, 'mediciones' => $m];

// Caracterización: mismo resultado antes y después
$escenarios = ['crítica (FC 135)' => $e(['fc' => 135, 'spo2' => 97, 'temp' => 36.8]), 'tres anormales' => $e(['pas' => 150, 'pad' => 95, 'temp' => 38.0]),
               'niño sin rango' => $e(['fc' => 150], 'pac-nino', 'epi-2'), 'SpO2 crítica y FC anormal' => $e(['spo2' => 85, 'fc' => 110])];
$esperadas = [1, 3, 0, 2]; $i = 0;
foreach ($escenarios as $nombre => $entrada) {
    $a = $antes($entrada); $d = $despues($entrada);
    ver(sprintf('CAR-%02d', ++$i), $a === $d && count($a[1]) === $esperadas[$i - 1], "Escenario «{$nombre}»: misma respuesta y mismas filas de alertas ({$esperadas[$i - 1]} alertas)");
}
$mapa = array_map(fn ($f) => $f['estado_envio'], $antes($escenarios['tres anormales'])[1]);
ver('CAR-05', $mapa === ['no_aplica', 'no_aplica', 'no_aplica'] && array_column($antes($escenarios['SpO2 crítica y FC anormal'])[1], 'estado_envio', 'tipo') === ['spo2' => 'pendiente_envio', 'fc' => 'no_aplica'], 'La regla de hoy se conserva: las críticas quedan pendiente_envio y las anormales no_aplica');

// Cambio solicitado: M21 también recibe las anormales
[, $filas] = $despues($escenarios['tres anormales'], new PoliticaEnviaAnormales());
ver('CAM-01', count($filas) === 3 && array_unique(array_column($filas, 'estado_envio')) === ['pendiente_envio'], 'Cambio solicitado «M21 recibe también las anormales»: se resuelve con una clase nueva de 3 líneas, sin editar RegistrarToma, el repositorio ni el dominio');

// Acoplamiento
$pdoSrc = (string) file_get_contents("$micro/src/Persistencia/TomasPdo.php"); $repoSrc = (string) file_get_contents(__DIR__ . '/ejemplo-refactor/despues/RegistrarTomaDespues.php');
$repoSrc = substr($repoSrc, 0, (int) strpos($repoSrc, 'final class RegistrarTomaDespues'));   // solo la clase del repositorio
ver('ACO-01', str_contains($pdoSrc, "Clasificacion::Critica->value ? 'pendiente_envio'") && !str_contains($repoSrc, 'Critica') && !str_contains($repoSrc, 'pendiente_envio'),
    'La regla de envío a M21 estaba escrita dentro de TomasPdo (capa de persistencia) y el repositorio nuevo no la conoce');
$params = fn (string $clase) => array_map(fn ($p) => (string) $p->getType(), (new ReflectionClass($clase))->getConstructor()->getParameters());
ver('ACO-02', !array_filter($params(RegistrarToma::class), fn ($t) => str_contains($t, 'Politica')) && count(array_filter($params(RegistrarTomaDespues::class), fn ($t) => str_contains($t, 'PoliticaDeAlertas'))) === 1,
    'Antes el caso de uso no tiene por dónde recibir otra política; después la recibe por constructor');
$mediciones = [Medicion::desde('fc', 135), Medicion::desde('temp', 38.0)];
$rangos = ['fc' => new RangoSigno(60, 100, 40, 130), 'temp' => new RangoSigno(36.0, 37.5, 35.0, 39.9)];
$toma = Toma::registrar('t', 'p', 'e', 'a', new DateTimeImmutable('2026-10-25'), $mediciones, $rangos, EvaluadorUmbrales::estandar());
$p = (new PoliticaEstandar())->alertasDe($toma);
ver('ACO-03', array_map(fn ($a) => [$a->signo, $a->nivel->value, $a->envio->value], $p) === [['fc', 'critica', 'pendiente_envio'], ['temp', 'anormal', 'no_aplica']], 'La política se prueba con objetos en memoria, sin PDO ni base de datos');

echo $fallas === 0 ? "\nResultado: el refactor conserva el comportamiento y baja el acoplamiento medido.\n" : "\nResultado: $fallas verificaciones fallaron.\n";
exit($fallas ? 1 : 0);
