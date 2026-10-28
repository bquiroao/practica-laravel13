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
                $informe = $this->recalcular($comando->hospitalVerificado, $comando->ancla);
                $this->cadena->auditarLectura(null, $comando->actor, 'INTEGRIDAD_VERIFICADA', ['hospital' => $comando->hospitalVerificado] + $informe);

                return Resultado::ok($informe);
            });
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }

    /**
     * @param array{secuencia: int, sello: string}|null $ancla
     * @return array<string, mixed>
     */
    private function recalcular(?string $hospital, ?array $ancla): array
    {
        $anterior = Sello::GENESIS;
        $cadena = $this->bitacora->cadena($hospital);
        foreach ($cadena as $i => $m) {
            if ($m->secuencia !== $i + 1 || !hash_equals(Sello::calcular($anterior, $m->secuencia, $m), $m->sello)) {
                return ['estado' => 'ALTERADA', 'primera_secuencia' => $i + 1, 'verificados' => $i];
            }
            $anterior = $m->sello;
        }
        // Semana 5: lo que la cadena sola no puede ver. Si faltan las últimas filas, la cadena que queda
        // es coherente consigo misma; solo un ancla publicada antes delata el truncamiento.
        if ($ancla !== null && $ancla['secuencia'] > 0) {
            if (count($cadena) < $ancla['secuencia']) {
                return ['estado' => 'TRUNCADA', 'esperada_hasta' => $ancla['secuencia'], 'verificados' => count($cadena)];
            }
            if (!hash_equals($cadena[$ancla['secuencia'] - 1]->sello, $ancla['sello'])) {
                return ['estado' => 'ALTERADA', 'primera_secuencia' => $ancla['secuencia'], 'verificados' => count($cadena)];
            }
        }

        return ['estado' => 'INTEGRA', 'verificados' => count($cadena)] + ($ancla === null ? [] : ['ancla_confirmada' => $ancla['secuencia']]);
    }
}
