<?php
declare(strict_types=1);

// DESPUÉS: el caso de uso pide las alertas a la política y el repositorio solo las escribe.
namespace SignosVitales\Refactor;

use SignosVitales\Aplicacion\{ErrorPersistencia, FuenteClinica, Usuario};
use SignosVitales\Dominio\{ErrorDominio, EvaluadorUmbrales, Medicion, Toma};
use function SignosVitales\Aplicacion\exigirEpisodio;

final class RepositorioTomasDespues {
    public function __construct(private \PDO $pdo) {}

    /** @param Alerta[] $alertas */
    public function guardar(Toma $t, array $alertas): void {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('INSERT INTO tomas_signos_vitales (id, paciente_id, episodio_id, autor_id, tomada_en) VALUES (?, ?, ?, ?, ?)')
                ->execute([$t->id, $t->pacienteId, $t->episodioId, $t->autorId, $t->tomadaEn->format('c')]);
            $medicion = $this->pdo->prepare('INSERT INTO mediciones_signo (toma_id, tipo, valor, clasificacion) VALUES (?, ?, ?, ?)');
            foreach ($t->mediciones as $m) { $medicion->execute([$t->id, $m->tipo->value, $m->valor, $t->clasificaciones[$m->tipo->value]->value]); }
            $fila = $this->pdo->prepare('INSERT INTO alertas_signos_vitales (toma_id, tipo, nivel, valor, estado_envio) VALUES (?, ?, ?, ?, ?)');
            foreach ($alertas as $a) { $fila->execute([$t->id, $a->signo, $a->nivel->value, $a->valor, $a->envio->value]); }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new ErrorPersistencia('No se pudo guardar la toma', 0, $e);
        }
    }
}

final class RegistrarTomaDespues {
    public function __construct(private RepositorioTomasDespues $tomas, private FuenteClinica $clinica, private PoliticaDeAlertas $politica,
                                private \Closure $reloj, private \Closure $nuevoId, private EvaluadorUmbrales $evaluador) {}

    public function ejecutar(array $entrada, Usuario $usuario): array {
        if ($usuario->rol !== 'enfermeria') { throw new ErrorDominio('acceso_denegado', 'Solo enfermería registra signos vitales'); }
        $paciente = (string) ($entrada['paciente_id'] ?? '');
        $episodio = exigirEpisodio($this->clinica, $paciente, (string) ($entrada['episodio_id'] ?? ''), $usuario);
        $valores = is_array($entrada['mediciones'] ?? null) ? $entrada['mediciones'] : [];
        $mediciones = array_map(fn ($tipo, $valor) => Medicion::desde((string) $tipo, $valor), array_keys($valores), $valores);
        $en = ($this->reloj)();
        $edad = (new \DateTimeImmutable($episodio['fecha_nacimiento']))->diff($en)->y;
        $toma = Toma::registrar(($this->nuevoId)(), $paciente, (string) $entrada['episodio_id'], $usuario->id, $en, $mediciones, $this->clinica->rangosPara($edad), $this->evaluador);
        $alertas = $this->politica->alertasDe($toma);
        $this->tomas->guardar($toma, $alertas);
        return ['toma_id' => $toma->id, 'clasificaciones' => array_map(fn ($c) => $c->value, $toma->clasificaciones),
                'alertas' => array_map(fn (Alerta $a) => ['signo' => $a->signo, 'nivel' => $a->nivel->value, 'valor' => $a->valor], $alertas)];
    }
}
