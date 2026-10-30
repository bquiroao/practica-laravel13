<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, DirectorioDestinatarios};

/** CU-02 + CU-03 + CU-09: resuelve destinatarios del nivel actual, entrega y sube de nivel si hace falta. */
final class NotificarNivel
{
    public function __construct(
        private DirectorioDestinatarios $directorio,
        private DespachadorNotificaciones $despachador,
    ) {}

    public function ejecutar(Alerta $alerta, \DateTimeImmutable $momento): void
    {
        while (true) {
            $usuarios = $this->directorio->destinatarios($alerta->tenant, $alerta->servicio, $alerta->nivel());
            if ($usuarios === []) {
                $alerta->registrarSinDestinatarios($momento); // RN-03
                if ($alerta->nivel() < Alerta::NIVEL_MAXIMO) {
                    $alerta->subirNivel('sin destinatarios', $momento);
                    continue;
                }
                $alerta->agotarEscalamiento($momento); // RN-05: no queda a quién avisar
                return;
            }
            $resultado = $this->despachador->despachar($alerta->resumen(), $usuarios);
            if ($resultado['entregados'] !== []) {
                $alerta->registrarEnvio($resultado['entregados'], $momento);
            }
            foreach ($resultado['fallidos'] as $usuario) {
                $alerta->registrarEntregaFallida($usuario, $this->despachador->maxIntentos(), $momento); // RN-08
            }
            if ($resultado['fallidos'] === []) {
                return;
            }
            // RN-08: alguien del nivel no recibió tras los reintentos → se escala de inmediato.
            if ($alerta->nivel() < Alerta::NIVEL_MAXIMO) {
                $alerta->subirNivel('entrega fallida', $momento);
                continue;
            }
            if ($resultado['entregados'] === []) {
                $alerta->agotarEscalamiento($momento); // nivel máximo y nadie recibió
            }
            return;
        }
    }
}
