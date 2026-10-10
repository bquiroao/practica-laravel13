<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Puertos\Transaccion;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\RegistroAuditoria;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioExpedientes;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

/** CU-01 + CU-02: registrar la nota SOAP con su diagnóstico. */
final class RegistrarNotaConDiagnostico
{
    public function __construct(
        private readonly VerificadorAcceso $acceso,
        private readonly RepositorioExpedientes $expedientes,
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly GeneradorId $ids,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(RegistrarNotaComando $c): NotaResultado
    {
        $usuario = new UsuarioClinico($c->usuarioId, $c->hospitalUsuario, $c->roles);

        // CU-05 / R-01 / R-02
        if ($usuario->hospitalId !== $c->hospitalSolicitud || !$this->acceso->puedeEscribir($usuario, $c->episodioId)) {
            throw new AccesoDenegado('No tiene permiso para escribir en este expediente.');
        }
        // R-03
        if (!$this->expedientes->estaDisponible($c->expedienteId, $c->episodioId)) {
            throw new ExpedienteNoDisponible('El expediente no existe, está cerrado o no corresponde al episodio.');
        }

        // R-04 y R-05: el dominio valida todo ANTES de guardar.
        $nota = NotaSoap::registrar(
            $this->ids->nuevo(),
            $c->expedienteId,
            $c->episodioId,
            $usuario->id,
            new SeccionesSoap($c->subjetivo, $c->objetivo, $c->analisis, $c->plan),
            $this->reloj->ahora(),
        );
        $diagnostico = Diagnostico::registrar($this->ids->nuevo(), $nota, $c->codigoCie10, $c->descripcionDiagnostico, $c->tipoDiagnostico);

        // R-06: nota, diagnóstico y evento juntos o nada.
        $this->transaccion->ejecutar(function () use ($nota, $diagnostico, $usuario): void {
            $this->notas->guardar($nota);
            $this->diagnosticos->guardar($diagnostico);
            $this->auditoria->registrar('NOTA_REGISTRADA', ['nota_id' => $nota->id, 'autor_id' => $usuario->id]);
        });

        return NotaResultado::desde($nota, [$diagnostico]);
    }
}
