# Semana 5 — Catálogo de medicamentos: cliente-servidor, API REST y frontera de microservicio

Evoluciona el MISMO Micro-HIS (`../micro-his/`) hacia un diseño cliente-servidor: la API JSON usa
los mismos casos de uso que la consola (S3) y la web MVC (S4).

## 1. Qué pide la tarea y dónde se cumple

| Pide | Dónde |
|---|---|
| Diseño cliente-servidor | `src/Presentation/Api/` + `diagramas/secuencia-api-alta.png` |
| Contrato API | `contrato-api.md` (endpoints, cuerpos, respuestas, errores, permisos, encabezados) |
| Propiedad de datos, comunicación, seguridad, resiliencia, observabilidad, consistencia | `frontera-microservicio.md`, secciones 2–7 |
| Evaluar frontera de microservicio sin dividir sin justificación medible | `frontera-microservicio.md`, secciones 1 y 8 (gatillos G1–G4) |
| Diagramas y migración razonada | `diagramas/frontera-microservicio.png`, `diagramas/migracion-etapas.png`, sección 9 |
| Plan de issue, rama, worktree y PR | `plan-issue-rama-worktree-pr.md` |
| Prototipo (sin infraestructura de producción) | API en el servidor de desarrollo de PHP; tokens ficticios |

## 2. Qué se agregó al Micro-HIS

| Archivo | Responsabilidad |
|---|---|
| `Api/KernelApi.php` | `X-Request-Id`, autenticación, rutas `/api/v1`, formato único de errores, registro JSON. |
| `Api/Autenticador.php`, `Identidad.php` | Token Bearer → usuario, rol y hospital (tenant). |
| `Api/Permisos.php` | Matriz rol → acciones; lo no listado es 403. |
| `Api/ApiMedicamentos.php` | Controlador JSON: permiso + un caso de uso + respuesta. |
| `Api/CuerpoJson.php`, `ErrorHttp.php`, `RespuestaJson.php` | 415/400, errores de protocolo y JSON uniforme. |
| `public/index.php` | Las rutas `/api/` van a la API; el resto, a la web. |
| `scripts/probar-api.php` | Prueba en vivo con evidencia UTF-8 (evita el problema de PowerShell 5 con cuerpos de error). |

## 3. Cómo ejecutar

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
php pruebas/ejecutar.php --evidencia=../semana-05/evidencia/salida-pruebas.txt
$env:CATALOGO_ALMACEN = "memoria"
php -S 127.0.0.1:8085 -t public
```

En otra terminal (misma carpeta):

```powershell
php scripts/probar-api.php --evidencia=../semana-05/evidencia/api-en-vivo.txt
```

Con `memoria` el resultado es reproducible (los datos vuelven al estado inicial en cada petición).
Con PostgreSQL, correr antes `php scripts/crear-esquema.php --reiniciar`.

**Resultados al preparar la entrega:** 69 pruebas, 69 correctas; prueba en vivo 9 de 9 respuestas
con el código esperado (PHP 8.3.6, servidor `php -S` con `memoria`).

## 4. Decisiones

- **La API no tiene reglas propias:** si mañana se separa en servicio, los casos de uso viajan igual.
- **El tenant es parte de la identidad**, no un parámetro: no hay forma de pedir datos de otro hospital.
- **404 y no 403 para otro hospital:** no se revela si el código existe en otro tenant.
- **No se separa en microservicio ahora:** no se cumple ningún gatillo medible (sección 8 de `frontera-microservicio.md`).

## 5. Riesgos y pendientes

- Los tokens son ficticios y viven en `config.php`; en el HIS se reemplazan por la autenticación real de Laravel.
- `SISTEMA_PRESCRIPCIONES`, la vista `v_medicamentos_vigentes` y los eventos son **propuestas** pendientes de acordar con `prescripciones-alergias`.
- Si la base falla al arrancar, el 503 del front controller no lleva `X-Request-Id` (solo los errores dentro de `KernelApi` lo llevan).
