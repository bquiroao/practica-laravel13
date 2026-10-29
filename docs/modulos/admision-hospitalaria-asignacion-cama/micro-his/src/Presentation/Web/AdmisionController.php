<?php
declare(strict_types=1);

namespace Admision\Presentation\Web;

use Admision\Application\ContextoUsuario;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Application\Puertos\GeneradorId;
use Admision\Application\SolicitudAdmision;
use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\ErrorDeDominio;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Presentation\MapaErrores;
use Admision\Presentation\Servicios;
use Admision\Presentation\UsuariosFicticios;

/**
 * Controlador MVC. Solo: leer la petición, llamar al caso de uso y elegir la vista.
 * NO tiene SQL ni reglas de negocio (lo verifica pruebas/unitarias/prueba-dependencias.php).
 */
final class AdmisionController
{
    public function __construct(
        private readonly Servicios $servicios,
        private readonly UsuariosFicticios $usuarios,
        private readonly GeneradorId $ids,
        private readonly Vista $vista,
    ) {
    }

    /** GET / — paso 1: datos de la admisión (A-01). */
    public function formulario(Peticion $p): Respuesta
    {
        return $this->pagina(200, 'formulario', ['valores' => $this->valores($p)], 'Nueva admisión');
    }

    /** GET /camas — paso 2: camas disponibles y compatibles (CU-03). */
    public function camas(Peticion $p): Respuesta
    {
        $valores = $this->valores($p);
        try {
            $camas = $this->servicios->camasDisponibles->ejecutar(
                $valores['servicio'], $valores['tipo'], $valores['aislamiento'] === '1', $this->usuario($p),
            );
        } catch (ErrorDeDominio | AccesoDenegado | ErrorPersistencia $e) {
            return $this->error($e, 'formulario', $valores);
        }
        return $this->pagina(200, 'camas', ['valores' => $valores, 'camas' => $camas], 'Elegir cama');
    }

    /** POST /admisiones — paso 3: confirmar (CU-01). */
    public function registrar(Peticion $p): Respuesta
    {
        $valores = $this->valores($p);
        try {
            $r = $this->servicios->admitir->ejecutar(new SolicitudAdmision(
                $valores['paciente'], $valores['servicio'], $valores['tipo'], $valores['aislamiento'] === '1',
                $valores['origen'], $p->dato('cama'),
            ), $this->usuario($p));
        } catch (ErrorDeDominio | AccesoDenegado | ErrorPersistencia $e) {
            $volverA = $e instanceof CamaNoElegible || $e->codigo() === 'CAMA_YA_ASIGNADA' ? 'camas' : 'formulario';
            return $this->error($e, $volverA, $valores, $p);
        }
        return $this->pagina(201, 'resultado', ['r' => $r, 'valores' => $valores], 'Admisión registrada');
    }

    private function usuario(Peticion $p): ContextoUsuario
    {
        return $this->usuarios->contexto($p->dato('usuario'), 'web-' . $this->ids->nuevo());
    }

    /** @return array<string, string> lo capturado se conserva ante un error (E-02) */
    private function valores(Peticion $p): array
    {
        return [
            'usuario' => $p->dato('usuario') ?: 'usr-adm-01',
            'paciente' => $p->dato('paciente'),
            'servicio' => $p->dato('servicio') ?: 'MEDICINA_INTERNA',
            'tipo' => $p->dato('tipo') ?: 'GENERAL',
            'aislamiento' => $p->dato('aislamiento') === '1' ? '1' : '0',
            'origen' => $p->dato('origen') ?: 'EMERGENCIA',
        ];
    }

    private function error(\Throwable $e, string $plantilla, array $valores, ?Peticion $p = null): Respuesta
    {
        $datos = ['valores' => $valores, 'error' => ['codigo' => $e->codigo(), 'mensaje' => $e->getMessage()]];
        if ($plantilla === 'camas' && $p !== null) {
            // Conflicto recuperable (CU-05): se vuelve a mostrar la lista actualizada.
            $datos['camas'] = $this->servicios->camasDisponibles->ejecutar(
                $valores['servicio'], $valores['tipo'], $valores['aislamiento'] === '1', $this->usuario($p),
            );
        }
        return $this->pagina(MapaErrores::estado($e->codigo()), $plantilla, $datos, 'No se pudo completar');
    }

    private function pagina(int $estado, string $plantilla, array $datos, string $titulo): Respuesta
    {
        return new Respuesta($estado, $this->vista->render($plantilla, $datos, $titulo));
    }
}
