<?php
declare(strict_types=1);

// DESPUÉS: la política de alertas es una pieza del dominio, se inyecta y se prueba sin base de datos.
namespace SignosVitales\Refactor;

use SignosVitales\Dominio\{Clasificacion, Toma};

enum EstadoEnvio: string { case NoAplica = 'no_aplica'; case PendienteEnvio = 'pendiente_envio'; }

final class Alerta {
    public function __construct(public readonly string $signo, public readonly Clasificacion $nivel, public readonly float $valor, public readonly EstadoEnvio $envio) {}
}

interface PoliticaDeAlertas {
    /** @return Alerta[] una por cada medición que la política considere alerta, con su estado de envío a M21 */
    public function alertasDe(Toma $toma): array;
}

/** Política vigente: alerta por cada medición anormal o crítica; solo las críticas se envían a M21. */
class PoliticaEstandar implements PoliticaDeAlertas {
    public function alertasDe(Toma $toma): array {
        $alertas = [];
        foreach ($toma->mediciones as $m) {
            $nivel = $toma->clasificaciones[$m->tipo->value];
            if ($nivel === Clasificacion::Anormal || $nivel === Clasificacion::Critica) { $alertas[] = new Alerta($m->tipo->value, $nivel, $m->valor, $this->envio($nivel)); }
        }
        return $alertas;
    }

    protected function envio(Clasificacion $nivel): EstadoEnvio { return $nivel === Clasificacion::Critica ? EstadoEnvio::PendienteEnvio : EstadoEnvio::NoAplica; }
}

/** Cambio solicitado de ejemplo: M21 también recibe las anormales. */
final class PoliticaEnviaAnormales extends PoliticaEstandar {
    protected function envio(Clasificacion $nivel): EstadoEnvio { return EstadoEnvio::PendienteEnvio; }
}
