<?php

declare(strict_types=1);

namespace NotasSoap\Persistencia\EnMemoria;

use NotasSoap\Aplicacion\Puertos\ConjuntoAdaptadores;

/**
 * Junta todos los adaptadores en memoria ya conectados entre sí (la transacción conoce a todos).
 * Se usa en bin/demo-en-memoria.php y en las pruebas; queda público para inspeccionar lo guardado.
 */
final class AlmacenesEnMemoria
{
    public readonly RepositorioNotasEnMemoria $notas;
    public readonly RepositorioDiagnosticosEnMemoria $diagnosticos;
    public readonly RegistroAuditoriaEnMemoria $auditoria;
    public readonly BandejaSalidaEnMemoria $bandeja;
    public readonly RepositorioExpedientesEnMemoria $expedientes;
    public readonly VerificadorAccesoEnMemoria $acceso;
    public readonly RegistroIdempotenciaEnMemoria $idempotencia;
    public readonly TransaccionEnMemoria $transaccion;

    /** @param array{expedientes: array<string, array{paciente: string, abierto: bool}>, episodios: array<string, string>, asignaciones: array<string, list<string>>} $datos */
    public function __construct(array $datos)
    {
        $this->notas = new RepositorioNotasEnMemoria();
        $this->diagnosticos = new RepositorioDiagnosticosEnMemoria($this->notas);
        $this->auditoria = new RegistroAuditoriaEnMemoria();
        $this->bandeja = new BandejaSalidaEnMemoria();
        $this->expedientes = new RepositorioExpedientesEnMemoria($datos['expedientes'], $datos['episodios']);
        $this->acceso = new VerificadorAccesoEnMemoria($datos['asignaciones']);
        $this->idempotencia = new RegistroIdempotenciaEnMemoria();
        $this->transaccion = new TransaccionEnMemoria([$this->notas, $this->diagnosticos, $this->auditoria, $this->bandeja, $this->idempotencia]);
    }

    public function conjunto(): ConjuntoAdaptadores
    {
        return new ConjuntoAdaptadores($this->acceso, $this->expedientes, $this->notas, $this->diagnosticos, $this->auditoria, $this->transaccion, $this->idempotencia);
    }
}
