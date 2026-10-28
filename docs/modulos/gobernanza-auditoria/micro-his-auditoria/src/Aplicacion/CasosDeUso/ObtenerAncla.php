<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoAncla;
use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Puertos\Bitacora;
use Auditoria\Dominio\Puertos\Reloj;

/**
 * Semana 5 — La cabeza de una cadena (última secuencia y su sello) para publicarla FUERA de la base
 * (otro sistema, correo firmado, papel). El sello encadenado no detecta que se borre la última fila;
 * comparar con un ancla publicada sí (ver VerificarIntegridad). No revela contenido: solo un hash.
 */
final class ObtenerAncla
{
    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly PoliticaAcceso $acceso,
        private readonly Reloj $reloj,
    ) {
    }

    public function ejecutar(ComandoAncla $comando): Resultado
    {
        if (!$this->acceso->permite($comando->actor, PoliticaAcceso::RED, VerificarIntegridad::PERMISO)) {
            return Resultado::accesoDenegado();
        }

        try {
            $ultimo = $this->bitacora->ultimo($comando->hospital);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }

        return Resultado::ok(['hospital' => $comando->hospital, 'secuencia' => $ultimo?->secuencia ?? 0, 'sello' => $ultimo?->sello,
            'emitida_en' => $this->reloj->ahora()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')]);
    }
}
