<?php
declare(strict_types=1);

namespace Alergias\Persistencia\EnMemoria;

use Alergias\Dominio\Alergeno;
use Alergias\Dominio\Alergia;
use Alergias\Dominio\AlergiaDuplicada;
use Alergias\Dominio\RepositorioAlergias;
use Alergias\Dominio\Severidad;
use Alergias\Dominio\TipoAlergia;
use DateTimeImmutable;

/** Mismo contrato que el adaptador PDO, incluido el rechazo que hace el índice único. */
final class AlergiasEnMemoria implements RepositorioAlergias
{
    /** @var list<Alergia> */
    private array $alergias = [];

    public static function demo(): self
    {
        $repo = new self();
        $repo->alergias[] = Alergia::reconstruir(
            '1b000000-0000-4000-8000-000000000001', '5a000000-0000-4000-8000-000000000451', new Alergeno(null, 'Ibuprofeno'),
            TipoAlergia::Alergia, Severidad::Moderada, 'Urticaria', new DateTimeImmutable('2021-07-15'),
            '7a1e0000-0000-4000-8000-0000000000a1', 'active',
        );
        return $repo;
    }

    public function existeActivaEquivalente(string $pacienteId, TipoAlergia $tipo, string $claveAlergeno): bool
    {
        foreach ($this->activasDePaciente($pacienteId) as $a) {
            if ($a->tipo === $tipo && $a->alergeno->clave() === $claveAlergeno) {
                return true;
            }
        }
        return false;
    }

    public function guardar(Alergia $alergia): void
    {
        if ($this->existeActivaEquivalente($alergia->pacienteId, $alergia->tipo, $alergia->alergeno->clave())) {
            throw new AlergiaDuplicada();
        }
        $this->alergias[] = $alergia;
    }

    public function activasDePaciente(string $pacienteId): array
    {
        return array_values(array_filter($this->alergias, fn (Alergia $a) => $a->pacienteId === $pacienteId && $a->estado === 'active'));
    }
}
