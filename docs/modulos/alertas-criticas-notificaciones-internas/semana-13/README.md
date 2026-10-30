# Semana 13 — Calidad del software y revisiones técnicas formales

**Tarea:** preparar una revisión técnica formal (RTF) enfocada en reglas de dominio, contrato API, permisos y tenant, integridad de las alertas y dependencias estrictamente necesarias.
**Entregable:** plan de revisión, checklist de 14 ítems, roles, criterios de entrada y salida, y formato de hallazgos.

## 1. Plan de revisión

| Elemento | Definición |
|---|---|
| Objeto | `micro-his/src/` (Domain, Application, Persistence, Presentation/Api), `scripts/crear-esquema.php` y el contrato API de `semana-05/README.md` |
| Fuera del objeto | Plantillas MVC y prototipo (se revisaron en la semana 9) |
| Duración | Preparación individual de 60 min; reunión de 60 min como máximo; hasta 400 líneas por sesión, en 2 sesiones (Domain + Application, luego Persistence + API) |
| Material previo | Enlace a los PRs, `salida-pruebas.txt` de las semanas 3 a 5 y este checklist, enviados 48 h antes |
| Resultado | Acta con hallazgos y decisión: aceptar, aceptar con correcciones o revisar de nuevo |

## 2. Roles (PROPUESTA, por confirmar con el grupo)

| Rol | Persona | Responsabilidad |
|---|---|---|
| Autor | Erick Ramazzini (@ErickRamazzini) | Presenta el material, responde y corrige; no defiende, aclara |
| Moderador | Boris Quiroa (bquiroao) | Conduce, controla el tiempo, decide la salida |
| Revisor 1 | Josué Morales | Reglas de dominio e integridad (ítems 1–6) |
| Revisor 2 | Joshua García | Contrato API, permisos y tenant (ítems 7–11) |
| Registrador | Lo designa el moderador entre los revisores | Llena el formato de hallazgos |

## 3. Criterios de entrada
- Las pruebas del Micro-HIS están en verde y la salida está adjunta.
- Ningún PR del material supera 400 líneas.
- El contrato API está documentado (semana 5).
- Cada revisor recibió el material 48 h antes y registró su tiempo de preparación (al menos 45 min).

## 4. Checklist (14 ítems)

| # | Área | Pregunta de verificación |
|---|---|---|
| 1 | Dominio | ¿Cada RN-01 a RN-11 está implementada en Domain, y no en Application ni Presentation? |
| 2 | Dominio | ¿`acusar()` es idempotente y una alerta acusada nunca se escala (RN-06)? |
| 3 | Dominio | ¿Toda transición de estado agrega un `EventoHistorial` con actor y momento (RN-10)? |
| 4 | Dominio | ¿El tiempo se obtiene solo de `Reloj` (no hay `time()` ni `new DateTimeImmutable('now')` fuera de `RelojSistema`)? |
| 5 | Integridad | ¿La unicidad `(tenant, origen, id_origen)` existe en el dominio, en la base y en el adaptador InMemory? |
| 6 | Integridad | ¿Se guarda la alerta antes de notificar, y un fallo del canal no la pierde (RNF-05)? |
| 7 | API | ¿Las rutas, los códigos HTTP y el formato de error coinciden con `semana-05/README.md`? |
| 8 | API | ¿Todo error lleva `request_id`, y la cabecera `X-Request-Id` siempre se devuelve? |
| 9 | Permisos | ¿Cada acción consulta `Permisos` antes de llamar al caso de uso? ¿RF-08 se aplica en **todos** los canales? |
| 10 | Tenant | ¿El tenant sale solo del token o la sesión, y nunca del cuerpo o la URL? |
| 11 | Tenant | ¿Toda consulta del repositorio filtra por tenant (excepto `pendientesDeAcuse`, que usa solo el planificador)? |
| 12 | Persistencia | ¿Solo se usa `prepare()` + `execute()`, con `ERRMODE_EXCEPTION` y sin parámetros con nombre repetidos? |
| 13 | Dependencias | ¿Domain no importa nada de otras capas y Application no usa PDO? (verificable con `Select-String`, semana 3) |
| 14 | Dependencias | ¿El módulo no lee ni escribe tablas de otros módulos; solo recibe el código de paciente? |

## 5. Formato de hallazgos

| ID | Ítem | Archivo:línea | Descripción | Severidad (Crítica / Mayor / Menor) | Tipo (defecto / mejora / duda) | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| RTF-01 | 9 | `src/Presentation/Web/AlertaController.php` (`cerrar`) | La web MVC no valida el rol al cerrar; la API sí (RF-08) | Mayor | Defecto (= D-03) | Autor | Abierto |
| RTF-02 | 7 | `public/index.php` | Si la base no está disponible al arrancar, `/api/*` responde 503 en texto plano, no en el formato JSON del contrato | Menor | Defecto (= D-04) | Autor | Abierto |
| RTF-03 | 1 | `src/Application/NotificarNivel.php` | La decisión de escalamiento inmediato vive en Application (refactor candidato de la semana 7) | Menor | Mejora | Autor | Abierto |

*RTF-01 a RTF-03 son hallazgos que el autor encontró al preparar la revisión, como ejemplo del formato. Los hallazgos de la reunión real se agregan aquí.*

## 6. Criterios de salida
- **Aceptar:** ningún hallazgo crítico ni mayor abierto.
- **Aceptar con correcciones:** hallazgos mayores con responsable y fecha; el moderador verifica la corrección sin otra reunión.
- **Revisar de nuevo:** algún hallazgo crítico, o más de 3 mayores.
- En todos los casos, el acta se archiva en esta carpeta y los defectos pasan al registro de la semana 15.
