# Semana 13 — Calidad del software y revisiones técnicas formales

Módulo: **Admisión hospitalaria y asignación de cama** · Flujo: **admisión de paciente y asignación de una cama disponible**

Plan de una **revisión técnica formal (RTF)** del módulo. El checklist, la pre-revisión del autor y el formato de hallazgos están en `checklist-y-hallazgos.md`.

## 1. Objetivo y alcance

| Se revisa | Fuente |
|---|---|
| Reglas de dominio RN-01 a RN-06 | `micro-his/src/Domain/`, `src/Application/` |
| Contrato API v1 | `semana-05/contrato-api.md` y `src/Presentation/Api/ApiKernel.php` |
| Permisos y tenant | `ContextoUsuario`, `AutenticadorTokens`, los repositorios (filtro por `tenant_id`) |
| Integridad y concurrencia (admisiones, camas, asignación) | `PdoAdmisionRepository`, `EsquemaMicroHis` (índices y trigger) |
| Trazabilidad clínica | `EventoAdmision`, `evento_admision`, `ConsultarTrazabilidad` |
| Dependencias **estrictamente necesarias** | Puertos hacia el catálogo y Pacientes; `prueba-dependencias.php` |

**Fuera de alcance:** el estilo visual de las pantallas (ya evaluado en la semana 9) y los módulos ajenos (catálogo, pacientes, dashboard).

## 2. Roles (propuesta, por confirmar con el líder)

| Rol | Persona propuesta | Responsabilidad |
|---|---|---|
| Autor | Erick Ramazzini | Prepara el material, responde preguntas y corrige; **no** defiende durante la inspección |
| Moderador | Boris Quiroa (líder) | Verifica los criterios de entrada, dirige la reunión, cuida el tiempo y decide la salida |
| Revisor 1 | Josué Morales | Frontera con el catálogo de camas: solo lectura, contrato de ocupación, RN-04 |
| Revisor 2 | Joshua García | API, permisos y tenant; manejo de errores |
| Registrador | Uno de los revisores, por turno | Anota los hallazgos en el formato de la sección 5 |

## 3. Proceso y calendario (propuesta)

| Paso | Duración | Producto |
|---|---|---|
| 1. Planificación: el moderador confirma los criterios de entrada | 15 min | Fecha y material |
| 2. Preparación individual con el checklist | 60 min por revisor | Hallazgos preliminares |
| 3. Reunión de inspección | 60 min como máximo | Lista de hallazgos con severidad |
| 4. Corrección por el autor | Según severidad | Un PR por hallazgo mayor |
| 5. Seguimiento: el moderador verifica | 15 min | Cierre o nueva revisión |

## 4. Criterios de entrada y de salida

**Entrada** (todos deben cumplirse):
1. Los PR de las semanas 3 a 5 están mergeados en `develop`.
2. `php pruebas/ejecutar.php` da `Fallas: 0` con la base de prueba, y se adjunta la salida.
3. El contrato API y el esquema están actualizados con el código.
4. El autor completó la pre-revisión (`checklist-y-hallazgos.md`, columna «Autor»).
5. El material se distribuyó al menos 48 h antes.

**Salida:**
1. Cero hallazgos **críticos** abiertos.
2. Los hallazgos **mayores** tienen un PR mergeado o una decisión documentada del moderador.
3. Los **menores** están registrados como tareas en Odoo.
4. Las pruebas siguen con `Fallas: 0` después de las correcciones.
5. El moderador firma el acta. Si se corrigió más del 20 % del código revisado, se programa una segunda revisión.

## 5. Formato de hallazgos

| Campo | Contenido |
|---|---|
| ID | `RT-NN` |
| Ítem del checklist | C-NN |
| Ubicación | Archivo y línea, o sección del documento |
| Descripción | Qué se observa y por qué incumple |
| Severidad | **Crítico** (riesgo clínico, de datos o de RN-01) · **Mayor** (incumple un requisito) · **Menor** (mantenibilidad o estilo) |
| Acción acordada | Corrección o decisión |
| Responsable y fecha | — |
| Estado | Abierto / En corrección / Cerrado (con el PR) |

En la inspección **se registran** los problemas; no se discute cómo resolverlos.
