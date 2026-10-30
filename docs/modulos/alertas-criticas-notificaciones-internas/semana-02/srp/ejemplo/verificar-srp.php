<?php
// Semana 2 - Verificador del ejemplo SRP (estructural + comportamiento).
// Uso desde la raíz del repo:
//   php docs/modulos/alertas-criticas-notificaciones-internas/semana-02/srp/ejemplo/verificar-srp.php
declare(strict_types=1);

use AlertasSrp\Despues\{Alerta, DespachadorNotificaciones, NotificadorSimulado, PoliticaEscalamiento, RelojFijo};

require __DIR__ . '/despues.php';

$salida = [];
$fallas = 0;
$linea = function (string $t) use (&$salida): void { $salida[] = $t; echo $t, PHP_EOL; };
$comprobar = function (string $id, bool $ok) use (&$fallas, $linea): void {
    $linea(($ok ? '[OK]    ' : '[FALLA] ') . $id);
    if (!$ok) { $fallas++; }
};

/** Dependencias externas que usa cada clase, leídas con el tokenizador de PHP. */
function dependenciasPorClase(string $archivo): array
{
    $externas = ['PDO', 'mail', 'time', 'sleep'];
    $tokens = token_get_all(file_get_contents($archivo));
    $clase = null; $resultado = [];
    foreach ($tokens as $i => $t) {
        if (!is_array($t)) { continue; }
        if (in_array($t[0], [T_CLASS, T_INTERFACE], true)) {
            for ($j = $i + 1; is_array($tokens[$j]) && $tokens[$j][0] !== T_STRING; $j++);
            $clase = $tokens[$j][1];
            $resultado[$clase] = [];
        } elseif ($clase && $t[0] === T_STRING && in_array($t[1], $externas, true)) {
            $resultado[$clase][$t[1]] = true;
        } elseif ($clase && $t[0] === T_NAME_FULLY_QUALIFIED && in_array(ltrim($t[1], '\\'), $externas, true)) {
            $resultado[$clase][ltrim($t[1], '\\')] = true;
        } elseif ($clase && $t[0] === T_CONSTANT_ENCAPSED_STRING && $t[1] === "'now'") {
            $resultado[$clase]['reloj real'] = true;
        }
    }
    return array_map('array_keys', $resultado);
}

$linea('=== Verificación SRP — Alertas críticas y notificaciones internas ===');
$linea('PHP ' . PHP_VERSION);
$linea('');
$linea('--- Estructural: dependencias externas por clase ---');
foreach (['antes.php', 'despues.php'] as $archivo) {
    foreach (dependenciasPorClase(__DIR__ . "/$archivo") as $clase => $deps) {
        $linea(sprintf('%-12s %-28s %d  %s', $archivo, $clase, count($deps), implode(', ', $deps)));
    }
}
$antes = dependenciasPorClase(__DIR__ . '/antes.php');
$despues = dependenciasPorClase(__DIR__ . '/despues.php');
$comprobar('ANTES: GestorAlertas concentra 4 dependencias externas', count($antes['GestorAlertas']) === 4);
foreach (['Alerta', 'PoliticaEscalamiento', 'DespachadorNotificaciones'] as $regla) {
    $comprobar("DESPUÉS: $regla no usa dependencias externas", $despues[$regla] === []);
}
$comprobar('DESPUÉS: ninguna clase tiene más de 1 dependencia externa', max(array_map('count', $despues)) <= 1);

$linea('');
$linea('--- Comportamiento (reloj fijo, notificador simulado) ---');
$reloj = new RelojFijo(new DateTimeImmutable('2026-10-29 10:00:00'));
$politica = new PoliticaEscalamiento();

$notificador = new NotificadorSimulado(['enf.turno01']);
$fallidos = (new DespachadorNotificaciones($notificador))->despachar('Alerta PAC-00451', ['enf.turno01', 'med.tratante01']);
$comprobar('CA-06 entrega fallida: 3 intentos para enf.turno01', $notificador->intentos['enf.turno01'] === 3);
$comprobar('CA-06 entrega correcta: 1 intento para med.tratante01', $notificador->intentos['med.tratante01'] === 1);
$comprobar('CA-06 el despachador reporta a enf.turno01 como fallido', $fallidos === ['enf.turno01']);

$alerta = new Alerta('PAC-00451', 'CRITICA', $reloj->ahora());
$alerta->marcarEnviada($reloj->ahora());
$reloj->avanzarMinutos(14);
$comprobar('CA-11 a las 10:14 no vence', !$politica->vencida($alerta, $reloj->ahora()));
$reloj->avanzarMinutos(1);
$comprobar('CA-10 a las 10:15 vence', $politica->vencida($alerta, $reloj->ahora()));
$alerta->escalar($reloj->ahora());
$comprobar('CA-10 queda ESCALADA en nivel 1', $alerta->estado === 'ESCALADA' && $alerta->nivel === 1);

$otra = new Alerta('PAC-00452', 'CRITICA', new DateTimeImmutable('2026-10-29 10:00:00'));
$otra->marcarEnviada(new DateTimeImmutable('2026-10-29 10:00:00'));
$primero = $otra->acusar('enf.turno01', new DateTimeImmutable('2026-10-29 10:05:00'));
$segundo = $otra->acusar('enf.turno01', new DateTimeImmutable('2026-10-29 10:06:00'));
$acuses = count(array_filter($otra->historial, fn ($e) => $e['evento'] === 'acusada'));
$comprobar('CA-08 acuse idempotente: un solo evento «acusada»', $primero && !$segundo && $acuses === 1);
$comprobar('CA-12 acusada no escala a las 10:30',
    !$politica->vencida($otra, new DateTimeImmutable('2026-10-29 10:30:00')));

$linea('');
$linea($fallas === 0 ? 'RESULTADO: todas las comprobaciones pasaron' : "RESULTADO: $fallas comprobación(es) fallaron");

$evidencia = __DIR__ . '/../../evidencia';
if (!is_dir($evidencia)) { mkdir($evidencia, 0777, true); }
file_put_contents("$evidencia/verificacion-srp.txt", implode(PHP_EOL, $salida) . PHP_EOL);
exit($fallas === 0 ? 0 : 1);
