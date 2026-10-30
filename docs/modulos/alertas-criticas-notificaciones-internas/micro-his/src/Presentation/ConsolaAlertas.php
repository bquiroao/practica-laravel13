<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation;

use AlertasCriticas\Domain\{Alerta, AlertaNoEncontradaException, ErrorPersistenciaException, ReglaDominioException};

/** Capa de presentación por consola: traduce argumentos a casos de uso. Sin SQL ni reglas de negocio. */
final class ConsolaAlertas
{
    public const OK = 0;
    public const USO_INCORRECTO = 1;
    public const REGLA_INCUMPLIDA = 2;
    public const NO_ENCONTRADA = 3;
    public const ERROR_PERSISTENCIA = 4;

    /** @param array<string, object> $casos casos de uso armados en bootstrap.php */
    public function __construct(private array $casos) {}

    /** @param string[] $argumentos sin el nombre del script */
    public function ejecutar(array $argumentos): int
    {
        $comando = array_shift($argumentos) ?? 'ayuda';
        $o = $this->opciones($argumentos);
        try {
            switch ($comando) {
                case 'registrar':
                    $r = $this->casos['registrar']->ejecutar([
                        'tenant' => $o['tenant'] ?? '', 'codigo_paciente' => $o['paciente'] ?? '',
                        'servicio' => $o['servicio'] ?? '', 'origen' => $o['origen'] ?? '',
                        'id_origen' => $o['id-origen'] ?? '', 'prioridad' => $o['prioridad'] ?? '',
                        'descripcion' => $o['descripcion'] ?? '']);
                    $this->linea(($r['duplicada'] ? 'Ya existía (no se duplicó): ' : 'Alerta registrada: ') . $this->fila($r['alerta']));
                    return self::OK;
                case 'acusar':
                    $r = $this->casos['acusar']->ejecutar($o['id'] ?? '', $o['usuario'] ?? '', $o['tenant'] ?? '');
                    $this->linea(($r['cambio'] ? 'Acuse registrado: ' : 'Ya estaba acusada (sin cambios): ') . $this->fila($r['alerta']));
                    return self::OK;
                case 'cerrar':
                    $a = $this->casos['cerrar']->ejecutar($o['id'] ?? '', $o['usuario'] ?? '', $o['nota'] ?? '', $o['tenant'] ?? '');
                    $this->linea('Alerta cerrada: ' . $this->fila($a));
                    return self::OK;
                case 'escalar':
                    $c = $this->casos['escalar']->ejecutar();
                    $this->linea("Revisión de plazos: {$c['escaladas']} escalada(s), {$c['agotadas']} con escalamiento agotado");
                    return self::OK;
                case 'historial':
                    $a = $this->casos['historial']->ejecutar($o['id'] ?? '', $o['tenant'] ?? '');
                    $this->linea($this->fila($a));
                    foreach ($a->historial() as $e) {
                        $this->linea(sprintf('  %2d. %s  %-20s %-22s %s', $e->orden, $e->momento->format('Y-m-d H:i'),
                            $e->evento, $e->actor, $e->detalle));
                    }
                    return self::OK;
                case 'listar':
                    foreach ($this->casos['listar']->ejecutar($o['tenant'] ?? '') as $a) {
                        $this->linea($this->fila($a));
                    }
                    return self::OK;
                default:
                    $this->ayuda();
                    return $comando === 'ayuda' ? self::OK : self::USO_INCORRECTO;
            }
        } catch (ReglaDominioException $e) {
            $this->linea('Regla incumplida ' . $e->getMessage());
            return self::REGLA_INCUMPLIDA;
        } catch (AlertaNoEncontradaException $e) {
            $this->linea('No encontrada: ' . $e->getMessage());
            return self::NO_ENCONTRADA;
        } catch (ErrorPersistenciaException $e) {
            $this->linea('Error de persistencia: ' . $e->getMessage());
            return self::ERROR_PERSISTENCIA;
        }
    }

    private function fila(Alerta $a): string
    {
        return sprintf('%s · %s · %s · nivel %d · %s', $a->id, $a->paciente->valor, $a->prioridad->value,
            $a->nivel(), $a->estado()->value . ($a->escalamientoAgotado() ? ' (escalamiento agotado)' : ''));
    }

    /** @return array<string,string> */
    private function opciones(array $argumentos): array
    {
        $o = [];
        foreach ($argumentos as $arg) {
            if (preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $m)) {
                $o[$m[1]] = $m[2];
            }
        }
        return $o;
    }

    private function linea(string $texto): void
    {
        echo $texto, PHP_EOL;
    }

    private function ayuda(): void
    {
        $this->linea('Uso: php consola.php <comando> [--opción=valor ...] [--ahora="AAAA-MM-DD HH:MM"]');
        $this->linea('  registrar --tenant --paciente --servicio --origen --id-origen --prioridad --descripcion');
        $this->linea('  acusar    --id --usuario --tenant');
        $this->linea('  cerrar    --id --usuario --nota --tenant');
        $this->linea('  escalar   (revisa plazos vencidos)');
        $this->linea('  historial --id --tenant');
        $this->linea('  listar    --tenant');
    }
}
