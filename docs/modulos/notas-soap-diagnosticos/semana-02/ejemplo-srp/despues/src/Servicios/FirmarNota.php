<?php

declare(strict_types=1);

namespace NotasSoap\Servicios;

use NotasSoap\Contratos\RegistroAuditoria;
use NotasSoap\Contratos\Reloj;
use NotasSoap\Contratos\RepositorioNotas;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\NotaSoap;
use NotasSoap\Dominio\UsuarioAutenticado;

/** CU-03. Solo el autor firma; la regla de «una vez firmada no cambia» vive en NotaSoap. */
final class FirmarNota
{
    public function __construct(
        private readonly RepositorioNotas $notas,
        private readonly Reloj $reloj,
        private readonly RegistroAuditoria $auditoria,
    ) {
    }

    public function ejecutar(UsuarioAutenticado $usuario, string $notaId): NotaSoap
    {
        $nota = $this->notas->buscar($notaId) ?? throw new ExpedienteNoDisponible('La nota no existe.');
        if ($nota->autorId !== $usuario->id) {
            throw new AccesoDenegado('Solo el autor puede firmar la nota.');
        }

        $firmada = $nota->firmar($this->reloj->ahora());
        $this->notas->actualizar($firmada);
        $this->auditoria->registrar('NOTA_FIRMADA', ['nota_id' => $notaId, 'autor_id' => $usuario->id]);

        return $firmada;
    }
}
