<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Cambios;

use NotasSoap\Dominio\Eventos\EntradaBitacora;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/** La firma de una nota (CU-03). La nota ya viene firmada por NotaSoap::firmar(), que aplica R-07. */
final class FirmaDeNota implements CambioClinico
{
    public function __construct(public readonly NotaSoap $firmada)
    {
    }

    public function aplicarEn(RepositorioNotas $notas, RepositorioDiagnosticos $diagnosticos): void
    {
        $notas->registrarFirma($this->firmada);
    }

    public function entrada(): EntradaBitacora
    {
        return EntradaBitacora::notaFirmada($this->firmada);
    }
}
