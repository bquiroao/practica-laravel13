<?php

declare(strict_types=1);

namespace Auditoria\Servicios;

use Auditoria\Contratos\Bitacora;
use Auditoria\Contratos\PoliticaAcceso;
use Auditoria\Dominio\ErrorPersistencia;
use Auditoria\Dominio\Resultado;
use Auditoria\Dominio\Sello;

/** CU-04 (R-03): recalcula la cadena y señala la PRIMERA secuencia cuyo sello no coincide. */
final class VerificadorIntegridad
{
    public function __construct(private readonly Bitacora $bitacora, private readonly PoliticaAcceso $acceso)
    {
    }

    public function verificar(string $actor, ?string $hospital): Resultado
    {
        if (!$this->acceso->permite($actor, '*', 'auditoria.verificar')) {
            return Resultado::error('prohibido', 'ACCESO_DENEGADO');
        }

        try {
            $anterior = Sello::GENESIS;
            $cadena = $this->bitacora->cadena($hospital);
            foreach ($cadena as $i => $m) {
                if ($m->secuencia !== $i + 1 || !hash_equals(Sello::calcular($anterior, $m->secuencia, $m), $m->sello)) {
                    return Resultado::ok(['estado' => 'ALTERADA', 'primera_secuencia' => $i + 1]);
                }
                $anterior = $m->sello;
            }

            return Resultado::ok(['estado' => 'INTEGRA', 'verificados' => count($cadena)]);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
