# Micro-HIS — Catálogo de pruebas de laboratorio

Micro-monolito educativo en PHP 8.2+ vanilla (sin framework) para el proceso «alta y consulta de prueba con requisitos de muestra y vigencia». Se construye en las semanas 3, 4 y 5; cada semana documenta su avance en `../semana-0N/`.

| Carpeta | Contenido |
|---|---|
| `src/Domain/` | Entidades, objetos de valor, reglas e interfaz `LabTestRepository` |
| `src/Application/` | Casos de uso |
| `src/Persistence/` | Adaptadores de persistencia |
| `src/Presentation/` | Consola, MVC web y API |
| `sql/`, `docker/`, `docker-compose.yml` | PostgreSQL 16 en Docker, puerto 55441 |
| `datos/` | Pruebas ficticias de ejemplo (`HEM-001`, `GLU-001`) |
| `pruebas/` | Ejecutor propio, dobles y pruebas |

Arranque rápido: ver [`../semana-03/README.md`](../semana-03/README.md#3-cómo-ejecutarlo).
