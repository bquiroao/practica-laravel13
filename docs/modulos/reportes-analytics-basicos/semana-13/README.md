# Reportes y analytics básicos — Semana 13

**Tema:** calidad del software y revisiones técnicas formales.
**Tarea individual:** preparar una revisión técnica formal del módulo, enfocada en reglas de dominio, contrato API, permisos/tenant, integridad de indicadores, agregaciones, filtros, fuentes, precisión y visualización, y dependencias estrictamente necesarias para «cálculo y visualización trazable de un indicador hospitalario».
**Entregable:** plan de revisión, checklist de 12–15 ítems, roles de autor/moderador/revisores, criterios de entrada/salida y formato de hallazgos.

## 1. Plan de revisión

| Campo | Definición |
|---|---|
| Tipo | Inspección formal (revisión técnica con roles, preparación individual y reunión moderada) |
| Objetivo | Encontrar defectos en reglas, contrato, permisos e integridad del indicador **antes** de implementarlo en el SHI |
| Alcance | `micro-his/src/Domain`, `micro-his/src/Application`, `semana-05/contrato-api.md` y `openapi.yaml`, `semana-07/refactor.md` |
| Fuera de alcance | Estilo visual, nombres de variables, reportes que aún no tienen tablas (Laboratorio y Medicamentos) |
| Tamaño | Unas 600 líneas de código y 3 documentos; ritmo de 150–200 líneas por hora |
| Duración | Preparación individual de 60 minutos; reunión de 90 minutos como máximo |
| Fecha | Antes de iniciar el desarrollo del módulo en Laravel |

## 2. Roles

| Rol | Persona | Responsabilidad |
|---|---|---|
| Autor | Erick Rolando Ramazzini Muralles | Entrega el material, responde dudas y corrige; no defiende ni discute durante la reunión |
| Moderador y revisor de permisos | Boris Alexander Quiroa Orellana (RBAC: roles, permisos y protección de rutas) | Verifica los criterios de entrada, conduce la reunión, controla el tiempo y decide el resultado; además revisa permisos, roles y tenant (C-12, C-13) |
| Revisor 1 | Josué David Morales Ramírez (Catálogo de salas, wards y camas) | Revisa fuentes y consultas de ocupación sobre `ward`, `bed` y `bed_stay` (C-07, C-08, C-09) |
| Revisor 2 | Joshua Eduardo García Reyes (Reportes operativos exportables y filtros administrativos) | Revisa el contrato API, los filtros y la precisión de los valores (C-05, C-10, C-11) |
| Escriba | Josué David Morales Ramírez | Registra cada hallazgo con el formato de la sección 5 |

## 3. Criterios de entrada

- [ ] El material está mergeado en `develop` y enlazado en la invitación.
- [ ] `php pruebas/ejecutar.php` pasa todas las pruebas y la salida está adjunta.
- [ ] Los diagramas C4, de capas y de componentes están actualizados.
- [ ] Cada revisor recibió el material y este checklist al menos 24 horas antes.
- [ ] El autor revisó su propio trabajo con el checklist y corrigió lo evidente.

## 4. Checklist de revisión (14 ítems)

| # | Área | Pregunta | Referencia |
|---|---|---|---|
| C-01 | Reglas de dominio | ¿`Filtros` rechaza fechas inválidas y períodos invertidos? | RN-02 |
| C-02 | Reglas de dominio | ¿«Sin datos» se informa como estado y nunca como cero? | RN-06 |
| C-03 | Integridad | ¿Todo reporte garantiza que la suma del desglose es igual al numerador? | RN-07 |
| C-04 | Integridad | ¿La cantidad de registros de una parte es igual al valor de esa parte? | RN-07 nivel 3 |
| C-05 | Agregaciones | ¿Los promedios y tasas se desglosan por numerador y denominador y no se suman promedios? | Cambio práctico, semana 6 |
| C-06 | Agregaciones | ¿Los grupos con menos de 3 casos se agrupan como «Otro» sin cambiar el total? | RN-04 |
| C-07 | Filtros | ¿Desglose y registros usan exactamente los mismos filtros que el indicador? | RN-08 |
| C-08 | Fuentes | ¿Las consultas son de solo lectura y usan siempre sentencias preparadas? | RNF-04 |
| C-09 | Fuentes | ¿La ficha de trazabilidad nombra la fuente real de cada indicador? | RN-05 |
| C-10 | Precisión | ¿El cálculo usa valores completos y se redondea solo al mostrar, en un único lugar? | Refactor, semana 7 |
| C-11 | Contrato API | ¿Cada ruta documenta parámetros, respuestas y los errores 403, 404, 422 y 503 con la forma común? | `contrato-api.md` |
| C-12 | Permisos | ¿El Médico solo accede a Citas y solo a las suyas, también en desglose y registros? | RN-01, RN-08 |
| C-13 | Tenant y privacidad | ¿El hospital sale del token y los registros muestran al paciente solo con código? | RN-09, contrato API |
| C-14 | Dependencias | ¿El dominio no depende de PDO, HTTP ni de otros módulos, y solo se leen las tablas necesarias? | Tabla de dependencias, semana 3 |

## 5. Formato de hallazgos

| Campo | Contenido |
|---|---|
| ID | `RTF-NN` |
| Ítem del checklist | `C-NN` |
| Ubicación | Archivo y línea, o sección del documento |
| Descripción | Qué está mal, en una o dos oraciones, sin proponer la solución durante la reunión |
| Severidad | **Mayor:** produce un indicador incorrecto, expone datos o salta un permiso · **Menor:** afecta mantenibilidad o claridad · **Observación:** sugerencia |
| Responsable | Quién lo corrige (normalmente el autor) |
| Estado | Abierto → Corregido → Verificado por el moderador |

Ejemplo:

| ID | Ítem | Ubicación | Descripción | Severidad | Responsable | Estado |
|---|---|---|---|---|---|---|
| RTF-01 | C-12 | `PoliticaPermisos.php` | El permiso del Médico se valida por reporte, pero no se exige `medicoId` igual al usuario en Citas | Mayor | Autor | Abierto |

## 6. Agenda de la reunión (90 minutos)

| Minutos | Actividad |
|---|---|
| 0–5 | El moderador confirma criterios de entrada y roles |
| 5–15 | El autor presenta el alcance (sin defender decisiones) |
| 15–75 | Recorrido del checklist por áreas; el escriba registra hallazgos |
| 75–85 | Clasificación de severidad y responsables |
| 85–90 | El moderador decide el resultado |

## 7. Criterios de salida

| Resultado | Condición |
|---|---|
| Aceptado | 0 hallazgos mayores abiertos |
| Aceptado con correcciones | Solo hallazgos menores; el moderador verifica las correcciones sin otra reunión |
| Nueva revisión | 1 o más hallazgos mayores, o más de 25 % del material sin revisar |

Al cerrar se registran las métricas de la revisión: horas de preparación, hallazgos por severidad y hallazgos por cada 100 líneas.
