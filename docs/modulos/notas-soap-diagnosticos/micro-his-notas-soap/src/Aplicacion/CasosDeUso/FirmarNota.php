<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\FirmarNotaComando;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Aplicacion\Servicios\RegistradorClinico;
use NotasSoap\Dominio\Cambios\FirmaDeNota;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/**
 * CU-03: solo el autor firma; una nota firmada no cambia (la regla vive en NotaSoap).
 * Semana 7: la firma y su evento los guarda RegistradorClinico, igual que el registro y la corrección.
 */
final class FirmarNota
{
    public function __construct(
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
        private readonly RegistradorClinico $registrador,
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
        $this->registrador->guardar(new FirmaDeNota($firmada));

        return NotaResultado::desde($firmada, $this->diagnosticos->deNota($firmada->id));
    }
}
