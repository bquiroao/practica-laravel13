<?php
declare(strict_types=1);

namespace Alergias\Presentacion\Web;

use Alergias\Aplicacion\RegistrarAlergia;
use Alergias\Dominio\ReglaDominioViolada;
use Alergias\Presentacion\TraductorErrores;
use Throwable;

/** Registro de alergia (CU-01) desde el formulario. Sin SQL ni reglas. */
final class ControladorAlergia
{
    public function __construct(
        private readonly RegistrarAlergia $registrar,
        private readonly string $usuarioId,
    ) {
    }

    public function formulario(array $ruta): Respuesta
    {
        return new Respuesta(200, Vista::render('formulario', ['codigo' => $ruta['codigo'], 'valores' => [], 'error' => null]));
    }

    public function registrar(array $ruta, array $formulario): Respuesta
    {
        try {
            $this->registrar->ejecutar(FormularioAlergia::solicitud($ruta['codigo'], $formulario, $this->usuarioId));
            return Respuesta::redirigir("/pacientes/{$ruta['codigo']}/alerta");
        } catch (ReglaDominioViolada $e) {
            // Error recuperable: se vuelve al formulario con lo escrito y el mensaje del dominio.
            [$estado, $codigo, $mensaje] = TraductorErrores::traducir($e);
            $datos = ['codigo' => $ruta['codigo'], 'valores' => $formulario, 'error' => "{$mensaje} ({$codigo})"];
            return new Respuesta($estado, Vista::render('formulario', $datos));
        } catch (Throwable $e) {
            return ControladorAlerta::error($e);
        }
    }
}
