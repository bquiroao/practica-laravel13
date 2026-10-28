<?php
declare(strict_types=1);

namespace Lab\Presentation\Http;

use Lab\Application\ConsultarPruebasVigentes;
use Lab\Application\RegistrarPrueba;
use Lab\Application\VerDetallePrueba;
use Lab\Domain\DatosInvalidos;
use Lab\Domain\ErrorPersistencia;
use Lab\Domain\PruebaNoVigente;
use Lab\Domain\VigenciaSuperpuesta;

/** Controlador MVC: traduce la petición a un caso de uso y el resultado a una vista. Sin SQL ni reglas. */
final class LabTestController
{
    public function __construct(
        private readonly RegistrarPrueba $registrar,
        private readonly ConsultarPruebasVigentes $consultar,
        private readonly VerDetallePrueba $detalle,
        private readonly string $tenantId,
        private readonly string $usuario,
    ) {
    }

    public function index(array $query): Respuesta
    {
        try {
            return new Respuesta(200, 'lista', ['pruebas' => $this->consultar->ejecutar($this->tenantId, $query), 'filtros' => $query]);
        } catch (DatosInvalidos $e) {
            return new Respuesta(422, 'lista', ['pruebas' => [], 'filtros' => $query, 'errores' => $e->errores]);
        }
    }

    public function mostrar(string $codigo, array $query): Respuesta
    {
        try {
            return new Respuesta(200, 'detalle', ['prueba' => $this->detalle->ejecutar($this->tenantId, $codigo, $query['fecha'] ?? '')]);
        } catch (PruebaNoVigente $e) {
            return new Respuesta(404, 'mensaje', ['titulo' => 'Prueba no vigente', 'mensaje' => $e->getMessage()]);
        }
    }

    public function formulario(): Respuesta
    {
        return new Respuesta(200, 'formulario', ['valores' => [], 'errores' => []]);
    }

    public function guardar(array $post): Respuesta
    {
        try {
            $prueba = $this->registrar->ejecutar($post, $this->tenantId, $this->usuario);
            return new Respuesta(201, 'detalle', ['prueba' => $prueba, 'aviso' => 'Prueba registrada.']);
        } catch (DatosInvalidos $e) {
            return new Respuesta(422, 'formulario', ['valores' => $post, 'errores' => $e->errores]);
        } catch (VigenciaSuperpuesta $e) {
            return new Respuesta(409, 'formulario', ['valores' => $post, 'errores' => ['vigente_desde' => $e->getMessage()]]);
        } catch (ErrorPersistencia $e) {
            return new Respuesta(500, 'formulario', ['valores' => $post, 'errores' => ['general' => $e->getMessage()]]);
        }
    }
}
