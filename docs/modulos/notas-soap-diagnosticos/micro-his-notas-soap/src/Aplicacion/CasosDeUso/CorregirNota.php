<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\CorregirNotaComando;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Puertos\Transaccion;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Dominio\Excepciones\NotaYaCorregida;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\RegistroAuditoria;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioExpedientes;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

/**
 * CU-06 (semana 6): corregir una nota SOAP firmada.
 * La nota firmada no se modifica (R-07) ni se borra (R-08): se guarda una VERSIÓN NUEVA con su
 * diagnóstico, que apunta a la anterior (supersedes_id) y explica el motivo. Usa las mismas reglas
 * y los mismos puertos que el registro; no cambia RegistrarNotaConDiagnostico ni FirmarNota (SRP).
 */
final class CorregirNota
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

    public function ejecutar(CorregirNotaComando $c): NotaResultado
    {
        $usuario = new UsuarioClinico($c->usuarioId, $c->hospitalUsuario, $c->roles);
        if ($usuario->hospitalId !== $c->hospitalSolicitud) {                                   // R-01
            throw new AccesoDenegado('El token no corresponde a este hospital.');
        }
        $anterior = $this->notas->buscar($c->notaId) ?? throw new NotaNoEncontrada('La nota no existe.');
        if ($anterior->autorId !== $usuario->id || !$this->acceso->puedeEscribir($usuario, $anterior->episodioId)) {   // R-02
            throw new AccesoDenegado('Solo el autor, todavía asignado al episodio, puede corregir la nota.');
        }
        if ($this->notas->correccionDe($anterior->id) !== null) {
            throw new NotaYaCorregida('Esta versión ya tiene una corrección: corrija la versión más reciente.');
        }
        if (!$this->expedientes->estaDisponible($anterior->expedienteId, $anterior->episodioId)) {   // R-03
            throw new ExpedienteNoDisponible('El expediente está cerrado o ya no corresponde al episodio.');
        }

        // R-04, R-05 y las reglas nuevas (firmada, motivo): el dominio valida todo ANTES de guardar.
        $nueva = NotaSoap::corregir($anterior, $this->ids->nuevo(),
            new SeccionesSoap($c->subjetivo, $c->objetivo, $c->analisis, $c->plan), $c->motivo, $this->reloj->ahora());
        $diagnostico = Diagnostico::registrar($this->ids->nuevo(), $nueva, $c->codigoCie10, $c->descripcionDiagnostico, $c->tipoDiagnostico);

        try {
            // R-06: versión nueva, diagnóstico y evento juntos o nada.
            $this->transaccion->ejecutar(function () use ($nueva, $diagnostico, $usuario): void {
                $this->notas->guardar($nueva);
                $this->diagnosticos->guardar($diagnostico);
                $this->auditoria->registrar('NOTA_CORREGIDA', ['nota_id' => $nueva->id, 'reemplaza_a' => $nueva->reemplazaA, 'autor_id' => $usuario->id]);
            });
        } catch (ErrorPersistencia $e) {
            // Dos correcciones de la misma versión al mismo tiempo: la base (UNIQUE supersedes_id) deja pasar una sola.
            if (str_starts_with((string) $e->sqlState, '23') && $this->notas->correccionDe($anterior->id) !== null) {
                throw new NotaYaCorregida('Esta versión ya tiene una corrección: corrija la versión más reciente.');
            }
            throw $e;
        }

        return NotaResultado::desde($nueva, [$diagnostico]);
    }
}
