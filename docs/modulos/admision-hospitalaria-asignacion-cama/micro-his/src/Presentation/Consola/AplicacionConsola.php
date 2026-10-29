<?php
declare(strict_types=1);

namespace Admision\Presentation\Consola;

use Admision\Application\ContextoUsuario;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Application\Puertos\GeneradorId;
use Admision\Application\SolicitudAdmision;
use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\ErrorDeDominio;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Presentation\Servicios;
use Admision\Presentation\UsuariosFicticios;

/** Presentación por consola: traduce argumentos a casos de uso. Sin SQL ni reglas de negocio. */
final class AplicacionConsola
{
    /** @param \Closure(string): void $escribir */
    public function __construct(
        private readonly Servicios $servicios,
        private readonly UsuariosFicticios $usuarios,
        private readonly GeneradorId $ids,
        private readonly \Closure $escribir,
    ) {
    }

    /** @param list<string> $argv */
    public function ejecutar(array $argv): int
    {
        $comando = $argv[1] ?? 'ayuda';
        if (!in_array($comando, ['camas', 'admitir'], true)) {
            return $this->ayuda();
        }
        $op = $this->opciones(array_slice($argv, 2));
        try {
            $usuario = $this->usuarios->contexto($op['usuario'] ?? '', 'cli-' . $this->ids->nuevo());
            return $comando === 'camas' ? $this->camas($op, $usuario) : $this->admitir($op, $usuario);
        } catch (ErrorDeDominio | AccesoDenegado $e) {
            $this->linea("ERROR [{$e->codigo()}] {$e->getMessage()}");
            if ($e instanceof CamaNoElegible || $e->codigo() === 'CAMA_YA_ASIGNADA') {
                $this->linea('Consulte de nuevo con «camas» y elija otra cama.');
            }
            return 1;
        } catch (ErrorPersistencia $e) {
            $this->linea("ERROR [{$e->codigo()}] {$e->getMessage()} Intente de nuevo.");
            return 2;
        }
    }

    private function camas(array $op, ContextoUsuario $usuario): int
    {
        $camas = $this->servicios->camasDisponibles->ejecutar(
            $op['servicio'] ?? '', $op['tipo'] ?? '', isset($op['aislamiento']), $usuario,
        );
        if ($camas === []) {
            $this->linea('No hay camas disponibles compatibles con ese requerimiento.');
            return 0;
        }
        $this->linea('Camas disponibles en ' . $usuario->tenantId->valor . ':');
        foreach ($camas as $c) {
            $this->linea(sprintf('  %-6s %-18s %-8s %s  id=%s', $c->codigo, $c->servicio, $c->tipo->value, $c->aislamiento ? 'aislamiento' : 'sin aislamiento', $c->id));
        }
        return 0;
    }

    private function admitir(array $op, ContextoUsuario $usuario): int
    {
        $r = $this->servicios->admitir->ejecutar(new SolicitudAdmision(
            $op['paciente'] ?? '', $op['servicio'] ?? '', $op['tipo'] ?? '',
            isset($op['aislamiento']), $op['origen'] ?? '', $op['cama'] ?? '',
        ), $usuario);
        $this->linea("Admisión {$r->estado}: paciente {$r->codigoPaciente} en cama {$r->camaCodigo} ({$r->servicio}).");
        $this->linea("Id: {$r->admisionId} · registrada por {$r->creadaPor} · {$r->creadaEn}");
        return 0;
    }

    private function ayuda(): int
    {
        $this->linea('Uso:');
        $this->linea('  php consola.php camas   --usuario=usr-adm-01 --servicio=MEDICINA_INTERNA --tipo=GENERAL [--aislamiento]');
        $this->linea('  php consola.php admitir --usuario=usr-adm-01 --paciente=PAC-00451 --servicio=MEDICINA_INTERNA --tipo=GENERAL');
        $this->linea('                          --origen=EMERGENCIA --cama=<id de la cama> [--aislamiento]');
        return 0;
    }

    /** @return array<string, string> */
    private function opciones(array $args): array
    {
        $op = [];
        foreach ($args as $arg) {
            if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $arg, $m) === 1) {
                $op[$m[1]] = $m[2] ?? '';
            }
        }
        return $op;
    }

    private function linea(string $texto): void
    {
        ($this->escribir)($texto);
    }
}
