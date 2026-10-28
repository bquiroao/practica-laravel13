<?php

declare(strict_types=1);

// Semana 15 — Caja blanca: casos diseñados leyendo el código del flujo de registro (RegistrarNotaConDiagnostico, ReglasDeEscritura).

use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Servicios\ReglasDeEscritura;
use NotasSoap\Persistencia\EnMemoria\{RepositorioExpedientesEnMemoria, VerificadorAccesoEnMemoria};
use NotasSoap\Pruebas\Dobles\{Escenario, RepositorioDiagnosticosQueFalla};

prueba('CB-01', 'Caminos básicos de RegistrarNotaConDiagnostico::ejecutar(): V(G) = 8 decisiones + 1 = 9 caminos, cada uno con su salida y sin efectos de más', function (): void {
    $clave = ['idempotency-key' => 'clave-cb-0001'];
    $caminos = [ // [camino, escenario, petición previa, petición, estado, código, notas que quedan]
        ['C1 D1 sin acceso', null, null, Escenario::peticion([], ['authorization' => 'Bearer token-lopez']), 403, 'acceso_denegado', 0],
        ['C2 D5 expediente cerrado', null, null, Escenario::peticion(['medical_record_id' => 'exp-rosa', 'care_episode_id' => 'epi-200']), 409, 'expediente_no_disponible', 0],
        ['C3 D6 sección vacía', null, null, Escenario::peticion(['plan' => ' ']), 422, 'nota_incompleta', 0],
        ['C4 D7 CIE-10 inválido', null, null, Escenario::peticion(['diagnostico' => ['code' => 'X1', 'description' => 'x', 'type' => 'primary']]), 422, 'diagnostico_invalido', 0],
        ['C5 D8 falla al guardar', 'falla', null, Escenario::peticion(), 500, 'error_persistencia', 0],
        ['C6 sin clave, todo bien', null, null, Escenario::peticion(), 201, null, 1],
        ['C7 D2 con clave nueva', null, null, Escenario::peticion([], $clave), 201, null, 1],
        ['C8 D3-D4 clave repetida, misma huella', null, Escenario::peticion([], $clave), Escenario::peticion([], $clave), 201, null, 1],
        ['C9 D4 clave repetida, otra huella', null, Escenario::peticion([], $clave), Escenario::peticion(['plan' => 'Otro plan'], $clave), 422, 'idempotency_key_reutilizada', 1],
    ];
    foreach ($caminos as [$camino, $tipo, $previa, $peticion, $estado, $codigo, $notas]) {
        $e = $tipo === 'falla' ? new Escenario(diagnosticos: new RepositorioDiagnosticosQueFalla()) : new Escenario();
        $previa !== null && $e->kernel->manejar($previa);
        $r = $e->kernel->manejar($peticion);
        igual([$estado, $codigo, $notas], [$r->estado, $r->cuerpo['error']['code'] ?? null, count($e->notas->todas())], $camino);
    }
});

prueba('CB-02', 'MC/DC de ReglasDeEscritura::exigirPuedeEscribir(): deniega si ¬A ∨ ¬(R ∧ S); cada condición cambia sola la decisión', function (): void {
    $reglas = new ReglasDeEscritura(new VerificadorAccesoEnMemoria(['epi-100' => ['dra-mendez', 'enf-ana']]), new RepositorioExpedientesEnMemoria([], []));
    $casos = [ // A = hospital del token igual al de la solicitud · R = rol médico · S = asignado al episodio
        'A R S → permite' => [new UsuarioClinico('dra-mendez', 'hosp-oriente', ['medico']), true],
        '¬A → deniega (efecto de A)' => [new UsuarioClinico('dra-mendez', 'hosp-central', ['medico']), false],
        '¬R → deniega (efecto de R)' => [new UsuarioClinico('enf-ana', 'hosp-oriente', ['enfermeria']), false],
        '¬S → deniega (efecto de S)' => [new UsuarioClinico('dr-lopez', 'hosp-oriente', ['medico']), false],
    ];
    foreach ($casos as $caso => [$usuario, $permite]) {
        $paso = true;
        try {
            $reglas->exigirPuedeEscribir($usuario, 'hosp-oriente', 'epi-100');
        } catch (AccesoDenegado) {
            $paso = false;
        }
        igual($permite, $paso, $caso);
    }
});
