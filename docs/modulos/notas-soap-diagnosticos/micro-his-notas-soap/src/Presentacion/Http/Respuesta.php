<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

/** Respuesta HTTP inmutable. La arma una Vista; el Kernel solo la envía. */
final class Respuesta
{
    /** @param array<string, mixed> $cuerpo cuerpo JSON (vacío si la respuesta es HTML) */
    public function __construct(
        public readonly int $estado,
        public readonly array $cuerpo,
        public readonly ?string $html = null,
    ) {
    }

    /** @param array<string, mixed> $cuerpo */
    public static function json(int $estado, array $cuerpo): self
    {
        return new self($estado, $cuerpo);
    }

    public static function html(int $estado, string $html): self
    {
        return new self($estado, [], $html);
    }

    public static function error(int $estado, string $codigo, string $mensaje): self
    {
        return new self($estado, ['error' => ['code' => $codigo, 'message' => $mensaje]]);
    }

    public function esHtml(): bool
    {
        return $this->html !== null;
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        if ($this->html !== null) {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->html;

            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
