<?php

declare(strict_types=1);

// Genera las dos claves que necesita ProteccionDocumentos, listas para copiar como variables de entorno.
//   php bin/generar-claves.php

echo 'PACIENTES_CLAVE_HASH=' . base64_encode(random_bytes(32)) . PHP_EOL;
echo 'PACIENTES_CLAVE_CIFRADO=' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) . PHP_EOL;
