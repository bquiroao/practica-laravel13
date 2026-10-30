<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, AlertaRepository, PoliticaEscalamiento, Reloj};

/** CU-05: lo dispara el planificador; el reloj inyectado decide qué venció (RN-04, RN-05). */
final class EscalarAlertasVencidas
{
    public function __construct(
        private AlertaRepository $repositorio,
        private PoliticaEscalamiento $politica,
        private NotificarNivel $notificar,
        private Reloj $reloj,
    ) {}

    /** @return array{escaladas: int, agotadas: int} */
    public function ejecutar(): array
    {
        $ahora = $this->reloj->ahora();
        $cuenta = ['escaladas' => 0, 'agotadas' => 0];
        foreach ($this->repositorio->pendientesDeAcuse() as $alerta) {
            if (!$this->politica->vencida($alerta, $ahora)) {
                continue;
            }
            if ($alerta->nivel() < Alerta::NIVEL_MAXIMO) {
                $alerta->subirNivel('plazo vencido', $ahora);
                $this->notificar->ejecutar($alerta, $ahora);
                $cuenta['escaladas']++;
            } else {
                $alerta->agotarEscalamiento($ahora);
                $cuenta['agotadas']++;
            }
            $this->repositorio->guardar($alerta);
        }
        return $cuenta;
    }
}
