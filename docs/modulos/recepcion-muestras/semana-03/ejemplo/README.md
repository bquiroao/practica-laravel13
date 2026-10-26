# Micro-HIS de recepción — PHP CLI

Prototipo académico en PHP 8.2+ sin framework ni dependencias externas. Implementa el flujo de recepción de una muestra, su identificación, aceptación o rechazo controlado y persistencia mediante PDO con sentencias preparadas.

## Requisitos y ejecución

Se requiere PHP 8.2+ con PDO y un driver PDO instalado. El DSN predeterminado es SQLite; se puede configurar otro DSN compatible con el esquema mediante `DATABASE_DSN`, y credenciales opcionales con `DATABASE_USER` y `DATABASE_PASSWORD`.

```powershell
Get-ChildItem . -Filter '*.php' -Recurse | ForEach-Object { php -l $_.FullName }
php tests/run.php
php bin/reception.php --order=ORD-001 --patient=PAT-001 --exam=CBC --type=blood --label=yes --container=yes --seal=yes
```

La CLI registra la recepción antes de imprimir la confirmación. Si falla la conexión o el guardado, termina con código distinto de cero e informa el error; no muestra una confirmación de éxito. Un rechazo clínico controlado sí se registra y presenta su motivo. Para registrar una discordancia confirmada se puede agregar `--reject="Discordancia con la orden"`.

## Capas

- `src/Presentation/Cli`: interpreta argumentos y presenta resultados; no contiene reglas ni SQL.
- `src/Application`: coordina el caso de uso y define los puertos de identificador y repositorio.
- `src/Domain`: valida referencias, decide aceptación/rechazo y protege invariantes.
- `src/Persistence`: guarda mediante PDO; el SQL usa parámetros enlazados.
- `src/Infrastructure/Identity`: genera identificadores para el puerto de aplicación.
- `database/schema.sql`: esquema mínimo de recepción.
- `tests/run.php`: dobles para probar camino feliz, rechazo de dominio y fallo de persistencia sin una base real.

## Límite del prototipo

Las referencias de orden, paciente y examen se capturan desde la CLI y se guardan para trazabilidad; no se consulta el EMR, no se crean órdenes o pacientes, no hay autenticación, impresión física ni reglas clínicas por examen. Para ejecutar el adaptador PDO se necesita el driver correspondiente (por ejemplo, `pdo_sqlite` si se usa el DSN predeterminado). Las pruebas usan un repositorio en memoria, por lo que no requieren driver.
