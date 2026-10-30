# Micro-HIS — Alergias clínicas y alerta visual en expediente

Micro-monolito educativo en **PHP 8.2+ vanilla** (sin framework ni Composer) del flujo «registro de alergia y emisión de alerta al consultar el expediente». Se inicia en la semana 3 y evoluciona en las semanas 4 (MVC, InMemory) y 5 (API REST).

## Requisitos

- PHP 8.2 o superior con `pdo_pgsql` y `mbstring`.
- Docker Desktop: PostgreSQL 16 en el contenedor `micro-alergias-pg`, puerto **55442** del equipo.
- Variables opcionales:
  - `ALERGIAS_DB_DSN`, `ALERGIAS_DB_USUARIO` y `ALERGIAS_DB_CLAVE` para la conexión. Por defecto: `127.0.0.1:55442`, `postgres`/`postgres`, que es una clave local de desarrollo.

## Estructura

```
micro-his/
├── autoload.php              ← PSR-4 mínimo (Alergias\ → src/)
├── bootstrap.php             ← Composition Root
├── consola.php               ← entrada de consola (semana 3)
├── docker-compose.yml        ← PostgreSQL 16, healthcheck, puerto 55442
├── scripts/                  ← esquema.php, datos-demo.php, crear-esquema.php
├── src/
│   ├── Dominio/              ← entidades, objetos de valor, reglas e interfaces Repository
│   ├── Aplicacion/           ← casos de uso
│   ├── Persistencia/         ← adaptadores PDO
│   └── Presentacion/         ← consola
└── pruebas/                  ← ejecutor propio, dobles y pruebas por semana
```

## Comandos (desde esta carpeta)

```powershell
docker compose up -d
docker compose ps                         # esperar «healthy»
php scripts/crear-esquema.php --datos
php pruebas/ejecutar.php                  # todas las pruebas
php pruebas/ejecutar.php --solo=semana03  # solo las de la semana 3
php consola.php alerta --paciente=PAC-00451
php consola.php registrar --paciente=PAC-00451 --alergeno=Penicilina --severidad=life_threatening --reaccion="Edema de glotis" --inicio=2019-03-02 --usuario=7a1e0000-0000-4000-8000-0000000000a1
```

`--evidencia=<ruta>` escribe la salida en UTF-8. No uses `>` de PowerShell 5, porque guarda en UTF-16.

**Las pruebas recrean el esquema de `micro_alergias_test`**: nunca apunten a una base con datos que importen.

Todos los datos son ficticios. Los pacientes se identifican solo por código.
