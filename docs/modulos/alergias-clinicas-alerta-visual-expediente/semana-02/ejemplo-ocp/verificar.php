<?php
declare(strict_types=1);

// Evidencia OCP. Uso (desde la raíz del repo o desde aquí):
//   php docs/modulos/alergias-clinicas-alerta-visual-expediente/semana-02/ejemplo-ocp/verificar.php --evidencia=docs/modulos/alergias-clinicas-alerta-visual-expediente/semana-02/evidencia/salida-ocp.txt
require __DIR__ . '/antes/CalculadorAlertaAntes.php';
require __DIR__ . '/despues/ReglaNivelAlerta.php';
require __DIR__ . '/despues/CalculadorAlerta.php';

// Huella del calculador «cerrado» (saltos de línea normalizados para que valga en Windows).
const HUELLA_CALCULADOR = 'f40a9f576cddf0a348d1935509ce5ee8d6de25724c0cb56baad403ce49f3399a';

/** Regla NUEVA escrita solo aquí, como lo haría otro desarrollador: no se edita ningún archivo existente. */
final class ReglaLatexEnQuirofano implements ReglaNivelAlerta
{
    public function nivelPara(array $alergia): ?string
    {
        return ($alergia['tipo'] ?? '') === 'latex_quirofano' ? 'ALTA' : null;
    }
}

$casos = [
    'sin alergias' => [[], 'SIN_REGISTRO'],
    'leve' => [[['severidad' => 'mild', 'reaccion' => null]], 'INFORMATIVA'],
    'moderada + grave' => [[['severidad' => 'moderate', 'reaccion' => null], ['severidad' => 'severe', 'reaccion' => null]], 'ALTA'],
    'desconocida' => [[['severidad' => 'unknown', 'reaccion' => null]], 'ALTA'],
    'riesgo vital' => [[['severidad' => 'life_threatening', 'reaccion' => null]], 'CRITICA'],
    'moderada con anafilaxia' => [[['severidad' => 'moderate', 'reaccion' => 'Anafilaxia en 2019']], 'CRITICA'],
];
$antes = new CalculadorAlertaAntes();
$despues = new CalculadorAlerta(new ReglaPorSeveridad(), new ReglaReaccionAnafilactica());
$lineas = [];
$fallas = 0;
$revisar = function (string $id, bool $ok, string $detalle) use (&$lineas, &$fallas) {
    $lineas[] = ($ok ? '[OK] ' : '[FALLA] ') . "{$id} {$detalle}";
    $fallas += $ok ? 0 : 1;
};

foreach ($casos as $nombre => [$alergias, $esperado]) {
    $a = $antes->calcular($alergias);
    $d = $despues->calcular($alergias);
    $revisar('OCP-01', $a === $esperado && $d === $esperado, "mismo resultado antes y después ({$nombre}): antes={$a} después={$d}");
}

$codigo = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/despues/CalculadorAlerta.php'));
// Código sin comentarios: lo que importa es qué ejecuta el calculador, no lo que dicen sus notas.
$sinComentarios = implode('', array_map(fn ($t) => is_array($t) ? (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $t[1]) : $t, token_get_all($codigo)));
$extendido = new CalculadorAlerta(new ReglaPorSeveridad(), new ReglaReaccionAnafilactica(), new ReglaLatexEnQuirofano());
$nivel = $extendido->calcular([['severidad' => 'mild', 'reaccion' => null, 'tipo' => 'latex_quirofano']]);
$revisar('OCP-02', $nivel === 'ALTA', "regla nueva aplicada sin editar archivos: nivel={$nivel}");
$revisar('OCP-03', hash('sha256', $codigo) === HUELLA_CALCULADOR, 'CalculadorAlerta.php sigue idéntico (SHA-256 registrado)');
$revisar('OCP-04', !preg_match('/severidad|anafila|life_threatening/', $sinComentarios), 'el calculador no conoce ninguna regla concreta');

$codigoAntes = file_get_contents(__DIR__ . '/antes/CalculadorAlertaAntes.php');
$revisar('ANT-01', (bool) preg_match('/switch \(\$a\[.severidad.\]\)/', $codigoAntes) && str_contains($codigoAntes, 'anafila'),
    'antes: severidad y anafilaxia viven en el mismo método (cada regla nueva obliga a editarlo)');

$lineas[] = sprintf('RESULTADO: %d verificaciones, %d fallas', count($lineas), $fallas);
$texto = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $texto;
$op = getopt('', ['evidencia:']);
if (isset($op['evidencia'])) {
    @mkdir(dirname($op['evidencia']), 0777, true);
    file_put_contents($op['evidencia'], 'Fecha: ' . date('Y-m-d H:i:s') . ' | PHP ' . PHP_VERSION . PHP_EOL . $texto);
}
exit($fallas > 0 ? 1 : 0);
