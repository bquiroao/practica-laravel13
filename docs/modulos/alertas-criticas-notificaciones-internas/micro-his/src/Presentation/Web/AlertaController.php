<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

use AlertasCriticas\Domain\{AlertaNoEncontradaException, ErrorPersistenciaException, ReglaDominioException};

/**
 * Controlador MVC: lee la petición, llama un caso de uso y elige vista o redirección.
 * No contiene SQL ni reglas de negocio: las reglas viven en Domain y se reciben como excepciones.
 */
final class AlertaController
{
    private const MENSAJES = [
        'registrada' => 'Alerta registrada y notificada.', 'duplicada' => 'El evento ya existía; no se duplicó.',
        'acusada' => 'Acuse registrado.', 'ya-acusada' => 'La alerta ya estaba acusada; no hubo cambios.',
        'cerrada' => 'Alerta cerrada.', 'revisada' => 'Revisión de plazos ejecutada.',
    ];

    /** @param array<string, object> $casos casos de uso armados en bootstrap.php */
    public function __construct(private array $casos, private Vista $vista) {}

    public function lista(Peticion $p): Respuesta
    {
        $sesion = SesionDemo::desde($p);
        return $this->atrapar($sesion, fn () => $this->pagina('lista', $sesion, $p, 'Alertas críticas',
            ['alertas' => $this->casos['listar']->ejecutar($sesion->tenant())]));
    }

    public function detalle(Peticion $p, string $id): Respuesta
    {
        $sesion = SesionDemo::desde($p);
        return $this->atrapar($sesion, fn () => $this->pagina('detalle', $sesion, $p, 'Detalle de alerta',
            ['alerta' => $this->casos['historial']->ejecutar($id, $sesion->tenant())]));
    }

    /** Simula el evento del sistema origen (en la semana 5 llega por la API). */
    public function registrar(Peticion $p): Respuesta
    {
        $sesion = SesionDemo::desde($p);
        return $this->atrapar($sesion, function () use ($p, $sesion) {
            $r = $this->casos['registrar']->ejecutar([
                'tenant' => $sesion->tenant(), 'codigo_paciente' => $p->campo('codigo_paciente'),
                'servicio' => $p->campo('servicio'), 'origen' => $p->campo('origen'),
                'id_origen' => $p->campo('id_origen'), 'prioridad' => $p->campo('prioridad'),
                'descripcion' => $p->campo('descripcion')]);
            return Respuesta::redirigir("/alertas/{$r['alerta']->id}?msg=" . ($r['duplicada'] ? 'duplicada' : 'registrada'));
        });
    }

    public function acusar(Peticion $p, string $id): Respuesta
    {
        $sesion = SesionDemo::desde($p);
        return $this->atrapar($sesion, function () use ($id, $sesion) {
            $r = $this->casos['acusar']->ejecutar($id, $sesion->usuario, $sesion->tenant());
            return Respuesta::redirigir("/alertas/$id?msg=" . ($r['cambio'] ? 'acusada' : 'ya-acusada'));
        });
    }

    public function cerrar(Peticion $p, string $id): Respuesta
    {
        $sesion = SesionDemo::desde($p);
        return $this->atrapar($sesion, function () use ($p, $id, $sesion) {
            $this->casos['cerrar']->ejecutar($id, $sesion->usuario, $p->campo('nota'), $sesion->tenant());
            return Respuesta::redirigir("/alertas/$id?msg=cerrada");
        });
    }

    /** Simula al planificador (en producción lo ejecutaría una tarea programada). */
    public function escalar(Peticion $p): Respuesta
    {
        $sesion = SesionDemo::desde($p);
        return $this->atrapar($sesion, function () {
            $this->casos['escalar']->ejecutar();
            return Respuesta::redirigir('/?msg=revisada');
        });
    }

    public function cambiarSesion(Peticion $p): Respuesta
    {
        $usuario = $p->campo('usuario');
        if (!isset(SesionDemo::USUARIOS[$usuario])) {
            return $this->error(422, 'Usuario de demostración no válido.', SesionDemo::desde($p), $p);
        }
        return Respuesta::redirigir('/', ['Set-Cookie' => "usuario_demo=$usuario; Path=/; HttpOnly; SameSite=Lax"]);
    }

    /** Traduce excepciones de dominio y persistencia a códigos HTTP y vistas de error. */
    private function atrapar(SesionDemo $sesion, callable $accion): Respuesta
    {
        $p = new Peticion('GET', '/');
        try {
            return $accion();
        } catch (ReglaDominioException $e) {
            return $this->error($e->regla === 'RN-07' ? 403 : 422, $e->getMessage(), $sesion, $p);
        } catch (AlertaNoEncontradaException) {
            return $this->error(404, 'La alerta no existe o no pertenece a su hospital.', $sesion, $p);
        } catch (ErrorPersistenciaException) {
            return $this->error(503, 'No se pudo acceder a los datos. Intente de nuevo en unos minutos.', $sesion, $p);
        }
    }

    private function pagina(string $plantilla, SesionDemo $sesion, Peticion $p, string $titulo, array $datos, int $estado = 200): Respuesta
    {
        $mensaje = self::MENSAJES[(string) ($p->consulta['msg'] ?? '')] ?? null;
        $html = $this->vista->render($plantilla, $datos + ['sesion' => $sesion, 'mensaje' => $mensaje,
            'usuarios' => array_keys(SesionDemo::USUARIOS)], $titulo);
        return new Respuesta($estado, $html);
    }

    private function error(int $estado, string $mensaje, SesionDemo $sesion, Peticion $p): Respuesta
    {
        return $this->pagina('error', $sesion, $p, 'No se pudo completar', ['error' => $mensaje, 'codigo' => $estado], $estado);
    }
}
