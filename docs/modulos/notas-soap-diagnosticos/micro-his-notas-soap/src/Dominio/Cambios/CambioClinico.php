<?php

declare(strict_types=1);

namespace NotasSoap\Dominio\Cambios;

use NotasSoap\Dominio\Eventos\EntradaBitacora;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;

/**
 * Semana 7: un cambio en el expediente clínico que se guarda junto con su evento de bitácora (R-06).
 * Dice QUÉ se escribe y QUÉ evento produce; el CÓMO (transacción, orden, fallas) es de RegistradorClinico.
 * Un cambio nuevo (por ejemplo, anular una nota) sería una clase más, sin tocar el registrador (OCP).
 */
interface CambioClinico
{
    public function aplicarEn(RepositorioNotas $notas, RepositorioDiagnosticos $diagnosticos): void;

    public function entrada(): EntradaBitacora;
}
