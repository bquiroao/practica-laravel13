<?php
declare(strict_types=1);

// Semana 15: unitarias (U), caja blanca (CB) y caja negra (CN), trazadas a RF/RNF/RN.
// Las marcadas [DEFECTO D-NN] describen el comportamiento CORRECTO y fallan mientras el defecto siga abierto.
use Alergias\Aplicacion\RegistrarAlergia;
use Alergias\Aplicacion\SolicitudRegistro;
use Alergias\Dominio\Alergeno;
use Alergias\Dominio\Alergia;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\NivelAlerta;
use Alergias\Dominio\PacienteRef;
use Alergias\Dominio\ReglaDominioViolada;
use Alergias\Dominio\Reglas\ReglaPorSeveridad;
use Alergias\Dominio\Reglas\ReglaReaccionAnafilactica;
use Alergias\Dominio\Severidad;
use Alergias\Dominio\TipoAlergia;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/dobles.php';

$medico = '7a1e0000-0000-4000-8000-0000000000a1';
$paciente = new PacienteRef('p-451', 'PAC-00451', 'active');
$hoy = new DateTimeImmutable('2026-10-28');
$alergia = fn (string $nombre, string $sev, ?string $reaccion = null, string $id = 'id-1')
    => Alergia::registrar($id, $paciente, new Alergeno(null, $nombre), TipoAlergia::Alergia, Severidad::from($sev), $reaccion, null, $medico, $hoy);
$calculador = new CalculadorAlerta(new ReglaPorSeveridad(), new ReglaReaccionAnafilactica());
/** Llama a la API v1 en proceso sobre una base de prueba limpia (o la indicada). Devuelve [estado, json]. */
$api = function (string $metodo, string $ruta, ?string $token, mixed $cuerpo = null, ?PDO $pdo = null, array $extra = []): array {
    $kernel = armarApi(armarCasosDeUso($pdo ?? baseDePrueba()), fn () => null);
    $cab = $extra + ($token ? ['authorization' => "Bearer {$token}"] : []);
    $r = $kernel->manejar($metodo, $ruta, $cab, is_string($cuerpo) ? $cuerpo : (string) json_encode($cuerpo));
    return [$r->estado, json_decode($r->cuerpo, true)];
};
$ALERGIAS = '/api/v1/pacientes/PAC-00451/alergias';
$hoyGt = (new DateTimeImmutable('today', new DateTimeZone('America/Guatemala')));

return [
    // ---------- Unitarias ----------
    'U-01 [RN-01, RN-03] Alergeno: normaliza mayúsculas y espacios, prefiere el código y exige uno de los dos' => function () {
        Arnes::igual('penicilina', (new Alergeno(null, '  PENICILINA '))->clave(), 'nombre');
        Arnes::igual('j01ca04', (new Alergeno('J01CA04', 'Penicilina'))->clave(), 'código primero');
        Arnes::lanza(fn () => new Alergeno('  ', ''), ReglaDominioViolada::class, 'ALERGENO_REQUERIDO');
    },
    'U-02 [RN-08] ReglaPorSeveridad: las 5 severidades dan el nivel esperado' => function () use ($alergia) {
        $esperado = ['life_threatening' => 'CRITICA', 'severe' => 'ALTA', 'unknown' => 'ALTA', 'moderate' => 'MODERADA', 'mild' => 'INFORMATIVA'];
        foreach ($esperado as $sev => $nivel) {
            Arnes::igual($nivel, (new ReglaPorSeveridad())->nivelPara($alergia('X', $sev))->value, $sev);
        }
    },
    'U-03 [RN-04] Reacción: 500 caracteres se aceptan, 501 no; en blanco se rechaza' => function () use ($alergia) {
        Arnes::igual(500, mb_strlen($alergia('X', 'mild', str_repeat('á', 500))->reaccion), 'límite 500');
        Arnes::lanza(fn () => $alergia('X', 'mild', str_repeat('á', 501)), ReglaDominioViolada::class, 'REACCION_DEMASIADO_LARGA');
        Arnes::lanza(fn () => $alergia('X', 'mild', "  \t "), ReglaDominioViolada::class, 'REACCION_EN_BLANCO');
    },
    'U-04 [RN-09] Solo ALTA y CRITICA exigen reconocimiento' => function () {
        $exige = array_map(fn (NivelAlerta $n) => $n->exigeReconocimiento(), NivelAlerta::cases());
        Arnes::igual([false, false, false, true, true], $exige, 'SIN_REGISTRO, INFORMATIVA, MODERADA, ALTA, CRITICA');
    },
    '[DEFECTO D-02] U-05 [RN-08] «Reacción anafiláctica» (con tilde) debe subir a CRITICA' => function () use ($alergia) {
        $nivel = (new ReglaReaccionAnafilactica())->nivelPara($alergia('Ibuprofeno', 'moderate', 'Reacción anafiláctica'));
        Arnes::igual('CRITICA', $nivel?->value, 'nivel con «anafiláctica»');
    },

    // ---------- Caja blanca ----------
    'CB-01 [RN-05] RegistrarAlergia::fecha(): los 5 caminos de la condición (nula, vacía, válida, desbordada, otro formato)' => function () use ($calculador, $medico) {
        $caso = fn (?string $f) => (new RegistrarAlergia(new PacientesFijos(), new AlergiasEnLista(), $calculador, new RelojFijo()))
            ->ejecutar(new SolicitudRegistro('PAC-00451', null, 'Polen', 'allergy', 'mild', null, $f, $medico));
        Arnes::igual('INFORMATIVA', $caso(null)->nivel->value, 'camino 1: null');
        Arnes::igual('INFORMATIVA', $caso('')->nivel->value, 'camino 2: vacía');
        Arnes::igual('INFORMATIVA', $caso('2019-03-02')->nivel->value, 'camino 3: válida');
        Arnes::lanza(fn () => $caso('2019-02-30'), ReglaDominioViolada::class, 'FECHA_INVALIDA'); // createFromFormat la desborda a 03-02
        Arnes::lanza(fn () => $caso('02/03/2019'), ReglaDominioViolada::class, 'FECHA_INVALIDA'); // createFromFormat devuelve false
    },
    'CB-02 [RN-08, RN-09] CalculadorAlerta: ramas lista vacía, regla null, regla que sube, desempate y huella independiente del orden' => function () use ($calculador, $alergia) {
        Arnes::igual('SIN_REGISTRO', $calculador->calcular('PAC-00451', [])->nivel->value, 'no entra al ciclo');
        $mod = $alergia('Ibuprofeno', 'moderate', 'Urticaria', 'id-1');
        Arnes::igual('MODERADA', $calculador->calcular('PAC-00451', [$mod])->nivel->value, 'anafilaxia devuelve null');
        $ana = $alergia('Polen', 'mild', 'Anafilaxia', 'id-2');
        Arnes::igual('CRITICA', $calculador->calcular('PAC-00451', [$ana])->nivel->value, 'regla que sube');
        $a = $calculador->calcular('PAC-00451', [$alergia('Zinc', 'severe', null, 'id-3'), $alergia('Avena', 'severe', null, 'id-4')]);
        Arnes::igual(['Avena', 'Zinc'], array_map(fn ($d) => $d['alergia']->alergeno->etiqueta(), $a->detalle), 'desempate alfabético');
        Arnes::igual($calculador->calcular('X', [$mod, $ana])->huella, $calculador->calcular('X', [$ana, $mod])->huella, 'huella');
    },
    'CB-03 [RNF-01] KernelApi: MC/DC de «hospital del token distinto O encabezado distinto»' => function () use ($api) {
        $ruta = '/api/v1/pacientes/PAC-00451/alerta';
        Arnes::igual(403, $api('GET', $ruta, 'tok-med-h02')[0], 'A=verdadero (token de H-02), sin encabezado');
        Arnes::igual(403, $api('GET', $ruta, 'tok-med-h01', null, null, ['x-tenant-id' => 'H-02'])[0], 'A=falso, B=verdadero');
        Arnes::igual(200, $api('GET', $ruta, 'tok-med-h01', null, null, ['x-tenant-id' => 'H-01'])[0], 'A=falso, B=falso (encabezado igual)');
        Arnes::igual(200, $api('GET', $ruta, 'tok-med-h01')[0], 'A=falso, encabezado ausente');
    },

    // ---------- Caja negra (API v1) ----------
    'CN-01 [RF-01, RN-02] Equivalencia de severidad: 5 clases válidas → 201; inválidas → 422' => function () use ($api, $ALERGIAS) {
        $pdo = baseDePrueba();
        foreach (['life_threatening', 'severe', 'moderate', 'mild', 'unknown'] as $i => $sev) {
            Arnes::igual(201, $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => "Sustancia {$i}", 'severidad' => $sev], $pdo)[0], $sev);
        }
        foreach (['grave', 'SEVERE', ''] as $sev) {
            [$estado, $json] = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'Otra', 'severidad' => $sev], $pdo);
            Arnes::igual([422, 'SEVERIDAD_INVALIDA'], [$estado, $json['error']['codigo']], "«{$sev}»");
        }
    },
    'CN-02 [RF-01, RN-05] Límites de fecha: hoy → 201; mañana → 422; 2026-02-29 (no bisiesto) → 422' => function () use ($api, $ALERGIAS, $hoyGt) {
        $pdo = baseDePrueba();
        Arnes::igual(201, $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'A', 'severidad' => 'mild', 'fecha_inicio' => $hoyGt->format('Y-m-d')], $pdo)[0], 'hoy');
        $r = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'B', 'severidad' => 'mild', 'fecha_inicio' => $hoyGt->modify('+1 day')->format('Y-m-d')], $pdo);
        Arnes::igual('FECHA_INICIO_FUTURA', $r[1]['error']['codigo'] ?? null, 'mañana');
        $r = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'C', 'severidad' => 'mild', 'fecha_inicio' => '2026-02-29'], $pdo);
        Arnes::igual('FECHA_INVALIDA', $r[1]['error']['codigo'] ?? null, '29 de febrero de 2026');
    },
    'CN-03 [RF-08, RN-07] Permisos: matriz rol × operación' => function () use ($api, $ALERGIAS) {
        $ops = [
            ['GET', '/api/v1/pacientes/PAC-00451/alerta', null],
            ['POST', $ALERGIAS, ['alergeno_nombre' => 'Polen', 'severidad' => 'mild']],
            ['POST', '/api/v1/pacientes/PAC-00451/alerta/reconocimientos', ['huella' => 'x']],
        ];
        // reconocer con huella inválida en alerta MODERADA → 422 (pasó el permiso); sin permiso → 403
        $esperado = ['tok-med-h01' => [200, 201, 422], 'tok-enf-h01' => [200, 403, 422], 'tok-adm-h01' => [403, 403, 403]];
        foreach ($esperado as $token => $estados) {
            foreach ($ops as $i => [$m, $ruta, $cuerpo]) {
                Arnes::igual($estados[$i], $api($m, $ruta, $token, $cuerpo)[0], "{$token} {$m} {$ruta}");
            }
        }
    },
    'CN-04 [RNF-04, RNF-05] Errores: 401, 400, 404, 409 y 503 con el formato único' => function () use ($api, $ALERGIAS) {
        $casos = [
            [401, 'NO_AUTENTICADO', fn () => $api('GET', '/api/v1/pacientes/PAC-00451/alerta', null)],
            [400, 'FORMATO_INVALIDO', fn () => $api('POST', $ALERGIAS, 'tok-med-h01', '{"roto"')],
            [404, 'PACIENTE_NO_ENCONTRADO', fn () => $api('GET', '/api/v1/pacientes/PAC-99999/alerta', 'tok-med-h01')],
            [409, 'ALERGIA_DUPLICADA', fn () => $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'ibuprofeno', 'severidad' => 'mild'])],
        ];
        foreach ($casos as [$estado, $codigo, $llamada]) {
            [$e, $json] = $llamada();
            Arnes::igual([$estado, $codigo], [$e, $json['error']['codigo'] ?? null], $codigo);
            Arnes::igual(['codigo', 'mensaje', 'request_id'], array_keys($json['error']), 'formato');
        }
        $pdo = baseDePrueba();
        $pdo->prepare('SET default_transaction_read_only = on')->execute();
        [$e] = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'Polen', 'severidad' => 'mild'], $pdo);
        $pdo->prepare('SET default_transaction_read_only = off')->execute();
        Arnes::igual([503, 1], [$e, contarAlergias($pdo)], '503 sin filas nuevas');
    },
    'CN-05 [RN-04] Límite de la reacción por la API: 500 → 201; 501 → 422' => function () use ($api, $ALERGIAS) {
        $pdo = baseDePrueba();
        Arnes::igual(201, $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'A', 'severidad' => 'mild', 'reaccion' => str_repeat('x', 500)], $pdo)[0], '500');
        $r = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'B', 'severidad' => 'mild', 'reaccion' => str_repeat('x', 501)], $pdo);
        Arnes::igual('REACCION_DEMASIADO_LARGA', $r[1]['error']['codigo'] ?? null, '501');
    },
    '[DEFECTO D-01] CN-06 [RF-03, RN-03] El mismo alérgeno por código+nombre y luego solo por nombre debe dar 409' => function () use ($api, $ALERGIAS) {
        $pdo = baseDePrueba();
        $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_codigo' => 'J01CA04', 'alergeno_nombre' => 'Penicilina', 'severidad' => 'severe'], $pdo);
        $estado = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'Penicilina', 'severidad' => 'severe'], $pdo)[0];
        Arnes::igual(409, $estado, 'segundo registro de Penicilina');
    },
    '[DEFECTO D-03] CN-07 [RF-01] Un campo con tipo JSON incorrecto («reaccion»: 123) debe dar 400, no guardarse sin él' => function () use ($api, $ALERGIAS) {
        [$estado, $json] = $api('POST', $ALERGIAS, 'tok-med-h01', ['alergeno_nombre' => 'Polen', 'severidad' => 'mild', 'reaccion' => 123]);
        Arnes::igual('400 FORMATO_INVALIDO', $estado . ' ' . ($json['error']['codigo'] ?? 'sin error: se guardó'), 'respuesta');
    },
];
