<?php

declare(strict_types=1);

use NotasSoap\Contratos\AutenticadorToken;
use NotasSoap\Contratos\RegistroAuditoria;
use NotasSoap\Contratos\Reloj;
use NotasSoap\Contratos\RepositorioDiagnosticos;
use NotasSoap\Contratos\RepositorioExpedientes;
use NotasSoap\Contratos\RepositorioNotas;
use NotasSoap\Contratos\Transaccion;
use NotasSoap\Contratos\VerificadorAcceso;
use NotasSoap\Presentacion\NotaSoapController;
use NotasSoap\Servicios\FirmarNota;
use NotasSoap\Servicios\RegistrarNotaConDiagnostico;

/**
 * Raíz de composición: el único lugar que conecta las piezas.
 * En el HIS real aquí se pasarían los adaptadores de PostgreSQL y JWT en vez de los de memoria.
 */
function componerController(
    AutenticadorToken $autenticador,
    VerificadorAcceso $acceso,
    RepositorioExpedientes $expedientes,
    RepositorioNotas $notas,
    RepositorioDiagnosticos $diagnosticos,
    Transaccion $transaccion,
    RegistroAuditoria $auditoria,
    Reloj $reloj,
): NotaSoapController {
    return new NotaSoapController(
        $autenticador,
        new RegistrarNotaConDiagnostico($acceso, $expedientes, $notas, $diagnosticos, $transaccion, $auditoria),
        new FirmarNota($notas, $reloj, $auditoria),
    );
}
