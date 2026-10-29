# Semana 13 — Revisión técnica formal (RTF)

Módulo: **Alergias clínicas y alerta visual en expediente** · Flujo: «registro de alergia y emisión de alerta al consultar el expediente»

## 1. Plan de revisión

| Elemento | Definición |
|---|---|
| Objetivo | Encontrar defectos en reglas de dominio, contrato API, permisos y tenant, integridad de alergias y alertas, y dependencias, **antes** de las pruebas formales de la semana 15 |
| Producto revisado | `micro-his/src/Dominio`, `src/Aplicacion`, `src/Persistencia`, `src/Presentacion/Api`, `scripts/esquema.php`, `semana-05/contrato-api.md` (1 182 líneas de PHP, contadas con `wc -l`) |
| Fuera de la revisión | Módulos ajenos (pacientes, expediente, prescripciones), el prototipo de la semana 11 y la presentación |
| Tipo | Inspección formal con checklist; 1 reunión de 60 min como máximo; preparación individual de 60 min |
| Documentos de referencia | RN-01 a RN-11 (semana 1), RF/RNF y CA (semana 2), contrato v1 (semana 5) |
| Calendario propuesto | Preparación: 2 días hábiles después de enviar el paquete. Reunión: el día 3. Correcciones: 3 días. Verificación por el moderador: el día 7 |

## 2. Roles (**propuesta**: depende de lo que acepte cada persona)

| Rol | Persona propuesta | Responsabilidad |
|---|---|---|
| Autor | Erick Ramazzini | Prepara el paquete, responde preguntas y corrige; **no** defiende durante la reunión |
| Moderador | Boris Quiroa (líder, revisa los PR) | Verifica los criterios de entrada, conduce la reunión, decide la salida y verifica las correcciones |
| Revisor 1 — dominio y datos | Josué Morales | Ítems 1 a 6 y 13 |
| Revisor 2 — API y seguridad | Joshua García | Ítems 7 a 12 y 14 |
| Registrador | Lo designa el moderador entre los revisores | Llena la tabla de hallazgos durante la reunión |

## 3. Criterios de entrada

1. `php pruebas/ejecutar.php` → 44/44 con PostgreSQL en el equipo del autor. La salida se adjunta.
2. `git diff --stat` del paquete con menos de 1 200 líneas; si es más, la revisión se parte.
3. Están disponibles los documentos de referencia y este checklist.
4. Cada revisor confirma que tuvo 60 min de preparación y trae sus hallazgos anotados.

## 4. Checklist (15 ítems)

| # | Área | Pregunta | Dónde mirar |
|---|---|---|---|
| 1 | Reglas | ¿Cada RN-01 a RN-06 está en el Dominio y no en controladores ni en la API? | `Alergia::registrar`, `Alergeno` |
| 2 | Reglas | ¿La normalización del alérgeno en PHP es **la misma** que la del índice único? (mayúsculas, espacios, código o nombre) | `Alergeno::clave`, `esquema.php`, `existeActivaEquivalente` |
| 3 | Reglas | ¿Las reglas de nivel detectan lo que el personal escribe de verdad (tildes, sinónimos)? | `Reglas/*` |
| 4 | Integridad | ¿Toda escritura falla sin dejar datos a medias? | `PdoRepositorio*`, T-12, T-44 |
| 5 | Integridad | ¿Un registro simultáneo duplicado termina en 409 y nunca en dos filas? | 23505 → `AlergiaDuplicada` |
| 6 | Integridad | ¿Ninguna ruta permite borrar alergias? | Trigger y repositorios |
| 7 | Contrato | ¿Cada código de error del contrato existe en el código, y al revés? | `contrato-api.md` vs `TraductorErrores`, `KernelApi` |
| 8 | Contrato | ¿Los tipos JSON incorrectos se rechazan con 400 en lugar de ignorarse? | `KernelApi::registrar` |
| 9 | Permisos | ¿Cada operación revisa el permiso **antes** de tocar datos? | `KernelApi::resolver` |
| 10 | Tenant | ¿El hospital sale solo del token? ¿Un encabezado distinto se rechaza? | T-38 |
| 11 | Seguridad | ¿Toda consulta usa `prepare()` + `execute()`, sin concatenar valores? | `grep -n "query(\|exec(" src` |
| 12 | Privacidad | ¿Registros, errores y respuestas evitan nombres, alérgenos o reacciones fuera de lo necesario? | Registro de acceso, mensajes |
| 13 | Alerta | ¿Ningún camino muestra «sin alergias» cuando la lectura falló o no hay registros? | Vistas, consola, API |
| 14 | Reconocimiento | ¿Un reconocimiento viejo puede validar una alerta nueva? | `ReconocerAlerta`, huella |
| 15 | Dependencias | ¿El Dominio y la Aplicación dependen solo de lo estrictamente necesario? (sin PDO ni Presentación) | Comandos de la semana 3 |

## 5. Formato de hallazgos

| Campo | Contenido |
|---|---|
| ID | RT-NN |
| Ítem | # del checklist |
| Ubicación | archivo:línea o sección |
| Descripción | Qué pasa, con un ejemplo reproducible |
| Severidad | **Mayor:** puede ocultar o rebajar una alergia, o romper permisos o tenant. **Menor:** error claro, pero sin riesgo clínico. **Observación:** estilo o mejora |
| Acción | Corregir, aceptar como riesgo, o pasar a prueba (semana 15) |
| Responsable y fecha | — |
| Estado | Abierto, Corregido o Verificado |

### Ejemplo: autorrevisión del autor antes de enviar el paquete

| ID | Ítem | Ubicación | Descripción | Severidad | Acción |
|---|---|---|---|---|---|
| RT-01 | 3 | `ReglaReaccionAnafilactica.php` | Busca «anafila»: detecta «Anafilaxia» pero **no** «reacción anafiláctica» por la tilde de «anafilá». Una alergia moderada con esa reacción queda MODERADA en lugar de CRÍTICA | **Mayor** | Pasar a prueba en la semana 15 y corregir |
| RT-02 | 2 | `Alergeno::clave`, índice real | La clave usa el código si existe y, si no, el nombre. «J01CA04 + Penicilina» y luego solo «Penicilina» no se detectan como equivalentes: quedan dos activas de la misma sustancia | Menor (heredado del índice real de HOSPITAL) | Pasar a prueba y proponer al líder una regla común |
| RT-03 | 8 | `KernelApi::registrar` | `"reaccion": 123` o `"tipo": ["x"]` se descartan en silencio: se guarda la alergia **sin** reacción y con tipo `allergy` | Menor | Pasar a prueba; responder 400 `FORMATO_INVALIDO` |
| RT-04 | 9 | `bootstrap.php`, `armarWeb()` | La entrada MVC no autentica (usuario demo fijo, DEC-07; ya es H-10) | Mayor si se despliega | Aceptado como límite de la demo; la API sí autentica |

## 6. Criterios de salida

- Ningún hallazgo **Mayor** abierto sin una acción decidida (corregir o una prueba que lo documente con responsable).
- Todos los hallazgos registrados con su responsable y fecha.
- El moderador verifica las correcciones y vuelve a correr `php pruebas/ejecutar.php`, sin fallas nuevas.
- Si hubo más de 5 hallazgos Mayores, se repite la revisión del área afectada.
