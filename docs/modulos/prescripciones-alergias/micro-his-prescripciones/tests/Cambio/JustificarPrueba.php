<?php

declare(strict_types=1);

/*
 * Semana 12 · Cambio solicitado: «la verificación previa dice si el usuario PUEDE justificar la advertencia» (RF-19, RF-20).
 * Cierra la brecha M-07 de la semana 8: hasta ahora la interfaz lo adivinaba con su copia de los permisos y, si se
 * equivocaba, el médico escribía la justificación en vano y recibía 409. Escritas ANTES del cambio
 * (semana-12/evidencia/cambio-1-antes-rojo.txt); entran por HTTP.
 */

use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Persistencia\FabricaAdaptadores;
use Prescripciones\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;

$previa = static fn ($k, string $paciente, string $medicamento, string $token = 'token-dr-lopez')
    => $k->manejar(Escenario::http('POST', '/api/v1/prescripciones/verificaciones', ['paciente' => $paciente, 'items' => [Escenario::item($medicamento)]], $token));

prueba('JUS-01', 'Previa con ADVERTENCIA: puede_justificar = true con el permiso override (dr-lopez) y false sin él (dr-ruiz); lo demás no cambia', function () use ($previa): void {
    $k = (new Escenario())->kernel();
    $lopez = $previa($k, 'P-002', 'CEFA500');
    $ruiz = $previa($k, 'P-002', 'CEFA500', 'token-dr-ruiz');
    igual([200, 'ADVERTENCIA', true], [$lopez->estado, $lopez->cuerpo['codigo'] ?? null, $lopez->cuerpo['puede_justificar'] ?? null], 'dr-lopez');
    igual([200, 'ADVERTENCIA', false], [$ruiz->estado, $ruiz->cuerpo['codigo'] ?? null, $ruiz->cuerpo['puede_justificar'] ?? null], 'dr-ruiz');
    igual($lopez->cuerpo['hallazgos'] ?? null, $ruiz->cuerpo['hallazgos'] ?? null, 'los mismos hallazgos para los dos');
});

prueba('JUS-02', 'Sin advertencia (SIN_HALLAZGOS, BLOQUEO, CONFIRMACION_REQUERIDA, NO DISPONIBLE) no hay puede_justificar y NO se pregunta a RBAC por override', function () use ($previa): void {
    $espia = new class (Escenario::config()['permisos']) implements PoliticaAcceso {
        public int $override = 0;

        /** @param array<string, list<string>> $permisos */
        public function __construct(private readonly array $permisos)
        {
        }

        public function permite(string $usuario, string $permiso, string $hospital): bool
        {
            $this->override += $permiso === 'prescripcion.override' ? 1 : 0;

            return in_array($permiso, $this->permisos["$usuario|$hospital"] ?? [], true);
        }
    };
    $fuente = new FuenteConmutable(Escenario::fuenteContada());
    $p = FabricaAdaptadores::memoria(Escenario::datos(), Escenario::config());
    $k = construirKernel(construirCasosDeUso(['acceso' => $espia, 'alergias' => $fuente] + $p), new AutenticadorTokenDemo(Escenario::config()['tokens_demo']));
    $codigos = [];
    foreach ([['P-005', 'PARA500'], ['P-001', 'AMOX500'], ['P-003', 'IBU400']] as [$paciente, $medicamento]) {
        $r = $previa($k, $paciente, $medicamento);
        $codigos[] = $r->cuerpo['codigo'] ?? null;
        afirmar(!array_key_exists('puede_justificar', $r->cuerpo), "$paciente: no debía traer puede_justificar");
    }
    $fuente->caida = true;
    $r = $previa($k, 'P-002', 'CEFA500');
    $codigos[] = $r->cuerpo['codigo'] ?? null;
    afirmar(!array_key_exists('puede_justificar', $r->cuerpo), 'Expediente caído: no debía traer puede_justificar');
    igual(['SIN_HALLAZGOS', 'BLOQUEO', 'CONFIRMACION_REQUERIDA', 'VERIFICACION_NO_DISPONIBLE'], $codigos, 'códigos');
    igual(0, $espia->override, 'consultas a RBAC por override');
});

prueba('JUS-03', 'La promesa se cumple: con una justificación válida, la emisión da 201 si la previa dijo true y 409 si dijo false', function () use ($previa): void {
    $k = (new Escenario())->kernel();
    foreach (['token-dr-lopez' => 201, 'token-dr-ruiz' => 409] as $token => $esperado) {
        $puede = $previa($k, 'P-002', 'CEFA500', $token)->cuerpo['puede_justificar'] ?? null;
        $r = $k->manejar(Escenario::http('POST', '/api/v1/prescripciones', ['paciente' => 'P-002', 'items' => [Escenario::item('CEFA500')],
            'justificacion' => 'Sin alternativa: cultivo sensible solo a cefalosporinas'], $token, cabeceras: ['idempotency-key' => "jus-$token"]));
        igual([$esperado === 201, $esperado], [$puede, $r->estado], $token);
    }
});

prueba('JUS-04', 'Contrato = código: el esquema Verificacion documenta puede_justificar (boolean, solo con ADVERTENCIA) y la versión es ≥ 1.2.0', function (): void {
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/api/openapi.yaml');
    preg_match('/^  version: (\d+)\.(\d+)\.(\d+)$/m', $yaml, $v);
    afirmar(isset($v[1]) && [(int) $v[1], (int) $v[2]] >= [1, 2], 'versión ' . ($v[0] ?? '?') . ' < 1.2.0');
    preg_match('/^    Verificacion:\n((?:      .*\n|        .*\n|          .*\n)+)/m', $yaml, $m);
    afirmar(preg_match('/^        puede_justificar: \{type: boolean, description: [^}]*ADVERTENCIA[^}]*\}$/m', $m[1] ?? '') === 1,
        'Verificacion no documenta puede_justificar (boolean, con ADVERTENCIA en la descripción)');
});
