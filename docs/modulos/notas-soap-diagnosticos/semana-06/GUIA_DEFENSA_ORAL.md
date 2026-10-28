# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Presentación:** [semana-06-defensa-notas-soap.pptx](presentacion/semana-06-defensa-notas-soap.pptx)

## 1. Tiempos sugeridos (12 a 14 minutos y preguntas)

| Diap. | Tema | Tiempo | Idea que debe quedar |
|:-:|---|:-:|---|
| 1 | Portada | 0:30 | Todo lo que digo se puede verificar: 113 pruebas, 0 fallas. |
| 2 | Actores y casos de uso | 1:30 | El cambio ya estaba previsto en R-07 desde la semana 1. |
| 3 | Requisitos y SRP | 1:30 | Cada requisito tiene criterio y prueba; una clase por razón de cambio. |
| 4 | Capas y Repository | 2:00 | Las dependencias apuntan al Dominio; el cambio cayó en cada capa sin romper la regla. |
| 5 | Contrato y frontera | 1:30 | El contrato coincide con el código; no hay microservicio porque lo medí. |
| 6 | Trazabilidad | 1:30 | Cualquier requisito se sigue hasta una prueba. |
| 7 | Cambio práctico | 2:30 | La nota firmada no se tocó: la corrección es otra versión. Rojo → verde. |
| 8 | Matriz decisión → evidencia | 1:00 | Cada decisión tiene archivo y prueba. |

Las notas del orador de cada diapositiva tienen el guion completo. En PowerPoint se ven en Vista → Página de notas, o en la parte de abajo del modo Normal.

## 2. Cómo responder un cambio pedido en vivo

Si el docente pide **otro** cambio, se sigue el mismo método del registro:

1. **Clasificar el cambio:** ¿es una regla nueva, un permiso nuevo, una ruta nueva o un dato nuevo?
2. **Ubicarlo en la cadena:** caso de uso → requisito → capa → puerto → contrato → prueba.
3. **Escribir la prueba primero:** copiar el patrón de `tests/Aplicacion/CorreccionPrueba.php`.
4. **Implementarlo en la capa que corresponde:**

   | Tipo de cambio | Dónde |
   |---|---|
   | Regla clínica | La entidad en `src/Dominio/Modelo/` y su excepción en `Dominio/Excepciones/`; el código HTTP en el `Kernel` |
   | Forma de la entrada | `Presentacion/Entrada/` |
   | Forma de la respuesta | `Aplicacion/Resultados/` y las vistas |
   | Persistencia | El puerto en `Dominio/Puertos/`, **ambos** adaptadores y la prueba REP |
   | Ruta o permiso | `Rutas::definir()`, `config/config.php` **y** `api/openapi.yaml` (si no, fallan API-01, API-03 y API-04) |

5. **Verificar:** `php tests/ejecutar.php`.

### Cambios probables y respuesta rápida

| Pedido | Respuesta |
|---|---|
| «Que también se pueda corregir con `Idempotency-Key`» | Igual que en el registro: leer la cabecera en `FormularioCorreccionNota`, guardar la clave en la transacción de `CorregirNota` y devolver la corrección ya creada si se repite. Ya está anotado como pendiente en el registro, §8. |
| «Que al corregir, el diagnóstico anterior quede como `entered_in_error`» | Un método nuevo en `RepositorioDiagnosticos` (puerto y dos adaptadores) llamado dentro de la misma transacción de `CorregirNota`. El estado ya existe en la tabla `diagnoses` del HIS. |
| «Que el jefe de servicio también pueda corregir» | Un rol nuevo en `permisos_por_rol` con `notas_soap.corregir`, y en `CorregirNota` cambiar la condición «es el autor» por «es el autor o es jefe del servicio». Aclarar que la versión nueva seguiría a nombre del autor original, así que habría que decidir si se agrega un campo «corregida por». |
| «Que la corrección nazca firmada» | En `NotaSoap::corregir()`, devolver `->firmar($ahora)` y en `CorregirNota` guardar con `guardar()` (ya guarda `signed_at`). Explicar por qué no lo hice: el médico debe revisar antes de firmar, igual que en el registro. |
| «Mostrar solo la versión vigente en la línea de tiempo» | En `ListarNotasExpediente`, filtrar las notas que tienen una corrección (`correccionDe($id) !== null`), o agregar un filtro `?vigentes=true` en `FiltroNotasExpediente` y en el contrato. |
| «Validar el CIE-10 contra el catálogo» | Un puerto `CatalogoCie10` en el Dominio, su adaptador PDO y en memoria, y la verificación en los dos casos de uso antes de la transacción. |

## 3. Preguntas teóricas probables

**¿Cómo conectas los actores con la arquitectura?**
Cada actor inicia un caso de uso. Cada caso de uso tiene requisitos, cada requisito vive en una clase de una capa y tiene una prueba. Por ejemplo: Médico → CU-06 → RF-11 → `CorregirNota` → `correccionDe()` → `POST …/correcciones` → COR-01.

**¿Qué principio SOLID aplicaste y dónde se ve hoy?**
SRP, una sola razón para cambiar por clase. En la semana 2 lo mostré con un antes y después. Hoy se vuelve a ver: la corrección pudo haberse metido como un `if` dentro de `RegistrarNotaConDiagnostico`, pero eso le daría a esa clase dos razones para cambiar. Agregué `CorregirNota` y no toqué el registro ni la firma.

**¿Por qué no editar la nota firmada y ya?**
Porque es un documento clínico firmado. Si se edita, se pierde lo que el médico firmó y el expediente deja de ser evidencia confiable. Por eso R-07 dice desde la semana 1 que una corrección es una nota nueva. Es el mismo criterio que usa el estándar HL7 FHIR, donde un documento «reemplaza» a otro sin borrarlo.

**¿Por qué cambiaste el puerto Repository? ¿No rompe eso el diseño?**
Agregar un método a una interfaz obliga a implementarlo en todos los adaptadores. Es el costo esperado cuando el modelo de datos cambia. La prueba de contrato REP-09 corre contra los dos adaptadores y garantiza que se comportan igual. Los casos de uso que no usan `correccionDe()` no se enteraron.

**¿Qué pasa si dos médicos (o dos pestañas) corrigen la misma versión a la vez?**
La aplicación pregunta primero si ya hay una corrección, pero entre la pregunta y el `INSERT` puede colarse otra petición. Por eso la base tiene `UNIQUE (supersedes_id)`: una de las dos falla con un error de integridad. `CorregirNota` lo traduce a 409 `nota_ya_corregida` en lugar de 500. Lo prueban REP-09 e INT-11, y el paso 12 de la demo en PostgreSQL.

**¿Por qué el motivo es una regla de dominio y no del formulario?**
Que el campo venga como texto es forma, y eso lo revisa el formulario (400). Que el motivo alcance para entender la corrección es una regla clínica (422). Si mañana se corrige desde otro canal, la regla se sigue aplicando porque vive en `NotaSoap`.

**¿Cómo sabes que el contrato está al día?**
Por API-01, API-03 y API-04. En el cambio de hoy pasó de verdad: después de implementar la ruta, esas tres pruebas fallaron hasta que documenté la operación en `openapi.yaml`. Está guardado como evidencia en `cambio-practico-2-contrato-desactualizado.txt`.

**¿Por qué la versión del contrato es 1.1.0 y no 2.0.0?**
Porque el cambio es compatible: se agrega una operación y dos campos de salida que pueden ser `null`. Un cliente viejo sigue funcionando. Una v2 solo haría falta si quitara o cambiara algo que los clientes ya usan.

**¿Qué es la matriz decisión → evidencia?**
Una tabla de 19 decisiones. Cada una tiene su motivo, el archivo donde vive y la prueba que falla si la decisión se rompe.

**Si es tan modular, ¿por qué no es un microservicio?**
Porque lo medí en la semana 5. Por red, en mi equipo, cada llamada cuesta unos 35 ms contra 0,05 ms en proceso. Además, cada nota necesita tres validaciones de otros módulos y una transacción en la misma base. Ninguno de los criterios C1 a C5 para separarlo se cumple.

## 4. Demostración en vivo (3 minutos)

En PowerShell, desde la carpeta del micro-HIS:

```powershell
cd docs\modulos\notas-soap-diagnosticos\micro-his-notas-soap
php tests\ejecutar.php
```

Mostrar el grupo «Casos de uso» con COR-01 a COR-08 y el total. Después, la corrección por HTTP sin PostgreSQL:

```powershell
$env:NOTASSOAP_ALMACEN="memoria"; $env:NOTASSOAP_MEMORIA_ARCHIVO="$env:TEMP\notas-demo.ser"
Remove-Item $env:NOTASSOAP_MEMORIA_ARCHIVO -ErrorAction SilentlyContinue
php -S 127.0.0.1:8080 -t public
```

En otra ventana de PowerShell:

```powershell
$h = @{ "X-Tenant-ID" = "hosp-oriente"; "Authorization" = "Bearer token-mendez" }
$nota = Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8080/api/v1/notas-soap -Headers $h -ContentType "application/json; charset=utf-8" -InFile ejemplos\nota.json
Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:8080/api/v1/notas-soap/$($nota.clinical_note_id)/firma" -Headers $h
$cuerpo = @{ correction_reason = "Temperatura mal anotada: era 37.6"; subjetivo = "Tos y fiebre"; objetivo = "T 37.6"; analisis = "Neumonía"; plan = "Antibiótico"; diagnostico = @{ code = "J18.9"; description = "Neumonía no especificada"; type = "primary" } } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:8080/api/v1/notas-soap/$($nota.clinical_note_id)/correcciones" -Headers $h -ContentType "application/json; charset=utf-8" -Body ([Text.Encoding]::UTF8.GetBytes($cuerpo))
```

La última respuesta trae `version_no: 2` y `supersedes_id` con el id de la primera nota. Si se repite la última línea, PowerShell muestra el error 409 `nota_ya_corregida`.

**Si el docente pide ver el rojo:** abrir `semana-06/evidencia/cambio-practico-1-antes-rojo.txt`.
**Si pide ver que la base también protege:** abrir el paso 12 de `semana-06/evidencia/salida-api-correccion-postgresql.txt`.
