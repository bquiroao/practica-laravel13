<?php

declare(strict_types=1);

namespace E2e\Dominio\Modelo;

use E2e\Dominio\Excepciones\ReglaRechazada;

/**
 * Semana 6 · CU-11, R-11: despliegue reproducible. Se instala un digest SOLO si la última evaluación del gate de esa
 * versión es APROBADO, está vigente y es sobre ESE digest; la guía de despliegue es versionada; si la verificación
 * posterior (smoke E2E) falla, el despliegue queda REVERTIDO a lo que estaba desplegado en ese entorno.
 */
final readonly class Despliegue
{
    public const DESPLEGADO = 'DESPLEGADO';
    public const REVERTIDO = 'REVERTIDO';

    public function __construct(
        public ?int $id,
        public string $tenant,
        public string $version,
        public string $digest,
        public string $entorno,
        public string $guia,
        public string $operador,
        public string $estado,
        public ?string $revertidoA,
        public \DateTimeImmutable $en,
    ) {
    }

    /**
     * @param list<Decision>  $evaluaciones las del gate de la versión, en orden: cuenta la ÚLTIMA (semana 7: lo decide el dominio)
     * @param Despliegue|null $vigente   lo que hoy está DESPLEGADO en ese entorno (para revertir)
     */
    public static function registrar(Version $version, array $evaluaciones, string $digest, string $entorno, string $guia, string $smoke,
        ?Despliegue $vigente, \DateTimeImmutable $ahora, int $vigenciaHoras, string $operador): self
    {
        $campos = [];
        if (!preg_match('/^[a-z][a-z0-9-]{1,30}$/', $entorno)) {
            $campos[] = 'entorno';
        }
        if (!preg_match('/^guia-\d{4}\.\d+$/', $guia)) {
            $campos[] = 'guia';
        }
        if (!in_array($smoke, ['PASA', 'FALLA'], true)) {
            $campos[] = 'smoke';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }
        $ultima = $evaluaciones === [] ? null : $evaluaciones[array_key_last($evaluaciones)];
        if ($ultima === null || $ultima->valor !== Decision::APROBADO) {
            throw new ReglaRechazada('GATE_NO_APROBADO');
        }
        if ($digest !== $version->digest || $ultima->digest !== $version->digest) {
            throw new ReglaRechazada('DIGEST_DISTINTO');
        }
        if ($ahora->getTimestamp() - $ultima->evaluadaEn->getTimestamp() > $vigenciaHoras * 3600) {
            throw new ReglaRechazada('APROBACION_VENCIDA');
        }
        $revertido = $smoke === 'FALLA';

        return new self(null, $version->tenant, $version->numero, $digest, $entorno, $guia, $operador, $revertido ? self::REVERTIDO : self::DESPLEGADO,
            $revertido ? $vigente?->version : null, $ahora);
    }

    public function conId(int $id): self
    {
        return new self($id, $this->tenant, $this->version, $this->digest, $this->entorno, $this->guia, $this->operador, $this->estado, $this->revertidoA, $this->en);
    }

    /** Semana 15 (DEF-01): ventana en la que un registro idéntico se toma como reintento del operador, no como otro despliegue. */
    public const VENTANA_REINTENTO_MIN = 15;

    /**
     * DEF-01 (RT-01 de la revisión técnica): el operador reintenta cuando se le corta la red. Mismo digest, entorno, guía y
     * resultado del smoke, dentro de la ventana, es el MISMO despliegue. Fuera de la ventana, o con algo distinto, es otro.
     */
    public static function esReintento(?self $ultimo, string $digest, string $entorno, string $guia, string $smoke, \DateTimeImmutable $ahora): bool
    {
        return $ultimo !== null && $ultimo->digest === $digest && $ultimo->entorno === $entorno && $ultimo->guia === $guia
            && $ultimo->estado === ($smoke === 'PASA' ? self::DESPLEGADO : self::REVERTIDO)
            && $ahora->getTimestamp() - $ultimo->en->getTimestamp() <= self::VENTANA_REINTENTO_MIN * 60;
    }
}
