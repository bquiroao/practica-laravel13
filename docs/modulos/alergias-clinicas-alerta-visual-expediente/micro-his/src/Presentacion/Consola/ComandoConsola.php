<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Consola;

use Alergias\Aplicacion\ConsultarAlertaExpediente;
use Alergias\Aplicacion\PacienteNoEncontrado;
use Alergias\Aplicacion\RegistrarAlergia;
use Alergias\Aplicacion\SolicitudRegistro;
use Alergias\Dominio\AlertaExpediente;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\ReglaDominioViolada;

/** Presentation (semana 3): traduce argumentos a casos de uso y resultados a texto. */
final class ComandoConsola
{
    public function __construct(
        private readonly RegistrarAlergia $registrar,
        private readonly ConsultarAlertaExpediente $consultar,
    ) {
    }

    /** @param array<string, string> $op */
    public function ejecutar(string $comando, array $op): int
    {
        try {
            $alerta = match ($comando) {
                'registrar' => $this->registrar->ejecutar(new SolicitudRegistro(
                    $op['paciente'] ?? '', $op['codigo'] ?? null, $op['alergeno'] ?? null, $op['tipo'] ?? 'allergy',
                    $op['severidad'] ?? '', $op['reaccion'] ?? null, $op['inicio'] ?? null, $op['usuario'] ?? '',
                )),
                'alerta' => $this->consultar->ejecutar($op['paciente'] ?? ''),
                default => null,
            };
        } catch (ReglaDominioViolada $e) {
            return $this->escribir("RECHAZADO [{$e->codigo}] {$e->getMessage()}", 2);
        } catch (PacienteNoEncontrado $e) {
            return $this->escribir("NO ENCONTRADO {$e->getMessage()}", 3);
        } catch (ErrorPersistencia $e) {
            return $this->escribir("SIN SERVICIO {$e->getMessage()} No se guardó nada.", 4);
        }
        if ($alerta === null) {
            return $this->escribir('Uso: consola.php registrar|alerta --paciente=PAC-00451 [...]', 1);
        }
        return $this->escribir($this->formatear($alerta), 0);
    }

    private function formatear(AlertaExpediente $alerta): string
    {
        $lineas = ["Paciente {$alerta->codigoPaciente} | ALERTA {$alerta->nivel->value} | huella {$alerta->huella}"];
        if ($alerta->detalle === []) {
            $lineas[] = '  Sin alergias registradas (no verificado: no equivale a «sin alergias»).';
        }
        foreach ($alerta->detalle as $d) {
            $a = $d['alergia'];
            $lineas[] = sprintf('  [%s] %s (%s) reacción: %s', $d['nivel']->value, $a->alergeno->etiqueta(), $a->severidad->value, $a->reaccion ?? 'sin dato');
        }
        if ($alerta->requiereReconocimiento()) {
            $lineas[] = '  Requiere reconocimiento antes de continuar.';
        }
        return implode(PHP_EOL, $lineas);
    }

    private function escribir(string $texto, int $codigo): int
    {
        echo $texto . PHP_EOL;
        return $codigo;
    }
}
