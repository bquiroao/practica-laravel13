<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Servicios;

use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\RepositorioExpedientes;
use NotasSoap\Dominio\Puertos\VerificadorAcceso;

/**
 * Semana 7: las reglas que debe cumplir cualquier escritura en el expediente, en un solo lugar.
 *   R-01 el token es del hospital de la solicitud · R-02 rol médico con asignación activa · R-03 expediente abierto.
 * Antes estaban copiadas en RegistrarNotaConDiagnostico y en CorregirNota.
 */
final class ReglasDeEscritura
{
    public const SIN_PERMISO = 'No tiene permiso para escribir en este expediente.';

    public function __construct(
        private readonly VerificadorAcceso $acceso,
        private readonly RepositorioExpedientes $expedientes,
    ) {
    }

    /** R-01 */
    public function exigirMismoHospital(UsuarioClinico $usuario, string $hospitalSolicitud): void
    {
        if ($usuario->hospitalId !== $hospitalSolicitud) {
            throw new AccesoDenegado('El token no corresponde a este hospital.');
        }
    }

    /** R-01 y R-02. El mensaje lo elige el caso de uso para explicar la negación en su contexto. */
    public function exigirPuedeEscribir(UsuarioClinico $usuario, string $hospitalSolicitud, string $episodioId, string $mensaje = self::SIN_PERMISO): void
    {
        if ($usuario->hospitalId !== $hospitalSolicitud || !$this->acceso->puedeEscribir($usuario, $episodioId)) {
            throw new AccesoDenegado($mensaje);
        }
    }

    /** R-03 */
    public function exigirExpedienteDisponible(string $expedienteId, string $episodioId, string $mensaje = 'El expediente no existe, está cerrado o no corresponde al episodio.'): void
    {
        if (!$this->expedientes->estaDisponible($expedienteId, $episodioId)) {
            throw new ExpedienteNoDisponible($mensaje);
        }
    }
}
