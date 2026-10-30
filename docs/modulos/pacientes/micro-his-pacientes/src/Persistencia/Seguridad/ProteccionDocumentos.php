<?php

declare(strict_types=1);

namespace Pacientes\Persistencia\Seguridad;

use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\FabricaIdentificador;
use Pacientes\Dominio\Modelo\Identificador;

/**
 * R-04: el documento nunca se guarda en claro.
 *  - hash(): HMAC-SHA256 del canónico, para buscar y para el índice único (search_hash).
 *  - cifrar()/descifrar(): libsodium secretbox con nonce aleatorio (value_encrypted).
 * Las claves vienen de la configuración; el dominio no las conoce.
 */
final class ProteccionDocumentos
{
    public function __construct(private readonly string $claveHash, private readonly string $claveCifrado)
    {
        if (strlen($claveHash) < 32) {
            throw new \InvalidArgumentException('La clave de hash debe tener al menos 32 bytes.');
        }
        if (strlen($claveCifrado) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException('La clave de cifrado debe tener 32 bytes.');
        }
    }

    public static function desdeBase64(string $claveHash, string $claveCifrado): self
    {
        $hash = base64_decode($claveHash, true);
        $cifrado = base64_decode($claveCifrado, true);
        if ($hash === false || $cifrado === false) {
            throw new \InvalidArgumentException('Las claves deben estar en base64.');
        }

        return new self($hash, $cifrado);
    }

    public function hash(Identificador $documento): string
    {
        return hash_hmac('sha256', $documento->canonico(), $this->claveHash);
    }

    public function cifrar(Identificador $documento): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plano = $documento->tipo() . '|' . $documento->numero() . '|' . $documento->emisor();

        return base64_encode($nonce . sodium_crypto_secretbox($plano, $nonce, $this->claveCifrado));
    }

    /** @throws ErrorPersistencia si el valor está dañado o se cifró con otra clave */
    public function descifrar(string $cifrado): Identificador
    {
        $bruto = base64_decode($cifrado, true);
        if ($bruto === false || strlen($bruto) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new ErrorPersistencia('Documento cifrado ilegible');
        }
        $plano = sodium_crypto_secretbox_open(
            substr($bruto, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($bruto, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->claveCifrado,
        );
        if ($plano === false) {
            throw new ErrorPersistencia('Documento cifrado ilegible');
        }

        [$tipo, $numero, $emisor] = array_pad(explode('|', $plano, 3), 3, '');
        try {
            return FabricaIdentificador::crear($tipo, $numero, $emisor) ?? throw new ErrorPersistencia('Documento cifrado vacío');
        } catch (\InvalidArgumentException $e) {
            throw new ErrorPersistencia('Documento cifrado inválido', null, $e);
        }
    }
}
