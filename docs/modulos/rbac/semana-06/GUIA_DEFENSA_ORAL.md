# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Presentación:** [semana-06-defensa-rbac.pptx](presentacion/semana-06-defensa-rbac.pptx)

## 1. Tiempos sugeridos (10–14 minutos + preguntas)

| Diap. | Tema | Tiempo | Idea que debe quedar |
|:-:|---|:-:|---|
| 1 | Portada | 0:30 | Todo lo que digo se puede verificar: 67 pruebas, 0 fallas. |
| 2 | Actores y UML | 1:30 | La denegación es un caso de uso con evidencia, no un error. |
| 3 | Requisitos y OCP | 1:30 | Regla nueva = clase nueva; el decisor no cambia. |
| 4 | Capas y Repository | 2:00 | Las dependencias apuntan al Dominio; dos adaptadores intercambiables. |
| 5 | Contrato y frontera | 1:30 | El contrato coincide con el código; no hay microservicio porque lo medí. |
| 6 | Trazabilidad | 1:30 | Cada requisito llega hasta una prueba. |
| 7 | Cambio práctico | 2:30 | Revocar cambió la decisión **sin tocar** la autorización. |
| 8 | Decisión → evidencia | 1:00 | Cada decisión tiene archivo y prueba. |

Las notas del orador de cada diapositiva tienen el guion completo: en PowerPoint, Vista → Página de notas.

## 2. Cómo responder el cambio práctico

Si el docente pide **otro** cambio en vivo, sigue el mismo método del registro:

1. **Clasificar:** ¿es una regla nueva, un permiso nuevo, una ruta nueva o un dato nuevo?
2. **Ubicar en la cadena:** caso de uso → requisito → capa → puerto → contrato → prueba.
3. **Escribir la prueba primero:** copiar el patrón de REV-0x en `tests/Aplicacion/RevocacionPrueba.php`.
4. **Implementar en la capa correcta:**

   | Tipo de cambio | Dónde |
   |---|---|
   | Regla de negocio | `src/Dominio/Reglas/...` + una línea en `bootstrap/contenedor.php` |
   | Formato de la entrada | `Formularios/` |
   | Formato de la respuesta | `Vistas/` |
   | Persistencia | El puerto en `Dominio/Puertos` + **ambos** adaptadores + `contratoRepositorios()` |
   | Ruta | `Rutas::definir()` **y** `api/openapi.yaml` (si no, API-01/03 fallan) |

5. **Verificar:** `php tests/ejecutar.php`.

### Cambios probables y respuesta rápida

| Pedido | Respuesta |
|---|---|
| «Que no se pueda revocar al último administrador» | Regla nueva `ReglaNoUltimoAdministrador implements ReglaRevocacion`: cuenta los admins activos del hospital y devuelve 422 si queda solo uno. Se agrega a `PoliticaRevocacion` en `contenedor.php`. Ya está anotada como riesgo en el registro, §8. |
| «Que la revocación tenga fecha efectiva futura» | `valido_hasta = fecha` en lugar de `estado = revoked`: `Membresia::estaVigenteEn()` ya respeta `valido_hasta`, así que la autorización no cambia. Se agrega el campo `efectiva_desde` en el formulario y en el contrato. |
| «Un permiso nuevo para una operación nueva» | Agregar el permiso a `datos-demo` (php y sql), la ruta en `Rutas::definir()` con ese permiso y la operación en `openapi.yaml`. La autorización no cambia. |
| «Que el auditor vea también las revocaciones» | Ya puede: `GET /auditoria/eventos/{correlation_id}` devuelve cualquier tipo de evento, incluido `ROL_REVOCADO`. |
| «Bloquear operaciones fuera del turno» | `ReglaHorarioTurno implements ReglaAutorizacion` (OCP, semana 2). Es el único caso que agrega una regla a la autorización, y sigue sin modificar `AutorizarOperacion`. |

## 3. Preguntas teóricas probables

**¿Cómo conectas actores con la arquitectura?**
Cada actor inicia un caso de uso. Cada caso de uso tiene requisitos, y cada requisito vive en una clase de una capa y tiene una prueba. Ejemplo: Administrador → CU-07 → RF-11 → `RevocarRol` → `revocar()` → `POST /roles/revocaciones` → REV-01.

**¿Qué principio SOLID aplicaste y dónde se ve hoy?**
OCP. Las tres políticas (autorización, asignación y revocación) reciben una lista de reglas que implementan una interfaz. La revocación de hoy lo demuestra: agregué una política y tres reglas sin modificar las existentes.

**¿Por qué la decisión de autorización cambió sin tocar su código?**
Porque `AutorizarOperacion` decide con el **estado** de la membresía (`ReglaMembresiaVigente`). Al pasar la membresía a `revoked`, la misma regla deniega. Lo comprobé con `git diff --quiet` sobre esos archivos: cero cambios.

**¿Por qué cambiaste el puerto Repository? ¿No rompe eso el diseño?**
Agregar un método a una interfaz obliga a implementarlo en todos los adaptadores. Es el costo esperado de un cambio en el modelo de datos. La suite de contrato (CON-MEM-07, y CON-PDO-07 con base real) garantiza que ambos adaptadores lo cumplan igual. El resto del sistema no se enteró.

**¿Por qué POST `/roles/revocaciones` y no DELETE?**
Porque no se borra nada: la membresía queda `revoked` como evidencia. Además la revocación lleva un cuerpo (el motivo), y así no se exponen ids internos.

**¿Por qué el motivo es una regla de dominio y no del formulario?**
Que exista un motivo es formato (formulario). Que alcance para auditar (≥ 10 caracteres) es una política del hospital (dominio). Si mañana se revoca desde otro canal, la política se sigue aplicando.

**¿Cómo pruebas que el contrato está al día?**
Con API-01/03. En el cambio de hoy lo vimos en vivo: después de implementar la ruta, esas dos pruebas fallaron hasta que documenté la operación en `openapi.yaml`. Eso está guardado como evidencia (`cambio-practico-2-contrato-desactualizado.txt`).

**¿Qué es la matriz decisión→evidencia?**
Una tabla de 17 decisiones. Cada una con su motivo, el archivo donde vive y la prueba que falla si la decisión se rompe.

## 4. Demostración en vivo (2 minutos)

```bash
cd docs/modulos/rbac/micro-his-rbac
php tests/ejecutar.php          # mostrar el grupo «Casos de uso» con REV-01..06 y el total
php bin/demo.php                # leer en voz alta los pasos 6, 7 y 8
```

**Si el docente pide ver el rojo:** abrir `semana-06/evidencia/cambio-practico-1-antes-rojo.txt`.
