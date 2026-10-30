<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Entidad raíz: estado, nivel, destinatarios notificados e historial de una alerta crítica. */
final class Alerta
{
    public const NIVEL_MAXIMO = 2; // RN-05: 0 enfermería + médico, 1 jefe de servicio, 2 coordinación médica

    private EstadoAlerta $estado = EstadoAlerta::CREADA;
    private int $nivel = 0;
    private ?\DateTimeImmutable $plazoDesde = null;
    private bool $escalamientoAgotado = false;
    /** @var array<string,int> usuario => nivel en que fue notificado */
    private array $destinatarios = [];
    /** @var EventoHistorial[] */
    private array $historial = [];

    private function __construct(
        public readonly string $id,
        public readonly string $tenant,
        public readonly CodigoPaciente $paciente,
        public readonly string $servicio,
        public readonly string $origen,
        public readonly string $idOrigen,
        public readonly Prioridad $prioridad,
        public readonly string $descripcion,
    ) {}

    /** CU-01 / RN-01: crea la alerta validando todos los datos obligatorios. */
    public static function crear(array $datos, \DateTimeImmutable $momento, ?string $id = null): self
    {
        foreach (['tenant', 'codigo_paciente', 'servicio', 'origen', 'id_origen', 'prioridad', 'descripcion'] as $campo) {
            if (trim((string) ($datos[$campo] ?? '')) === '') {
                throw new ReglaDominioException('RN-01', "Falta el dato obligatorio «{$campo}»");
            }
        }
        if (mb_strlen(trim($datos['descripcion'])) > 200) {
            throw new ReglaDominioException('RN-01', 'La descripción no puede pasar de 200 caracteres');
        }
        $alerta = new self(
            $id ?? IdAlerta::nuevo(), trim($datos['tenant']), new CodigoPaciente($datos['codigo_paciente']),
            trim($datos['servicio']), trim($datos['origen']), trim($datos['id_origen']),
            Prioridad::desde($datos['prioridad']), trim($datos['descripcion']),
        );
        $alerta->registrar('creada', 'sistema:' . $alerta->origen, $momento);
        return $alerta;
    }

    /** Uso exclusivo de los adaptadores de persistencia para reconstruir una alerta guardada. */
    public static function reconstituir(array $f, array $destinatarios, array $historial): self
    {
        $alerta = new self($f['id'], $f['tenant'], new CodigoPaciente($f['codigo_paciente']), $f['servicio'],
            $f['origen'], $f['id_origen'], Prioridad::from($f['prioridad']), $f['descripcion']);
        $alerta->estado = EstadoAlerta::from($f['estado']);
        $alerta->nivel = (int) $f['nivel'];
        $alerta->plazoDesde = $f['plazo_desde'] ? new \DateTimeImmutable($f['plazo_desde']) : null;
        $alerta->escalamientoAgotado = (bool) $f['escalamiento_agotado'];
        $alerta->destinatarios = $destinatarios;
        $alerta->historial = $historial;
        return $alerta;
    }

    /** CU-03: registra a quién se entregó; queda ENVIADA (nivel 0) o ESCALADA (nivel 1-2) y corre el plazo. */
    public function registrarEnvio(array $usuarios, \DateTimeImmutable $momento): void
    {
        $this->exigirAbierta();
        foreach ($usuarios as $usuario) {
            $this->destinatarios[$usuario] ??= $this->nivel;
        }
        $this->estado = $this->nivel === 0 ? EstadoAlerta::ENVIADA : EstadoAlerta::ESCALADA;
        $this->plazoDesde = $momento;
        $this->registrar('enviada', 'sistema', $momento, "nivel {$this->nivel}: " . implode(', ', $usuarios));
    }

    /** RN-08: un destinatario agotó sus intentos. */
    public function registrarEntregaFallida(string $usuario, int $intentos, \DateTimeImmutable $momento): void
    {
        $this->registrar('entrega fallida', 'sistema', $momento, "$usuario tras $intentos intentos");
    }

    /** RN-03: el nivel actual no tiene destinatarios disponibles. */
    public function registrarSinDestinatarios(\DateTimeImmutable $momento): void
    {
        $this->registrar('sin destinatarios', 'sistema', $momento, "nivel {$this->nivel}");
    }

    /** RN-05: sube un nivel. */
    public function subirNivel(string $motivo, \DateTimeImmutable $momento): void
    {
        $this->exigirAbierta();
        if ($this->nivel >= self::NIVEL_MAXIMO) {
            throw new ReglaDominioException('RN-05', 'La alerta ya está en el nivel máximo');
        }
        $this->nivel++;
        $this->registrar('escalada', 'sistema', $momento, "a nivel {$this->nivel} por $motivo");
    }

    /** RN-05: en el nivel máximo ya no hay a quién escalar; se marca una sola vez. */
    public function agotarEscalamiento(\DateTimeImmutable $momento): void
    {
        if ($this->escalamientoAgotado) {
            return;
        }
        $this->escalamientoAgotado = true;
        $this->registrar('escalamiento agotado', 'sistema', $momento, "nivel {$this->nivel}");
    }

    /** CU-04 / RN-06 / RN-07. Devuelve false si ya estaba acusada (idempotente). */
    public function acusar(string $usuario, \DateTimeImmutable $momento): bool
    {
        if ($this->estado === EstadoAlerta::ACUSADA || $this->estado === EstadoAlerta::CERRADA) {
            return false;
        }
        if (!$this->estado->esperaAcuse()) {
            throw new ReglaDominioException('RN-07', 'La alerta todavía no fue enviada a nadie');
        }
        if (!isset($this->destinatarios[$usuario])) {
            throw new ReglaDominioException('RN-07', "El usuario «{$usuario}» no fue notificado de esta alerta");
        }
        $this->estado = EstadoAlerta::ACUSADA;
        $this->registrar('acusada', $usuario, $momento);
        return true;
    }

    /** CU-06 / RN-09. */
    public function cerrar(string $usuario, string $nota, \DateTimeImmutable $momento): void
    {
        if ($this->estado !== EstadoAlerta::ACUSADA) {
            throw new ReglaDominioException('RN-09', 'Solo se puede cerrar una alerta acusada');
        }
        if (trim($nota) === '') {
            throw new ReglaDominioException('RN-09', 'La nota de cierre es obligatoria');
        }
        $this->estado = EstadoAlerta::CERRADA;
        $this->registrar('cerrada', $usuario, $momento, trim($nota));
    }

    /** Resumen para notificar (RNF-02): código de paciente, nunca nombre. */
    public function resumen(): string
    {
        return "[{$this->prioridad->value}] {$this->paciente->valor} · {$this->descripcion} · alerta " . substr($this->id, 0, 8);
    }

    public function estado(): EstadoAlerta { return $this->estado; }
    public function nivel(): int { return $this->nivel; }
    public function plazoDesde(): ?\DateTimeImmutable { return $this->plazoDesde; }
    public function escalamientoAgotado(): bool { return $this->escalamientoAgotado; }
    /** @return array<string,int> */
    public function destinatarios(): array { return $this->destinatarios; }
    /** @return EventoHistorial[] */
    public function historial(): array { return $this->historial; }

    private function exigirAbierta(): void
    {
        if ($this->estado === EstadoAlerta::ACUSADA || $this->estado === EstadoAlerta::CERRADA) {
            throw new ReglaDominioException('RN-06', 'Una alerta acusada o cerrada no se envía ni se escala');
        }
    }

    private function registrar(string $evento, string $actor, \DateTimeImmutable $momento, string $detalle = ''): void
    {
        $this->historial[] = new EventoHistorial(count($this->historial) + 1, $evento, $actor, $momento, $detalle);
    }
}
