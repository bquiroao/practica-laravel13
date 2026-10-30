<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Plazos de acuse por prioridad (RN-04, RN-11) y vencimiento (RN-05). */
final class PoliticaEscalamiento
{
    public const NIVEL_MAXIMO = Alerta::NIVEL_MAXIMO;
    public const PLAZO_MINIMO = 5;
    public const PLAZO_MAXIMO = 60;

    /** @var array<string,int> */
    private array $plazos = ['CRITICA' => 15, 'ALTA' => 30];

    public function __construct(array $plazos = [])
    {
        foreach ($plazos as $prioridad => $minutos) {
            $this->configurarPlazo(Prioridad::desde((string) $prioridad), (int) $minutos);
        }
    }

    public function configurarPlazo(Prioridad $prioridad, int $minutos): void
    {
        if ($minutos < self::PLAZO_MINIMO || $minutos > self::PLAZO_MAXIMO) {
            throw new ReglaDominioException('RN-11', "El plazo debe estar entre 5 y 60 minutos; se recibió $minutos");
        }
        $this->plazos[$prioridad->value] = $minutos;
    }

    public function plazoMinutos(Prioridad $prioridad): int
    {
        return $this->plazos[$prioridad->value];
    }

    public function vencida(Alerta $alerta, \DateTimeImmutable $ahora): bool
    {
        if (!$alerta->estado()->esperaAcuse() || $alerta->escalamientoAgotado() || $alerta->plazoDesde() === null) {
            return false; // RN-06: acusada o cerrada no se escala
        }
        $limite = $alerta->plazoDesde()->modify('+' . $this->plazoMinutos($alerta->prioridad) . ' minutes');
        return $ahora >= $limite;
    }
}
