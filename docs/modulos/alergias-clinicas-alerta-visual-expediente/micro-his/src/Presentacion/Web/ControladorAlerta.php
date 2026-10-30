<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Web;

use Alergias\Aplicacion\ConsultarAlertaExpediente;
use Alergias\Aplicacion\ReconocerAlerta;
use Alergias\Presentacion\TraductorErrores;
use Throwable;

/** C de MVC: recibe, llama al caso de uso y elige la vista. Sin SQL ni reglas. */
final class ControladorAlerta
{
    public function __construct(
        private readonly ConsultarAlertaExpediente $consultar,
        private readonly ReconocerAlerta $reconocer,
        private readonly string $usuarioId,
    ) {
    }

    public function mostrar(array $ruta): Respuesta
    {
        try {
            $alerta = $this->consultar->ejecutar($ruta['codigo'], $this->usuarioId);
            return new Respuesta(200, Vista::render('alerta', ['alerta' => $alerta, 'aviso' => null]));
        } catch (Throwable $e) {
            return self::error($e);
        }
    }

    public function reconocer(array $ruta, array $formulario): Respuesta
    {
        try {
            $this->reconocer->ejecutar($ruta['codigo'], $this->usuarioId, (string) ($formulario['huella'] ?? ''));
            return Respuesta::redirigir("/pacientes/{$ruta['codigo']}/alerta");
        } catch (Throwable $e) {
            return self::error($e);
        }
    }

    public static function error(Throwable $e): Respuesta
    {
        [$estado, $codigo, $mensaje] = TraductorErrores::traducir($e);
        return new Respuesta($estado, Vista::render('error', ['codigo' => $codigo, 'mensaje' => $mensaje]));
    }
}
