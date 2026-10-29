# Micro-HIS «Admisión hospitalaria y asignación de cama»

Prototipo en **PHP 8.2+ vanilla** (sin framework ni Composer) del flujo **admisión de paciente y asignación de una cama disponible**. Se inicia en la semana 3 y se continúa en las semanas 4 y 5. Todos los datos son **ficticios** y los pacientes se identifican solo por código.

## Capas

| Capa | Carpeta | Contiene | Depende de |
|---|---|---|---|
| Presentation | `src/Presentation/` | Consola (semana 3); más adelante MVC y API | Application |
| Application | `src/Application/` | Casos de uso `AdmitirPacienteYAsignarCama` y `ConsultarCamasDisponibles`, contexto de usuario, DTOs, puertos `Reloj` y `GeneradorId` | Domain |
| Domain | `src/Domain/` | Entidad `Admision`, objetos de valor, criterios de elegibilidad (OCP), excepciones e **interfaces Repository** | Nada |
| Persistence | `src/Persistence/Pdo/` | Adaptadores PDO que implementan las interfaces del dominio | Domain |

El **Composition Root** es `bootstrap.php`: es el único archivo que elige las clases concretas.

## Requisitos
- PHP 8.2 o superior con `pdo_pgsql`.
- Docker Desktop, para PostgreSQL 16 en el puerto **55443**.

## Levantar la base (desde esta carpeta)

```powershell
docker compose up -d
docker compose ps
```

Antes de seguir, `docker compose ps` debe mostrar el contenedor `micro-his-admision-pg` como **healthy**.

```powershell
php scripts/crear-esquema.php --base=micro_admision --datos-ficticios
php scripts/crear-esquema.php --base=micro_admision_test --recrear --datos-ficticios
```

## Pruebas

```powershell
php pruebas/ejecutar.php
php pruebas/ejecutar.php --grupo=unitarias
php pruebas/ejecutar.php --evidencia=../semana-03/evidencia/salida-pruebas.txt
```

- `pruebas/unitarias/` usa **dobles** (`pruebas/soporte/Dobles.php`) y no necesita base de datos.
- `pruebas/integracion/` usa la base de prueba `micro_admision_test`, que se recrea al empezar. Si no hay base, esas pruebas salen como `[OMITIDA]`, no como `[FALLA]`.
- `--evidencia` escribe la salida en UTF-8. No uses `>`: PowerShell 5 la guarda en UTF-16.
- Si la última línea dice `Fallas: 0`, todo pasó. El código de salida es 1 si alguna prueba falla.

## Consola

```powershell
php consola.php camas --usuario=usr-adm-01 --servicio=MEDICINA_INTERNA --tipo=GENERAL
php consola.php admitir --usuario=usr-adm-01 --paciente=PAC-00451 --servicio=MEDICINA_INTERNA --tipo=GENERAL --origen=EMERGENCIA --cama=c0000000-0000-4000-8000-000000000201
```

Los usuarios `usr-adm-01`, `usr-sup-01`, `usr-med-01` y `usr-adm-02` son ficticios (`datos-ficticios.php`). Para volver al estado inicial:

```powershell
php scripts/crear-esquema.php --base=micro_admision --recrear --datos-ficticios
```

## Datos y frontera con otros módulos
- Tablas propias: `admision`, `asignacion_cama` y `evento_admision`. Los nombres son **propuesta del Micro-HIS**: el esquema real de HOSPITAL no está publicado.
- `cama_catalogo` y `paciente_ref` **simulan** al catálogo de salas y camas (Josué) y al módulo Pacientes. El código del módulo solo les hace `SELECT`; los datos los carga el script.
- RN-01 lo garantiza el índice único parcial `ux_asignacion_cama_activa`; RN-02, `ux_admision_paciente_activa`; RN-05, el trigger `tr_evento_admision_inmutable`.
- La contraseña de `docker-compose.yml` es de uso local y no protege datos reales.
