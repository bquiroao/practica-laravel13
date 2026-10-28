<?php

declare(strict_types=1);

/**
 * Semana 15 · Pruebas EXPLORATORIAS previas a la caja negra: entradas raras contra el kernel REAL
 * (adaptadores en memoria). Cada fila imprime la entrada, la respuesta y cómo quedó guardado.
 * De aquí salieron DEF-06 (teléfono) y OBS-01..OBS-03.
 *
 * Uso: php herramientas/exploratorias.php   (desde docs/modulos/pacientes/semana-15)
 */
$micro = dirname(__DIR__, 2) . '/micro-his-pacientes';
require $micro . '/bootstrap/contenedor.php';
foreach (glob($micro . '/tests/Dobles/*.php') as $archivo) {
    require_once $archivo;
}

use Pacientes\Pruebas\Dobles\Escenario;

$alta = static function (array $cambios): string {
    $e = Escenario::crear();
    $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', 'token-admision-oriente',
        array_merge(Escenario::json(Escenario::datosAna()), $cambios)));
    if ($r->estado !== 201) {
        return "HTTP $r->estado " . json_encode($r->cuerpo, JSON_UNESCAPED_UNICODE);
    }
    $p = $e->kernel->manejar(Escenario::http('GET', '/api/v1/pacientes/' . $r->cuerpo['codigo'], 'token-admision-oriente'))->cuerpo['paciente'];

    return sprintf('HTTP 201 · guardado: nombre «%s», nacimiento %s, teléfono %s, documento %s',
        $p['nombre'], $p['nacimiento'], json_encode($p['telefono']), $p['identificador'] ?? 'ninguno');
};

$casos = [
    'Teléfono' => [
        '«1      2» (2 dígitos + 6 espacios)' => ['telefono' => '1      2'],
        '«12 34 56 7» (7 dígitos)' => ['telefono' => '12 34 56 7'],
        '«1234567» (7 dígitos, sin espacios)' => ['telefono' => '1234567'],
    ],
    'Nombres' => [
        '«!!!»' => ['nombres' => '!!!'],
        '«123»' => ['nombres' => '123'],
    ],
    'Fecha de nacimiento' => [
        '0001-01-01' => ['fecha_nacimiento' => '0001-01-01'],
        '1850-01-01' => ['fecha_nacimiento' => '1850-01-01'],
    ],
    'Documento' => [
        'pasaporte del país «ZZZ»' => ['documento' => ['tipo' => 'PASAPORTE', 'numero' => 'AB12345', 'pais' => 'ZZZ']],
        'DPI 1234567800101 (verificador incorrecto)' => ['documento' => ['tipo' => 'DPI', 'numero' => '1234567800101']],
        'DPI 1234567890199 (municipio 99)' => ['documento' => ['tipo' => 'DPI', 'numero' => '1234567890199']],
        'DPI de los datos de prueba (2547896320101)' => ['documento' => ['tipo' => 'DPI', 'numero' => Escenario::DPI_ANA]],
    ],
    'Motivo' => [
        '5 000 caracteres al proteger (supervisión)' => null,
    ],
];

foreach ($casos as $grupo => $filas) {
    echo $grupo . PHP_EOL;
    foreach ($filas as $entrada => $cambios) {
        if ($cambios === null) {
            $e = Escenario::crear();
            $r = $e->kernel->manejar(Escenario::http('POST', '/api/v1/pacientes', 'token-supervisor-oriente',
                Escenario::json(Escenario::datosAna(), ['identidad_protegida' => true, 'motivo' => str_repeat('x', 5000)])));
            $motivo = $e->bitacora->eventos('PACIENTE_REGISTRADO')[0]->detalle['motivo_proteccion'] ?? '';
            $salida = "HTTP $r->estado · motivo guardado en la bitácora: " . strlen($motivo) . ' caracteres';
        } else {
            $salida = $alta($cambios);
        }
        echo '  ' . mb_str_pad($entrada, 44) . " → $salida" . PHP_EOL;
    }
}

// El verificador del CUI: los 8 primeros dígitos por los pesos 2..9, módulo 11
// (la validación del CUI que se usa habitualmente; confirmar con la norma del RENAP al corregir AR-07).
$verificador = static function (string $dpi): string {
    $suma = 0;
    for ($i = 0; $i < 8; $i++) {
        $suma += (int) $dpi[$i] * ($i + 2);
    }

    return sprintf('esperado %d, tiene %s', $suma % 11, $dpi[8]);
};
echo PHP_EOL . 'Dígito verificador de los DPI ficticios de las pruebas' . PHP_EOL;
foreach (['DPI_ANA' => Escenario::DPI_ANA, 'DPI_LUIS' => Escenario::DPI_LUIS, 'DPI_NUEVO' => Escenario::DPI_NUEVO] as $nombre => $dpi) {
    printf("  %-9s %s → %s\n", $nombre, $dpi, $verificador($dpi));
}
