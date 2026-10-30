<?php
// Semana 2 - SRP: diseño DESPUÉS (versión mínima para la evidencia).
// La implementación completa está en el Micro-HIS desde la semana 3. Datos ficticios.
declare(strict_types=1);

namespace AlertasSrp\Despues;

interface Reloj { public function ahora(): \DateTimeImmutable; }

interface PuertoNotificacion { public function enviar(string $usuario, string $mensaje): bool; }

final class RelojSistema implements Reloj
{
    public function ahora(): \DateTimeImmutable { return new \DateTimeImmutable('now'); }
}

final class RelojFijo implements Reloj
{
    public function __construct(private \DateTimeImmutable $momento) {}
    public function ahora(): \DateTimeImmutable { return $this->momento; }
    public function avanzarMinutos(int $m): void { $this->momento = $this->momento->modify("+$m minutes"); }
}

/** Adaptador simulado: registra lo «enviado»; no envía correos, SMS ni push reales. */
final class NotificadorSimulado implements PuertoNotificacion
{
    public array $enviados = [];
    public array $intentos = [];
    public function __construct(private array $fallanSiempre = []) {}
    public function enviar(string $usuario, string $mensaje): bool
    {
        $this->intentos[$usuario] = ($this->intentos[$usuario] ?? 0) + 1;
        if (in_array($usuario, $this->fallanSiempre, true)) {
            return false;
        }
        $this->enviados[] = [$usuario, $mensaje];
        return true;
    }
}

final class DespachadorNotificaciones
{
    public const MAX_INTENTOS = 3; // RN-08
    public function __construct(private PuertoNotificacion $puerto) {}

    /** @return string[] usuarios a los que no se pudo entregar */
    public function despachar(string $mensaje, array $usuarios): array
    {
        $fallidos = [];
        foreach ($usuarios as $usuario) {
            $ok = false;
            for ($i = 1; $i <= self::MAX_INTENTOS && !$ok; $i++) {
                $ok = $this->puerto->enviar($usuario, $mensaje);
            }
            if (!$ok) {
                $fallidos[] = $usuario;
            }
        }
        return $fallidos;
    }
}

final class PoliticaEscalamiento
{
    private const PLAZOS = ['CRITICA' => 15, 'ALTA' => 30]; // RN-04
    public function vencida(Alerta $a, \DateTimeImmutable $ahora): bool
    {
        if (!in_array($a->estado, ['ENVIADA', 'ESCALADA'], true)) {
            return false; // RN-06: acusada o cerrada no escala
        }
        $limite = $a->plazoDesde->modify('+' . self::PLAZOS[$a->prioridad] . ' minutes');
        return $ahora >= $limite;
    }
}

final class Alerta
{
    public string $estado = 'CREADA';
    public int $nivel = 0;
    public ?\DateTimeImmutable $plazoDesde = null;
    public array $historial = [];

    public function __construct(public readonly string $codigoPaciente, public readonly string $prioridad,
                                \DateTimeImmutable $momento)
    {
        $this->registrar('creada', 'sistema:laboratorio', $momento);
    }

    public function marcarEnviada(\DateTimeImmutable $momento): void
    {
        $this->estado = 'ENVIADA';
        $this->plazoDesde = $momento;
        $this->registrar('enviada', 'sistema', $momento);
    }

    public function acusar(string $usuario, \DateTimeImmutable $momento): bool
    {
        if (in_array($this->estado, ['ACUSADA', 'CERRADA'], true)) {
            return false; // RN-06: idempotente
        }
        $this->estado = 'ACUSADA';
        $this->registrar('acusada', $usuario, $momento);
        return true;
    }

    public function escalar(\DateTimeImmutable $momento): void
    {
        if ($this->nivel >= 2) {
            $this->registrar('escalamiento agotado', 'sistema', $momento); // RN-05
            return;
        }
        $this->nivel++;
        $this->estado = 'ESCALADA';
        $this->plazoDesde = $momento;
        $this->registrar('escalada', 'sistema', $momento);
    }

    private function registrar(string $evento, string $actor, \DateTimeImmutable $momento): void
    {
        $this->historial[] = ['evento' => $evento, 'actor' => $actor, 'momento' => $momento->format('H:i')];
    }
}
