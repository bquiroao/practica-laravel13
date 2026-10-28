<?php

declare(strict_types=1);

/*
 * Semana 15 · Pruebas de CAJA BLANCA: diseñadas leyendo el código (semana-15/diagramas/semana-15-grafo-emitir.png).
 *   CB-01 · caminos básicos de EmitirPrescripcion (ejecutar → emitir → reintento / rechazo / guardar).
 *   CB-02 · MC/DC de las tres condiciones compuestas del flujo de emisión.
 *   CB-03 · caminos y ramas de AnularPrescripcion.
 * La cobertura de líneas que logran se mide con semana-15/herramientas/cobertura-caja-blanca.php (phpdbg).
 */

use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Excepciones\YaAnulada;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Anulacion;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;
use Prescripciones\Dominio\Modelo\Prescripcion;
use Prescripciones\Dominio\Modelo\ResultadoVerificacion;
use Prescripciones\Dominio\Modelo\SolicitudEmision;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Reglas\PoliticaEmision;
use Prescripciones\Persistencia\FabricaAdaptadores;

const CB_JUSTIFICACION = 'Sin alternativa: cultivo sensible solo a cefalosporinas';

$cbResumen = static fn (Resultado $r): string => $r->tipo . ' ' . $r->codigo;
/** Una prescripción «ganadora» de una carrera, guardada por otra transacción con la clave dada. */
$cbGanadora = static fn (SolicitudEmision $s, string $clave): Prescripcion => new Prescripcion(null, 'hosp-norte', $s->paciente, $s->usuario, $clave, $s->huella(), $s->items,
    new ResultadoVerificacion(ResultadoVerificacion::SIN_HALLAZGOS, []), [], null, false, new DateTimeImmutable());

prueba('CB-01', 'Caminos básicos de EmitirPrescripcion: V(G) = 14, 14 caminos independientes C1..C14, cada uno con su salida', function () use ($cbResumen, $cbGanadora): void {
    $emitir = static fn (Escenario $e, SolicitudEmision $s): string => $cbResumen($e->casos['emitir']->ejecutar($s));
    $para = [Escenario::item('PARA500')];
    $caminos = [];

    $e = new Escenario();
    $caminos['C1 sin permiso de emitir'] = [$emitir($e, Escenario::solicitud('P-005', $para, usuario: 'farm-norte')), 'DENEGADO PERMISO_DENEGADO'];
    $e = new Escenario();
    $emitir($e, Escenario::solicitud('P-005', $para, clave: 'k'));
    $caminos['C2 reintento: misma clave y mismo contenido'] = [$emitir($e, Escenario::solicitud('P-005', $para, clave: 'k')), 'OK YA_EMITIDA'];
    $caminos['C3 misma clave, otro contenido'] = [$emitir($e, Escenario::solicitud('P-005', [Escenario::item('IBU400', 400)], clave: 'k')), 'CONFLICTO CLAVE_REUTILIZADA'];
    // Regresión de DEF-02 (AR-02 de la semana 13): la clave es de la intención de UN prescriptor.
    $caminos['C4 misma clave y contenido, OTRO prescriptor'] = [$emitir($e, Escenario::solicitud('P-005', $para, usuario: 'dr-ruiz', clave: 'k')), 'CONFLICTO CLAVE_REUTILIZADA'];
    $caminos['C5 paciente de otro hospital'] = [$emitir(new Escenario(), Escenario::solicitud('P-900', $para)), 'NO_ENCONTRADO NO_ENCONTRADO'];
    $e = new Escenario(new FuenteAlergiasCaida());
    $caminos['C6 el Expediente no responde (fail-closed)'] = [$emitir($e, Escenario::solicitud('P-005', $para)), 'NO_DISPONIBLE VERIFICACION_NO_DISPONIBLE'];
    afirmar($e->auditoria->acciones() === ['VERIFICACION_NO_DISPONIBLE'], 'C6 deja su traza aparte');
    $caminos['C7 datos inválidos'] = [$emitir(new Escenario(), Escenario::solicitud('P-005', [Escenario::item('PARA500', 0)])), 'RECHAZADO DATOS_INVALIDOS'];
    $e = new Escenario();
    $caminos['C8 bloqueo por alergia'] = [$emitir($e, Escenario::solicitud('P-001', [Escenario::item('AMOX500')])), 'RECHAZADO BLOQUEO_ALERGIA'];
    afirmar($e->auditoria->acciones() === ['PRESCRIPCION_BLOQUEADA'], 'C8 deja su traza aparte');
    $caminos['C9 sin registros y sin confirmar'] = [$emitir(new Escenario(), Escenario::solicitud('P-003', $para)), 'RECHAZADO CONFIRMACION_REQUERIDA'];
    $caminos['C10 advertencia sin justificación'] = [$emitir(new Escenario(), Escenario::solicitud('P-002', [Escenario::item('CEFA500')])), 'CONFLICTO JUSTIFICACION_REQUERIDA'];
    $caminos['C11 emitida'] = [$emitir(new Escenario(), Escenario::solicitud('P-002', [Escenario::item('CEFA500')], justificacion: CB_JUSTIFICACION)), 'CREADO EMITIDA'];
    $e = new Escenario();
    $s = Escenario::solicitud('P-005', $para, clave: 'carrera');
    $e->repositorio->ganadoraConcurrente = $cbGanadora($s, 'carrera');
    $caminos['C12 carrera: otra transacción ganó con la misma clave'] = [$emitir($e, $s), 'OK YA_EMITIDA'];
    $e = new Escenario();
    $s = Escenario::solicitud('P-005', $para, clave: 'carrera-2');
    $e->repositorio->ganadoraConcurrente = $cbGanadora($s, 'otra-clave');
    $caminos['C13 carrera: ClaveEnUso pero la ganadora no se encuentra'] = [$emitir($e, $s), 'CONFLICTO CLAVE_REUTILIZADA'];
    $e = new Escenario();
    $e->repositorio->fallar = true;
    $caminos['C14 la base no responde'] = [$emitir($e, Escenario::solicitud('P-005', $para)), 'NO_DISPONIBLE ERROR_PERSISTENCIA'];

    $mal = array_filter($caminos, static fn (array $c): bool => $c[0] !== $c[1]);
    igual([], array_map(static fn (array $c): string => "obtenido {$c[0]}, esperado {$c[1]}", $mal), 'caminos con otra salida');
    igual(14, count($caminos), 'caminos recorridos');
});

prueba('CB-02', 'MC/DC: «!evaluado && !confirma», «justificación < 20 || !override» y «misma huella && mismo prescriptor»: cada condición cambia la salida sola; y las ramas de la previa', function (): void {
    $pol = new PoliticaEmision();
    $sol = static fn (?string $j, bool $conf): SolicitudEmision => new SolicitudEmision('dr-lopez', 'hosp-norte', 'P-002', 'k', [], $j, $conf);
    $tipo = static fn (string $v, bool $evaluado, ?string $j, bool $conf, bool $override): string
        => $pol->decidir(new ResultadoVerificacion($v, []), new ExpedienteAlergias($evaluado, []), $sol($j, $conf), static fn (): bool => $override)->tipo;

    // A = !evaluado, B = !confirma  →  REQUIERE_CONFIRMACION solo si A y B
    igual(['REQUIERE_CONFIRMACION', 'PERMITIDA', 'PERMITIDA'], [
        $tipo('SIN_HALLAZGOS', false, null, false, true),  // A=V, B=V
        $tipo('SIN_HALLAZGOS', true, null, false, true),   // A=F, B=V  (A decide)
        $tipo('SIN_HALLAZGOS', false, null, true, true),   // A=V, B=F  (B decide)
    ], 'confirmación');
    // C = justificación < 20, D = !override  →  REQUIERE_JUSTIFICACION si C o D
    $j19 = str_repeat('x', 19);
    $j20 = str_repeat('x', 20);
    igual(['PERMITIDA', 'REQUIERE_JUSTIFICACION', 'REQUIERE_JUSTIFICACION'], [
        $tipo('ADVERTENCIA', true, $j20, false, true),  // C=F, D=F
        $tipo('ADVERTENCIA', true, $j19, false, true),  // C=V, D=F  (C decide)
        $tipo('ADVERTENCIA', true, $j20, false, false), // C=F, D=V  (D decide)
    ], 'justificación y override');
    // La rama de la previa (semana 12): resultadoPrevio = «evaluado ? estado : CONFIRMACION_REQUERIDA» y puedeJustificar solo con ADVERTENCIA.
    // La primera medición de cobertura mostró estas dos líneas sin recorrer (semana-15/evidencia/2a-caja-blanca-cobertura-primera.txt).
    $v = static fn (string $estado): ResultadoVerificacion => new ResultadoVerificacion($estado, []);
    igual(['ADVERTENCIA', 'CONFIRMACION_REQUERIDA', true, null, null], [
        $pol->resultadoPrevio($v('ADVERTENCIA'), new ExpedienteAlergias(true, [])),
        $pol->resultadoPrevio($v('ADVERTENCIA'), new ExpedienteAlergias(false, [])),
        $pol->puedeJustificar($v('ADVERTENCIA'), new ExpedienteAlergias(true, []), static fn (): bool => true),
        $pol->puedeJustificar($v('ADVERTENCIA'), new ExpedienteAlergias(false, []), static fn (): bool => true),
        $pol->puedeJustificar($v('SIN_HALLAZGOS'), new ExpedienteAlergias(true, []), static fn (): bool => true),
    ], 'previa');
    // E = misma huella, F = mismo prescriptor  →  YA_EMITIDA solo si E y F (EmitirPrescripcion::reintento, DEF-02)
    $e = new Escenario();
    $para = [Escenario::item('PARA500')];
    $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', $para, clave: 'mcdc'));
    igual(['YA_EMITIDA', 'CLAVE_REUTILIZADA', 'CLAVE_REUTILIZADA'], [
        $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', $para, clave: 'mcdc'))->codigo,                         // E=V, F=V
        $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500', 400)], clave: 'mcdc'))->codigo, // E=F, F=V (E decide)
        $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', $para, usuario: 'dr-ruiz', clave: 'mcdc'))->codigo,     // E=V, F=F (F decide)
    ], 'reintento');
});

prueba('CB-03', 'Caminos y ramas de AnularPrescripcion: sin permiso, inexistente, ya anulada, motivo corto, anulada, carrera (YaAnulada), base caída y traza que falla', function () use ($cbResumen): void {
    $preparar = static function (): array {
        $e = new Escenario();
        $id = $e->casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]))->datos['id'];

        return [$e, $id];
    };
    $anular = static fn (Escenario $e, int $id, string $motivo = 'Pauta duplicada con la de urgencias', string $usuario = 'dr-lopez', string $hospital = 'hosp-norte'): string
        => $cbResumen($e->casos['anular']->ejecutar($usuario, $hospital, $id, $motivo));
    $ramas = [];
    [$e, $id] = $preparar();
    $ramas['R1 sin permiso (farmacia)'] = [$anular($e, $id, usuario: 'farm-norte'), 'DENEGADO PERMISO_DENEGADO'];
    $ramas['R2 de otro hospital = inexistente'] = [$anular($e, $id, usuario: 'dr-sur', hospital: 'hosp-sur'), 'NO_ENCONTRADO NO_ENCONTRADO'];
    $ramas['R3 motivo corto'] = [$anular($e, $id, 'corto'), 'RECHAZADO MOTIVO_REQUERIDO'];
    $ramas['R4 anulada'] = [$anular($e, $id), 'OK ANULADA'];
    $ramas['R5 ya anulada (la política, antes del motivo)'] = [$anular($e, $id, 'x'), 'CONFLICTO ESTADO_NO_PERMITE_ANULAR'];

    // R6: carrera. La política vio la prescripción emitida, pero otra transacción la anuló antes del INSERT.
    $p = FabricaAdaptadores::memoria(Escenario::datos(), Escenario::config());
    $casos = construirCasosDeUso(['repositorio' => new class ($p['repositorio']) implements RepositorioPrescripciones {
        public function __construct(private readonly RepositorioPrescripciones $interno)
        {
        }

        public function porClave(string $hospital, string $clave): ?Prescripcion
        {
            return $this->interno->porClave($hospital, $clave);
        }

        public function porId(string $hospital, int $id): ?Prescripcion
        {
            $x = $this->interno->porId($hospital, $id);

            return $x === null ? null : new Prescripcion($x->id, $x->hospital, $x->paciente, $x->prescriptor, $x->clave, $x->huella, $x->items,
                $x->verificacion, $x->alergenosEvaluados, $x->justificacion, $x->confirmaSinAlergias, $x->emitidaEn);  // sin su anulación: lectura vieja
        }

        public function guardar(Prescripcion $prescripcion): Prescripcion
        {
            return $this->interno->guardar($prescripcion);
        }

        public function anular(Anulacion $anulacion): void
        {
            $this->interno->anular($anulacion);
        }
    }] + $p);
    $id6 = $casos['emitir']->ejecutar(Escenario::solicitud('P-005', [Escenario::item('PARA500')]))->datos['id'];
    $casos['anular']->ejecutar('dr-lopez', 'hosp-norte', $id6, 'Primera anulación, la que gana');
    $ramas['R6 carrera: YaAnulada al insertar'] = [$cbResumen($casos['anular']->ejecutar('dr-lopez', 'hosp-norte', $id6, 'Segunda anulación, la que pierde')), 'CONFLICTO ESTADO_NO_PERMITE_ANULAR'];

    [$e, $id] = $preparar();
    $e->repositorio->fallar = true;
    $ramas['R7 la base no responde'] = [$anular($e, $id), 'NO_DISPONIBLE ERROR_PERSISTENCIA'];
    [$e, $id] = $preparar();
    $e->auditoria->fallar = true;
    $ramas['R8 la traza falla (todo o nada)'] = [$anular($e, $id), 'NO_DISPONIBLE ERROR_PERSISTENCIA'];
    $e->auditoria->fallar = false;
    afirmar($e->repositorio->todas()[0]->estado === 'EMITIDA', 'R8: sin traza no queda anulada');

    $mal = array_filter($ramas, static fn (array $c): bool => $c[0] !== $c[1]);
    igual([], array_map(static fn (array $c): string => "obtenido {$c[0]}, esperado {$c[1]}", $mal), 'ramas con otra salida');
});
