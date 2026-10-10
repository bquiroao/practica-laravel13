<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\FirmarNotaComando;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Puertos\Transaccion;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Dominio\Puertos\RegistroAuditoria;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/** CU-03: solo el autor firma; una nota firmada no cambia (la regla vive en NotaSoap). */
final class FirmarNota
{
    public function __construct(
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(FirmarNotaComando $c): NotaResultado
    {
        if ($c->hospitalUsuario !== $c->hospitalSolicitud) {
            throw new AccesoDenegado('El token no corresponde a este hospital.');
        }
        $nota = $this->notas->buscar($c->notaId) ?? throw new NotaNoEncontrada('La nota no existe.');
        if ($nota->autorId !== $c->usuarioId) {
            throw new AccesoDenegado('Solo el autor puede firmar la nota.');
        }

        $firmada = $nota->firmar($this->reloj->ahora());
        $this->transaccion->ejecutar(function () use ($firmada, $c): void {
            $this->notas->registrarFirma($firmada);
            $this->auditoria->registrar('NOTA_FIRMADA', ['nota_id' => $firmada->id, 'autor_id' => $c->usuarioId]);
        });

        return NotaResultado::desde($firmada, $this->diagnosticos->deNota($firmada->id));
    }
}
