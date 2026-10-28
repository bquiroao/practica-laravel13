<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/**
 * Ficha del paciente en un hospital (local_patients). Inmutable: cada cambio devuelve otra instancia.
 *
 * El estado (activo, inactivo, fallecido) es un DATO, no una subclase. Una clase
 * «PacienteFallecido» que lanzara excepción al editar rompería LSP (ver el documento, §6.4).
 */
final class Paciente
{
    public const ACTIVO = 'active';
    public const INACTIVO = 'inactive';
    public const FALLECIDO = 'deceased';
    public const SEXOS = ['female', 'male', 'other', 'unknown'];

    private function __construct(
        public readonly ?string $codigo,
        public readonly string $hospital,
        public readonly string $nombres,
        public readonly string $apellidos,
        public readonly \DateTimeImmutable $nacimiento,
        public readonly string $sexo,
        public readonly ?string $telefono,
        public readonly ?Identificador $identificador,
        public readonly string $estado,
        public readonly int $version,
    ) {
    }

    /**
     * Valida lo capturado (R-01) y crea un paciente nuevo, activo y sin código.
     *
     * @throws ErrorPaciente 422 DATOS_INVALIDOS con los campos que fallan
     */
    public static function desdeDatos(string $hospital, DatosPaciente $datos, \DateTimeImmutable $hoy): self
    {
        $errores = [];
        $nombres = trim($datos->nombres);
        $apellidos = trim($datos->apellidos);
        if ($nombres === '' || mb_strlen($nombres) > 100) {
            $errores['nombres'] = 'Obligatorio, hasta 100 caracteres.';
        }
        if ($apellidos === '' || mb_strlen($apellidos) > 100) {
            $errores['apellidos'] = 'Obligatorio, hasta 100 caracteres.';
        }

        $nacimiento = \DateTimeImmutable::createFromFormat('!Y-m-d', $datos->nacimiento);
        if ($nacimiento === false || $nacimiento->format('Y-m-d') !== $datos->nacimiento) {
            $errores['nacimiento'] = 'Fecha válida con formato AAAA-MM-DD.';
        } elseif ($nacimiento > $hoy) {
            $errores['nacimiento'] = 'No puede ser una fecha futura.';
        }

        if (!in_array($datos->sexo, self::SEXOS, true)) {
            $errores['sexo'] = 'Debe ser female, male, other o unknown.';
        }

        $telefono = trim((string) $datos->telefono);
        if ($telefono !== '' && preg_match('/^\+?[0-9 ]{8,15}$/', $telefono) !== 1) {
            $errores['telefono'] = 'De 8 a 15 dígitos.';
        }

        $identificador = null;
        try {
            $identificador = FabricaIdentificador::crear($datos->tipoDocumento, $datos->numeroDocumento, $datos->paisDocumento);
        } catch (\InvalidArgumentException $e) {
            $errores['documento'] = $e->getMessage();
        }

        if ($errores !== []) {
            throw new ErrorPaciente(422, 'DATOS_INVALIDOS', ['campos' => $errores]);
        }

        return new self(null, $hospital, $nombres, $apellidos, $nacimiento, $datos->sexo,
            $telefono === '' ? null : $telefono, $identificador, self::ACTIVO, 0);
    }

    /** Sin documento: se registra con identidad provisional (por ejemplo, ingreso por emergencia). */
    public function identidadProvisional(): bool
    {
        return $this->identificador === null;
    }

    public function nombreCompleto(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }

    public function nombreNormalizado(): string
    {
        return Texto::normalizar($this->nombreCompleto());
    }

    public function conCodigo(string $codigo, int $version): self
    {
        return $this->copiar(codigo: $codigo, version: $version);
    }

    public function conVersion(int $version): self
    {
        return $this->copiar(version: $version);
    }

    public function conEstado(string $estado): self
    {
        return $this->copiar(estado: $estado);
    }

    public function conIdentificador(?Identificador $identificador): self
    {
        return new self($this->codigo, $this->hospital, $this->nombres, $this->apellidos, $this->nacimiento,
            $this->sexo, $this->telefono, $identificador, $this->estado, $this->version);
    }

    /** Los datos de $nuevo sobre esta ficha: conserva código, hospital, estado y versión. */
    public function conDatosDe(self $nuevo): self
    {
        return new self($this->codigo, $this->hospital, $nuevo->nombres, $nuevo->apellidos, $nuevo->nacimiento,
            $nuevo->sexo, $nuevo->telefono, $nuevo->identificador, $this->estado, $this->version);
    }

    /** ¿Cambia algún dato de identidad (R-07)? Nombres, apellidos, fecha de nacimiento o documento. */
    public function cambiaIdentidadRespectoDe(self $otro): bool
    {
        return $this->nombreNormalizado() !== $otro->nombreNormalizado()
            || $this->nacimiento != $otro->nacimiento
            || $this->identificador?->claveBusqueda() !== $otro->identificador?->claveBusqueda();
    }

    /** Datos mínimos para listas de búsqueda (R-10, R-04). */
    public function resumen(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombreCompleto(),
            'nacimiento' => $this->nacimiento->format('Y-m-d'),
            'identificador' => $this->identificador?->enmascarado(),
        ];
    }

    /** Detalle completo, con el identificador enmascarado. */
    public function detalle(): array
    {
        return $this->resumen() + [
            'sexo' => $this->sexo,
            'telefono' => $this->telefono,
            'tipo_documento' => $this->identificador?->tipo(),
            'estado' => $this->estado,
            'identidad_provisional' => $this->identidadProvisional(),
            'version' => $this->version,
        ];
    }

    /** Valores para la auditoría «antes/después» (R-09), sin el documento en claro. */
    public function instantanea(): array
    {
        return [
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nacimiento' => $this->nacimiento->format('Y-m-d'),
            'sexo' => $this->sexo,
            'telefono' => $this->telefono,
            'identificador' => $this->identificador?->enmascarado(),
            'estado' => $this->estado,
        ];
    }

    public static function compararPorNombre(self $a, self $b): int
    {
        return [Texto::normalizar($a->apellidos), Texto::normalizar($a->nombres), $a->codigo]
            <=> [Texto::normalizar($b->apellidos), Texto::normalizar($b->nombres), $b->codigo];
    }

    private function copiar(?string $codigo = null, ?int $version = null, ?string $estado = null): self
    {
        return new self($codigo ?? $this->codigo, $this->hospital, $this->nombres, $this->apellidos, $this->nacimiento,
            $this->sexo, $this->telefono, $this->identificador, $estado ?? $this->estado, $version ?? $this->version);
    }
}
