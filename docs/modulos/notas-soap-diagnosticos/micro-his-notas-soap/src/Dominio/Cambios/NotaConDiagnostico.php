<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Cambios;

use NotasSoap\Dominio\Eventos\EntradaBitacora;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/**
 * Una nota nueva con su diagnóstico: la de un registro (CU-01 + CU-02) o la de una corrección (CU-06).
 * Nunca una nota sin diagnóstico (RNF-01): los dos se guardan en el mismo cambio.
 */
final class NotaConDiagnostico implements CambioClinico
{
    public function __construct(
        public readonly NotaSoap $nota,
        public readonly Diagnostico $diagnostico,
    ) {
    }

    public function aplicarEn(RepositorioNotas $notas, RepositorioDiagnosticos $diagnosticos): void
    {
        $notas->guardar($this->nota);
        $diagnosticos->guardar($this->diagnostico);
    }

    public function entrada(): EntradaBitacora
    {
        return $this->nota->reemplazaA === null
            ? EntradaBitacora::notaRegistrada($this->nota)
            : EntradaBitacora::notaCorregida($this->nota);
    }
}
