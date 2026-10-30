# Micro-HIS — Alertas críticas y notificaciones internas

Micro-monolito educativo en **PHP 8.2+ vanilla** (sin framework ni Composer) para el flujo
«detección, envío, acuse y escalamiento de una alerta crítica». Es el MISMO proyecto en las semanas 3, 4 y 5.

## Estructura

```
micro-his/
  autoload.php               autocarga PSR-4 mínima (AlertasCriticas\ -> src/)
  bootstrap.php              Composition Root: único lugar que elige adaptadores
  consola.php                entrada por consola (Presentation)
  config.php                 conexión local (credenciales ficticias de desarrollo)
  destinatarios-ficticios.php
  docker-compose.yml         PostgreSQL 16, contenedor shi-alertas-criticas-pg, puerto 55444
  scripts/crear-esquema.php  crea base y tablas (PHP, no .sh)
  src/Domain/                entidades, objetos de valor, reglas e interfaces (puertos)
  src/Application/           casos de uso
  src/Persistence/           adaptadores: PDO, notificador simulado, directorio, relojes
  src/Presentation/          ConsolaAlertas
  pruebas/                   ejecutor propio, dobles y pruebas (unidad, aplicación, integración, presentación)
```

## Puesta en marcha (PowerShell, desde `micro-his/`)

```powershell
docker compose up -d
docker compose ps                      # esperar STATUS = healthy
php scripts/crear-esquema.php          # base micro_alertas
php scripts/crear-esquema.php --prueba # base micro_alertas_test
php pruebas/ejecutar.php --evidencia=../semana-03/evidencia/salida-pruebas.txt
```

## Demostración por consola (reloj fijo con `--ahora`)

```powershell
php consola.php registrar --tenant=HOSP-GUA-01 --paciente=PAC-00451 --servicio=MED-INT `
  --origen=ingreso-resultados-laboratorio --id-origen=LAB-77801 --prioridad=CRITICA `
  "--descripcion=Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)" "--ahora=2026-10-29 10:00"
php consola.php escalar "--ahora=2026-10-29 10:14"   # 0 escaladas
php consola.php escalar "--ahora=2026-10-29 10:15"   # 1 escalada (a jefe.servicio01)
php consola.php acusar --id=<id> --usuario=jefe.servicio01 --tenant=HOSP-GUA-01 "--ahora=2026-10-29 10:18"
php consola.php historial --id=<id> --tenant=HOSP-GUA-01
Get-Content var/notificaciones-simuladas.log -Encoding UTF8
```

Códigos de salida: 0 correcto · 1 uso incorrecto · 2 regla incumplida · 3 no encontrada (o de otro tenant) · 4 error de persistencia.

**Notificaciones:** el adaptador es **simulado**. Escribe en `var/notificaciones-simuladas.log` (carpeta ignorada por git); no se envían correos, SMS ni push reales.
**Datos:** todo es ficticio (pacientes por código, personal por usuario ficticio).
