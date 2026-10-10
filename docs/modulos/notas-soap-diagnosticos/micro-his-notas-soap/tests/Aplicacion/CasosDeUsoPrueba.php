<?php

declare(strict_types=1);

use NotasSoap\Aplicacion\Comandos\ConsultarNotaComando;
use NotasSoap\Aplicacion\Comandos\FirmarNotaComando;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Excepciones\NotaIncompleta;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Pruebas\Dobles\Escenario;

prueba('APP-01', 'Camino feliz: guarda la nota, su diagnóstico y el evento de auditoría (CU-01, CU-02, CA-01)', function (): void {
    $e = new Escenario();
    $r = $e->registrar->ejecutar(Escenario::comando());

    igual(['id-001'], array_keys($e->notas->notas), 'nota guardada');
    igual('draft', $r->estado, 'estado');
    igual(1, $r->version, 'versión');
    igual('dra-mendez', $r->autorId, 'autor');
    $dx = $e->diagnosticos->deNota('id-001');
    igual(1, count($dx), 'un diagnóstico ligado a la nota');
    igual('J18.9', $dx[0]->codigo, 'código');
    igual([['evento' => 'NOTA_REGISTRADA', 'datos' => ['nota_id' => 'id-001', 'autor_id' => 'dra-mendez']]], $e->auditoria->eventos, 'auditoría');
});

prueba('APP-02', 'Médico sin asignación al episodio: 403 y no se guarda nada (R-02, CA-04)', function (): void {
    $e = new Escenario();
    lanza(AccesoDenegado::class, fn () => $e->registrar->ejecutar(Escenario::comando(['usuario' => 'dr-lopez'])), 'Dr. López');
    igual([], $e->notas->notas, 'sin notas');
});

prueba('APP-03', 'Enfermería no puede registrar notas SOAP (R-02, CA-03)', function (): void {
    $e = new Escenario();
    lanza(AccesoDenegado::class, fn () => $e->registrar->ejecutar(Escenario::comando(['usuario' => 'enf-ana', 'roles' => ['enfermeria']])), 'enfermera');
});

prueba('APP-04', 'Token de un hospital usado contra otro hospital: 403 (R-01, CA-05)', function (): void {
    $e = new Escenario();
    lanza(AccesoDenegado::class, fn () => $e->registrar->ejecutar(Escenario::comando(['hospital' => 'hosp-central'])), 'otro hospital');
});

prueba('APP-05', 'Expediente cerrado o episodio de otro paciente: no se registra (R-03, CA-06)', function (): void {
    $e = new Escenario();
    lanza(ExpedienteNoDisponible::class, fn () => $e->registrar->ejecutar(Escenario::comando(['expediente' => 'exp-rosa'])), 'expediente cerrado');
    lanza(ExpedienteNoDisponible::class, fn () => $e->registrar->ejecutar(Escenario::comando(['episodio' => 'epi-200'])), 'episodio cruzado');
    igual([], $e->notas->notas, 'sin notas');
});

prueba('APP-06', 'Regla de dominio en el caso de uso: sección vacía o CIE-10 inválido no tocan la base (R-04, R-05, CA-07, CA-08)', function (): void {
    $e = new Escenario();
    lanza(NotaIncompleta::class, fn () => $e->registrar->ejecutar(Escenario::comando(['A' => ''])), 'análisis vacío');
    lanza(DiagnosticoInvalido::class, fn () => $e->registrar->ejecutar(Escenario::comando(['codigo' => 'NEUMO'])), 'código inválido');
    igual([], $e->notas->notas, 'sin notas');
    igual([], $e->auditoria->eventos, 'sin auditoría');
});

prueba('APP-07', 'El autor firma su nota y queda evento NOTA_FIRMADA (CU-03, CA-11)', function (): void {
    $e = new Escenario();
    $id = $e->registrar->ejecutar(Escenario::comando())->notaId;
    $r = $e->firmar->ejecutar(new FirmarNotaComando('dra-mendez', 'hosp-oriente', 'hosp-oriente', $id));
    igual('signed', $r->estado, 'estado');
    igual('2026-10-12T09:30:00-06:00', $r->firmadaEn, 'fecha de firma');
    igual('NOTA_FIRMADA', $e->auditoria->eventos[1]['evento'] ?? null, 'evento');
});

prueba('APP-08', 'Otro médico no puede firmar; una nota firmada no se firma otra vez (R-07, CA-11)', function (): void {
    $e = new Escenario();
    $id = $e->registrar->ejecutar(Escenario::comando())->notaId;
    lanza(AccesoDenegado::class, fn () => $e->firmar->ejecutar(new FirmarNotaComando('dr-lopez', 'hosp-oriente', 'hosp-oriente', $id)), 'no autor');
    $e->firmar->ejecutar(new FirmarNotaComando('dra-mendez', 'hosp-oriente', 'hosp-oriente', $id));
    lanza(NotaFirmada::class, fn () => $e->firmar->ejecutar(new FirmarNotaComando('dra-mendez', 'hosp-oriente', 'hosp-oriente', $id)), 'segunda firma');
    lanza(NotaNoEncontrada::class, fn () => $e->firmar->ejecutar(new FirmarNotaComando('dra-mendez', 'hosp-oriente', 'hosp-oriente', 'no-existe')), 'nota inexistente');
});

prueba('APP-09', 'Consultar devuelve la nota con sus diagnósticos (CU-04)', function (): void {
    $e = new Escenario();
    $id = $e->registrar->ejecutar(Escenario::comando())->notaId;
    $r = $e->consultar->ejecutar(new ConsultarNotaComando('hosp-oriente', 'hosp-oriente', $id))->comoArreglo();
    igual($id, $r['clinical_note_id'], 'id');
    igual('J18.9', $r['diagnoses'][0]['code'] ?? null, 'diagnóstico');
});
