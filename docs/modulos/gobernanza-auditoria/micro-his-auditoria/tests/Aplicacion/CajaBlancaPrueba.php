<?php

declare(strict_types=1);

/*
 * Semana 15 — Pruebas de CAJA BLANCA: se diseñan leyendo el código, para recorrer sus ramas y condiciones.
 *   CB-01 · Caminos básicos (McCabe) de RegistrarMovimiento::ejecutar(): V(G) = 11 decisiones + 1 = 12; 11 caminos factibles
 *           (el de «ninguna regla configurada» no es factible: el contenedor siempre arma las 9 reglas).
 *   CB-02 · MC/DC de las dos condiciones compuestas de ejecutar(): el original de otro hospital y la idempotencia.
 *   CB-03 · Todas las ramas de VerificarIntegridad::recalcular(), con MC/DC de «secuencia ≠ i+1 || sello ≠ recalculado».
 * El grafo de flujo está en semana-15/diagramas/semana-15-grafo-registrar.png; la cobertura de líneas la mide
 * semana-15/herramientas/cobertura-caja-blanca.php.
 */

use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Dominio\Modelo\Movimiento;
use Auditoria\Pruebas\Dobles\Escenario;

const CB_MOTIVO = 'El integrador atribuyó la consulta al usuario equivocado';

$cbPar = static fn (Resultado $r): array => [$r->estado, $r->codigo];
$cbCorr = static fn (string $eventId, string $original, array $cambios = []): array =>
    Escenario::mov(array_merge(['event_id' => $eventId, 'usuario' => 'u-correcto', 'corrige_evento' => $original, 'motivo_correccion' => CB_MOTIVO], $cambios));

prueba('CB-01', 'Caminos básicos de RegistrarMovimiento::ejecutar(): los 11 caminos factibles, cada uno con su salida', function () use ($cbPar, $cbCorr): void {
    $caminos = [];
    // C1 · nodo 1 verdadero: sin permiso → sale antes de las reglas
    $e = Escenario::crear();
    $caminos['C1 sin permiso'] = [$cbPar(Escenario::registrar($e, Escenario::mov(), 'svc-desconocido')), [Resultado::PROHIBIDO, 'ACCESO_DENEGADO']];
    // C2 · una regla rechaza (nodo 3 verdadero)
    $caminos['C2 regla rechaza'] = [$cbPar(Escenario::registrar($e, Escenario::mov(['usuario' => '']))), [Resultado::INVALIDO, 'DATOS_INVALIDOS']];
    // C3 · todas las reglas pasan, sin corrección, evento nuevo → anexar
    $caminos['C3 nuevo'] = [$cbPar(Escenario::registrar($e, Escenario::mov())), [Resultado::CREADO, null]];
    // C4 · existente con el mismo contenido → idempotente
    $caminos['C4 reintento igual'] = [$cbPar(Escenario::registrar($e, Escenario::mov())), [Resultado::OK, 'YA_REGISTRADO']];
    // C5 · existente con otro contenido → conflicto
    $caminos['C5 mismo id, otro contenido'] = [$cbPar(Escenario::registrar($e, Escenario::mov(['usuario' => 'u-otro']))), [Resultado::CONFLICTO, 'EVENTO_EN_CONFLICTO']];
    // C6 · corrección de un evento inexistente (original null por no encontrarse)
    $caminos['C6 corrige inexistente'] = [$cbPar(Escenario::registrar($e, $cbCorr('c-6', 'no-existe'))), [Resultado::INVALIDO, 'DATOS_INVALIDOS']];
    // C7 · corrección de un evento de OTRO hospital (nodo 5 verdadero: original pasa a null)
    Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-norte', 'hospital' => Escenario::H_NORTE]), 'svc-pacientes', Escenario::H_NORTE);
    $caminos['C7 corrige otro hospital'] = [$cbPar(Escenario::registrar($e, $cbCorr('c-7', 'ev-norte'))), [Resultado::INVALIDO, 'DATOS_INVALIDOS']];
    // C8 · corrección válida → anexar con corrige_a
    $r8 = Escenario::registrar($e, $cbCorr('c-8', 'ev-1'));
    $caminos['C8 corrección válida'] = [$cbPar($r8), [Resultado::CREADO, null]];
    // C9 · reintento de la corrección: existente, mismo contenido y misma referencia
    $caminos['C9 reintento de la corrección'] = [$cbPar(Escenario::registrar($e, $cbCorr('c-8', 'ev-1'))), [Resultado::OK, 'YA_REGISTRADO']];
    // C10 · otra corrección del mismo original → ya corregido
    $caminos['C10 ya corregido'] = [$cbPar(Escenario::registrar($e, $cbCorr('c-10', 'ev-1'))), [Resultado::CONFLICTO, 'YA_CORREGIDO']];
    // C11 · la base falla dentro de la transacción → no disponible
    $e->bitacora->fallarEn = ['agregar'];
    $caminos['C11 la base falla'] = [$cbPar(Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-11']))), [Resultado::NO_DISPONIBLE, null]];
    $e->bitacora->fallarEn = [];

    foreach ($caminos as $camino => [$obtenido, $esperado]) {
        igual($esperado[0], $obtenido[0], "$camino: estado");
        if ($esperado[1] !== null) {
            igual($esperado[1], $obtenido[1], "$camino: código");
        }
    }
    igual(1, $e->bitacora->porId($r8->datos['id'])->corrigeA, 'C8 enlaza al original');
    igual(11, count($caminos), 'caminos recorridos');
});

prueba('CB-02', 'MC/DC: «original ≠ null Y de otro hospital» y «mismo contenido Y misma referencia»: cada condición cambia la salida sola', function () use ($cbPar, $cbCorr): void {
    $e = Escenario::crear();
    Escenario::registrar($e, Escenario::mov());                                               // ev-1 en Oriente
    Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-2']));                         // ev-2 en Oriente
    Escenario::registrar($e, Escenario::mov(['event_id' => 'ev-n', 'hospital' => Escenario::H_NORTE]), 'svc-pacientes', Escenario::H_NORTE);
    // (A) original ≠ null · (B) hospital distinto → el original se descarta solo si A y B
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS'], $cbPar(Escenario::registrar($e, $cbCorr('m-1', 'no-existe'))), 'A=F → se descarta (no existe)');
    igual([Resultado::INVALIDO, 'DATOS_INVALIDOS'], $cbPar(Escenario::registrar($e, $cbCorr('m-2', 'ev-n'))), 'A=V, B=V → se descarta (otro hospital)');
    igual(Resultado::CREADO, Escenario::registrar($e, $cbCorr('m-3', 'ev-1'))->estado, 'A=V, B=F → se usa: cambia la salida respecto de B=V');
    // (C) mismo contenido · (D) misma referencia → 200 solo si C y D; si cualquiera es falso, 409
    igual([Resultado::OK, 'YA_REGISTRADO'], $cbPar(Escenario::registrar($e, $cbCorr('m-3', 'ev-1'))), 'C=V, D=V → 200');
    igual([Resultado::CONFLICTO, 'EVENTO_EN_CONFLICTO'], $cbPar(Escenario::registrar($e, $cbCorr('m-3', 'ev-1', ['usuario' => 'u-otro']))), 'C=F, D=V → 409');
    igual([Resultado::CONFLICTO, 'EVENTO_EN_CONFLICTO'], $cbPar(Escenario::registrar($e, $cbCorr('m-3', 'ev-2'))), 'C=V, D=F (otro original) → 409');
});

prueba('CB-03', 'Ramas de VerificarIntegridad: recalcular() (vacía, íntegra, hueco, sello roto con MC/DC del «||», ancla truncada, distinta, confirmada, secuencia 0) y las salidas de ejecutar() (sin permiso, base caída)', function (): void {
    $verificar = static fn (object $e, ?array $ancla = null): array => $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE, $ancla))->datos;
    $sembrar = static function (int $n): object {
        $e = Escenario::crear();
        for ($i = 1; $i <= $n; $i++) {
            Escenario::registrar($e, Escenario::mov(['event_id' => "v-$i"]));
        }
        return $e;
    };
    igual(['estado' => 'INTEGRA', 'verificados' => 0], $verificar(Escenario::crear()), 'R1 cadena vacía: el foreach no itera');
    $e = $sembrar(3);
    igual(['estado' => 'INTEGRA', 'verificados' => 3], $verificar($e), 'R2 íntegra sin ancla (ancla = null)');
    // MC/DC del «||»: (X) secuencia ≠ i+1 · (Y) sello ≠ recalculado
    $hueco = $sembrar(3);
    $hueco->bitacora->ocultarParaPrueba(2);
    igual(['estado' => 'ALTERADA', 'primera_secuencia' => 2, 'verificados' => 1], $verificar($hueco), 'R3 X=V: falta la fila 2 (hueco)');
    $roto = $sembrar(3);
    $o = $roto->bitacora->porId(2);
    $roto->bitacora->alterarParaPrueba(2, Movimiento::reconstituir((int) $o->id, $o->emisor, $o->eventId, $o->hospital, 'u-intruso', $o->modulo, $o->accion,
        $o->ocurridoEn, $o->resultado, $o->recurso, $o->correlationId, $o->detalle, $o->secuencia, $o->sello));
    igual(['estado' => 'ALTERADA', 'primera_secuencia' => 2, 'verificados' => 1], $verificar($roto), 'R4 X=F, Y=V: el usuario cambió, sello sin recalcular');
    // ramas del ancla
    $ultimo = $e->bitacora->porId(3);
    igual(['estado' => 'TRUNCADA', 'esperada_hasta' => 5, 'verificados' => 3], $verificar($e, ['secuencia' => 5, 'sello' => $ultimo->sello]), 'R5 ancla más adelante');
    igual(['estado' => 'ALTERADA', 'primera_secuencia' => 3, 'verificados' => 3], $verificar($e, ['secuencia' => 3, 'sello' => str_repeat('0', 64)]), 'R6 sello del ancla distinto');
    igual(['estado' => 'INTEGRA', 'verificados' => 3, 'ancla_confirmada' => 3], $verificar($e, ['secuencia' => 3, 'sello' => $ultimo->sello]), 'R7 ancla confirmada');
    igual(['estado' => 'INTEGRA', 'verificados' => 3, 'ancla_confirmada' => 0], $verificar($e, ['secuencia' => 0, 'sello' => '']), 'R8 secuencia 0: la rama del ancla no se toma');
    // Los otros dos puntos de salida de ejecutar(): sin permiso (antes de leer) y la base cae al leer la cadena
    igual(Resultado::PROHIBIDO, $e->casos['verificar']->ejecutar(new ComandoVerificar('auditor-oriente', Escenario::H_ORIENTE, null))->estado, 'R9 sin permiso');
    $e->bitacora->fallarEn = ['cadena'];
    igual(Resultado::NO_DISPONIBLE, $e->casos['verificar']->ejecutar(new ComandoVerificar('superadmin', Escenario::H_ORIENTE, null))->estado, 'R10 la base cae');
});
