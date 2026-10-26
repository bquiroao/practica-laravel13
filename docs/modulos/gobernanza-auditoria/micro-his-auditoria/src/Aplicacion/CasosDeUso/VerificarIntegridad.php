<?php

declare(strict_types=1);

namespace Auditoria\Aplicacion\CasosDeUso;

use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Dominio\Excepciones\ErrorPersistencia;
use Auditoria\Dominio\Modelo\Sello;
use Auditoria\Dominio\Puertos\Bitacora;

/**
 * CU-04 (R-03): recalcula la cadena de un hospital y señala la PRIMERA secuencia cuyo sello no
 * coincide. La verificación queda registrada en la cadena de red (INTEGRIDAD_VERIFICADA).
 */
final class VerificarIntegridad
{
    public const PERMISO = 'auditoria.verificar';

    public function __construct(
        private readonly Bitacora $bitacora,
        private readonly CadenaSellada $cadena,
        private readonly Transaccion $transaccion,
        private readonly PoliticaAcceso $acceso,
    ) {
    }

    public function ejecutar(ComandoVerificar $comando): Resultado
    {
        if (!$this->acceso->permite($comando->actor, PoliticaAcceso::RED, self::PERMISO)) {
            return Resultado::accesoDenegado();
        }

        try {
            return $this->transaccion->ejecutar(function () use ($comando): Resultado {
                $informe = $this->recalcular($comando->hospitalVerificado);
                $this->cadena->auditarLectura(null, $comando->actor, 'INTEGRIDAD_VERIFICADA', ['hospital' => $comando->hospitalVerificado] + $informe);

                return Resultado::ok($informe);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }

    /** @return array<string, mixed> */
    private function recalcular(?string $hospital): array
    {
        $anterior = Sello::GENESIS;
        $cadena = $this->bitacora->cadena($hospital);
        foreach ($cadena as $i => $m) {
            if ($m->secuencia !== $i + 1 || !hash_equals(Sello::calcular($anterior, $m->secuencia, $m), $m->sello)) {
                return ['estado' => 'ALTERADA', 'primera_secuencia' => $i + 1, 'verificados' => $i];
            }
            $anterior = $m->sello;
        }

        return ['estado' => 'INTEGRA', 'verificados' => count($cadena)];
    }
}
