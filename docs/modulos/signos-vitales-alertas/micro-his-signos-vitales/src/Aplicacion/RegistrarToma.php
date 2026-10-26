<?php
declare(strict_types=1);

// Aplicación: orquesta el flujo CU-01 (permiso → episodio → validar y clasificar → guardar). Sin SQL ni HTTP.
namespace SignosVitales\Aplicacion;

use SignosVitales\Dominio\{ErrorDominio, EvaluadorUmbrales, Medicion, Toma};
use SignosVitales\Persistencia\TomasPdo;

final class Usuario {
    public function __construct(public readonly string $id, public readonly string $rol, public readonly string $hospitalId) {}
}

final class ErrorPersistencia extends \RuntimeException {}

final class RegistrarToma {
    private \Closure $reloj;
    private \Closure $nuevoId;
    private EvaluadorUmbrales $evaluador;

    // Semana 3: depende directo del adaptador PDO. En la semana 4 se invierte con una interfaz Repository.
    public function __construct(private TomasPdo $bd, ?\Closure $reloj = null, ?\Closure $nuevoId = null, ?EvaluadorUmbrales $evaluador = null) {
        $this->reloj = $reloj ?? fn () => new \DateTimeImmutable();
        $this->nuevoId = $nuevoId ?? fn () => bin2hex(random_bytes(8));
        $this->evaluador = $evaluador ?? EvaluadorUmbrales::estandar();
    }

    public function ejecutar(array $entrada, Usuario $usuario): array {
        if ($usuario->rol !== 'enfermeria') { throw new ErrorDominio('acceso_denegado', 'Solo enfermería registra signos vitales'); }
        $paciente = (string) ($entrada['paciente_id'] ?? '');
        $episodio = $this->bd->episodioActivo($paciente, (string) ($entrada['episodio_id'] ?? ''));
        if ($episodio === null) { throw new ErrorDominio('episodio_no_disponible', 'El paciente no tiene ese episodio activo'); }
        if ($episodio['hospital_id'] !== $usuario->hospitalId) { throw new ErrorDominio('acceso_denegado', 'El paciente es de otro hospital'); }
        $valores = is_array($entrada['mediciones'] ?? null) ? $entrada['mediciones'] : [];
        $mediciones = array_map(fn ($tipo, $valor) => Medicion::desde((string) $tipo, $valor), array_keys($valores), $valores);
        $en = ($this->reloj)();
        $edad = (new \DateTimeImmutable($episodio['fecha_nacimiento']))->diff($en)->y;
        $toma = Toma::registrar(($this->nuevoId)(), $paciente, (string) $entrada['episodio_id'], $usuario->id, $en,
                                $mediciones, $this->bd->rangosPara($edad), $this->evaluador);
        $this->bd->guardar($toma);
        return ['toma_id' => $toma->id, 'clasificaciones' => array_map(fn ($c) => $c->value, $toma->clasificaciones), 'alertas' => $toma->alertas()];
    }
}
