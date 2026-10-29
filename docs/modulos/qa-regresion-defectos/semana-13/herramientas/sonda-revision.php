<?php

declare(strict_types=1);

/*
 * Semana 13 · Sonda de la revisión técnica formal.
 * Reproduce, sin modificar el código, cada hallazgo de la revisión con los adaptadores en memoria de producción
 * (la misma raíz de composición que usan las pruebas). Cada escenario imprime lo ESPERADO por la regla y lo OBSERVADO.
 *   php herramientas/sonda-revision.php
 * Código de salida: la cantidad de hallazgos reproducidos (0 = ninguno se reproduce: todos corregidos).
 */

$raiz = dirname(__DIR__, 2) . '/micro-his-qa';
require $raiz . '/bootstrap/contenedor.php';
foreach (glob($raiz . '/tests/Dobles/*.php') ?: [] as $archivo) {
    require_once $archivo;
}

use Qa\Dominio\Modelo\CasoPrueba;

$reproducidos = 0;
function escenario(string $id, string $regla, string $esperado, string $observado, bool $sereproduce): void
{
    global $reproducidos;
    $reproducidos += $sereproduce ? 1 : 0;
    printf("%s  %s\n    regla:     %s\n    esperado:  %s\n    observado: %s\n\n", $sereproduce ? 'SE REPRODUCE' : 'no se reproduce', $id, $regla, $esperado, $observado);
}
$falla = static fn (string $caso, string $severidad): array => ['caso' => $caso, 'resultado' => 'FALLA',
    'evidencias' => [['tipo' => 'captura', 'bytes' => Escenario::PNG . $caso]],
    'defecto' => ['titulo' => "Falla en $caso", 'pasos' => ['Ejecutar el caso'], 'severidad' => $severidad]];

// RT-01 · Un defecto CRÍTICO propuesto y sin clasificar no bloquea el GO --------------------------------------------
$e = new Escenario();
$id = $e->campanaEnCurso(['TC-107', 'TC-108', 'TC-109'], ['expediente'], 0.6);
$e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $id, Escenario::clave(), $falla('TC-107', 'CRITICA'));
$e->registrar($id, 'TC-108', 'PASA');
$e->registrar($id, 'TC-109', 'PASA');
$t = $e->uc['trazabilidad']->ejecutar('lider-qa', 'hosp-norte', $id)->datos['casos'][0]['defectos'][0];
$c = $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
escenario('RT-01', 'R-09: no hay GO con un defecto crítico abierto',
    'NO-GO (o 409 hasta clasificar): el tester propuso CRÍTICA y nadie la revisó',
    sprintf('trazabilidad: severidad %s, clasificado %s, bloquea_go %s → cierre %s (tasa %.2f, umbral 0.60)', $t['severidad'],
        var_export($t['clasificado'], true), var_export($t['bloquea_go'], true), $c->datos['decision'] ?? $c->codigo, $c->datos['tasa_aprobados'] ?? 0),
    ($c->datos['decision'] ?? '') === 'GO');

// RT-02 · El cierre cuenta defectos de campañas anteriores que la trazabilidad no muestra ---------------------------
$e = new Escenario();
$a = $e->campanaEnCurso(['TC-107', 'TC-108']);
$e->uc['registrar']->ejecutar('tito-tester', 'hosp-norte', $a, Escenario::clave(), $falla('TC-107', 'MAYOR'));
$e->uc['clasificar']->ejecutar('lider-qa', 'hosp-norte', 1, ['severidad' => 'CRITICA', 'prioridad' => 'ALTA', 'motivo' => 'Edita una nota firmada']);
$e->registrar($a, 'TC-108', 'PASA');
$e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $a);
$b = $e->campanaEnCurso(['TC-107', 'TC-108']);
$e->registrar($b, 'TC-107', 'PASA');
$e->registrar($b, 'TC-108', 'PASA');
$t = $e->uc['trazabilidad']->ejecutar('lider-qa', 'hosp-norte', $b)->datos;
$bloquean = array_sum(array_map(static fn (array $f): int => count(array_filter($f['defectos'], static fn (array $d): bool => $d['bloquea_go'])), $t['casos']));
$c = $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $b);
escenario('RT-02', 'D-23: la trazabilidad dice qué bloquea el GO ANTES de cerrar',
    'si el cierre va a dar NO-GO por un defecto, la trazabilidad lo marca con bloquea_go',
    sprintf('trazabilidad de la campaña 2: %d defectos con bloquea_go → cierre %s: %s', $bloquean, $c->datos['decision'],
        implode('; ', $c->datos['causas'])),
    $bloquean === 0 && $c->datos['decision'] === 'NO_GO');

// RT-03 · La trazabilidad toma los requisitos de la ÚLTIMA versión del caso, no de la versión congelada en la campaña -
$e = new Escenario();
$id = $e->campanaEnCurso(['TC-107', 'TC-108']);
$e->casos->real->guardar(new CasoPrueba('hosp-norte', 'TC-108', 2, 'ana-qa', 'H4 alergias (borrador v2)', 'Paciente con alergia',
    [['accion' => 'x', 'esperado' => 'y']], 'ALTA', ['RF-99'], CasoPrueba::BORRADOR));
$t = $e->uc['trazabilidad']->ejecutar('lider-qa', 'hosp-norte', $id)->datos;
$fila = $t['casos'][1];
escenario('RT-03', 'RF-11: requisito ↔ caso (VERSIÓN) ↔ intento',
    'la campaña congeló TC-108 v1: sus requisitos son los de la v1',
    sprintf('fila TC-108: versión %d, requisitos [%s]; requisitos de la campaña: [%s]', $fila['version'], implode(', ', $fila['requisitos']),
        implode(', ', array_keys($t['requisitos']))),
    in_array('RF-99', $fila['requisitos'], true));

// RT-04 · CA-02: editar un caso aprobado no tiene operación ----------------------------------------------------------
$e = new Escenario();
$r = $e->uc['disenar']->ejecutar('ana-qa', 'hosp-norte', ['id' => 'TC-107', 'titulo' => 'Nota firmada (v2)', 'precondiciones' => 'x',
    'pasos' => [['accion' => 'a', 'esperado' => 'b']], 'prioridad' => 'ALTA', 'requisitos' => ['RF-07']]);
$metodos = array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass(CasoPrueba::class))->getMethods(ReflectionMethod::IS_PUBLIC));
escenario('RT-04', 'CA-02: editar uno aprobado → versión 2 en borrador y la 1 sigue aprobada',
    'una operación que cree la versión siguiente',
    sprintf('diseñar TC-107 otra vez → %s; métodos públicos de CasoPrueba: %s; rutas del contrato: 9, ninguna de edición', $r->codigo, implode(', ', $metodos)),
    $r->codigo === 'CASO_EXISTENTE' && !in_array('nuevaVersion', $metodos, true));

// RT-05 · Una campaña donde todo es NO_APLICA se libera con GO y tasa 100 % -----------------------------------------
$e = new Escenario();
$id = $e->campanaEnCurso(['TC-107', 'TC-108']);
$e->registrar($id, 'TC-107', 'NO_APLICA', ['motivo' => 'Sin ambiente']);
$e->registrar($id, 'TC-108', 'NO_APLICA', ['motivo' => 'Sin ambiente']);
$c = $e->uc['cerrar']->ejecutar('lider-qa', 'hosp-norte', $id);
escenario('RT-05', 'R-09: GO con la tasa de aprobados ≥ el umbral',
    'sin ningún caso aplicable no hay evidencia para liberar: NO-GO o 409',
    sprintf('cierre %s, tasa %.2f, causas [%s]', $c->datos['decision'] ?? $c->codigo, $c->datos['tasa_aprobados'] ?? 0, implode('; ', $c->datos['causas'] ?? [])),
    ($c->datos['decision'] ?? '') === 'GO');

// RT-06 · CORREGIDO acepta cualquier texto como build ----------------------------------------------------------------
$e = new Escenario();
$id = $e->campanaEnCurso(['TC-107', 'TC-108']);
$e->registrar($id, 'TC-107', 'FALLA');
$e->uc['clasificar']->ejecutar('lider-qa', 'hosp-norte', 1, ['severidad' => 'MAYOR', 'prioridad' => 'ALTA']);
$r = $e->uc['corregir']->ejecutar('dev-luis', 'hosp-norte', 1, 'ya quedó');
escenario('RT-06', 'R-08 y R-03: el build tiene el formato semver(+commit) que exige iniciar una campaña',
    '422 DATOS_INVALIDOS [build]',
    sprintf('marcar corregido con build «ya quedó» → %s', $r->codigo),
    $r->codigo === 'DEFECTO_CORREGIDO');

echo "Hallazgos reproducidos: $reproducidos de 6\n";
exit($reproducidos);
