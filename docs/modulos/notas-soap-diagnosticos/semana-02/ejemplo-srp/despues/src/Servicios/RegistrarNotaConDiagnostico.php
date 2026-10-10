<?php

declare(strict_types=1);

namespace NotasSoap\Servicios;

use NotasSoap\Contratos\RegistroAuditoria;
use NotasSoap\Contratos\RepositorioDiagnosticos;
use NotasSoap\Contratos\RepositorioExpedientes;
use NotasSoap\Contratos\RepositorioNotas;
use NotasSoap\Contratos\Transaccion;
use NotasSoap\Contratos\VerificadorAcceso;
use NotasSoap\Dominio\Diagnostico;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\NotaSoap;
use NotasSoap\Dominio\SeccionesSoap;
use NotasSoap\Dominio\SolicitudRegistroNota;
use NotasSoap\Dominio\UsuarioAutenticado;

/**
 * Coordina el caso de uso CU-01. Única razón de cambio: el orden del proceso.
 * No valida reglas de la nota ni del diagnóstico (eso es del dominio),
 * no sabe de HTTP (controlador) ni de SQL (repositorios).
 */
final class RegistrarNotaConDiagnostico
{
    public function __construct(
        private readonly VerificadorAcceso $acceso,
        private readonly RepositorioExpedientes $expedientes,
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
        private readonly Transaccion $transaccion,
        private readonly RegistroAuditoria $auditoria,
    ) {
    }

    /** @return array{nota: NotaSoap, diagnostico: Diagnostico} */
    public function ejecutar(UsuarioAutenticado $usuario, SolicitudRegistroNota $solicitud): array
    {
        if (!$this->acceso->puedeEscribir($usuario, $solicitud->tenantId, $solicitud->episodioId)) {
            throw new AccesoDenegado('No tiene permiso para escribir en este expediente.');
        }
        if (!$this->expedientes->estaDisponible($solicitud->expedienteId, $solicitud->episodioId)) {
            throw new ExpedienteNoDisponible('El expediente no existe, está cerrado o no corresponde al episodio.');
        }

        // Se valida TODO antes de guardar nada (corrige la nota huérfana del diseño anterior).
        $nota = NotaSoap::registrar(
            $solicitud->expedienteId,
            $solicitud->episodioId,
            $usuario->id,
            new SeccionesSoap($solicitud->subjetivo, $solicitud->objetivo, $solicitud->analisis, $solicitud->plan),
        );
        $diagnostico = Diagnostico::registrar($solicitud->codigoCie10, $solicitud->descripcionDiagnostico, $solicitud->tipoDiagnostico);

        try {
            $resultado = $this->transaccion->ejecutar(function () use ($nota, $diagnostico): array {
                $notaGuardada = $this->notas->guardar($nota);
                $diagnosticoGuardado = $this->diagnosticos->guardar($diagnostico->paraNota((string) $notaGuardada->id));

                return ['nota' => $notaGuardada, 'diagnostico' => $diagnosticoGuardado];
            });
        } catch (\Throwable $e) {
            throw new ErrorPersistencia('No se pudo guardar la nota.', 0, $e);
        }

        $this->auditoria->registrar('NOTA_REGISTRADA', ['nota_id' => (string) $resultado['nota']->id, 'autor_id' => $usuario->id]);

        return $resultado;
    }
}
