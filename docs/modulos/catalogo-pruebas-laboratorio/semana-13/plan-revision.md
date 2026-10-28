# Plan de revisión técnica formal

## 1. Objetivo y alcance

Encontrar defectos, no corregirlos en la reunión, en lo que sostiene «alta y consulta de prueba con requisitos de muestra y vigencia»:

| Foco | Artefactos |
|---|---|
| Reglas de dominio (RN-02 a RN-07) | `micro-his/src/Domain/*`, `micro-his/src/Application/RegistrarPrueba.php` |
| Contrato API | `semana-05/contrato-api.md`, `micro-his/src/Presentation/Api/ApiLabTestController.php` |
| Permisos y tenant (RN-01) | `micro-his/src/Presentation/Api/Autorizacion.php`, filtros por `tenant_id` en `PdoLabTestRepository.php` |
| Integridad de pruebas, requisitos, vigencia y catálogo | `micro-his/sql/esquema.sql`, `PdoLabTestRepository::guardarNuevaVersion` |
| Dependencias estrictamente necesarias | `semana-03/diagramas/dependencias-capas.png`, `bootstrap.php`, `semana-04/README.md` (repositorio compartido) |

**Fuera de alcance:** estilo visual, wireframes y módulos ajenos (Órdenes, Recepción de muestras, Resultados).

## 2. Roles

| Rol | Persona | Responsabilidad |
|---|---|---|
| Autor | Erick Ramazzini | Prepara el paquete, responde dudas, no defiende ni corrige durante la reunión |
| Moderador | Boris Quiroa (propuesto) | Verifica los criterios de entrada, conduce la reunión, controla el tiempo y decide el resultado |
| Revisor 1 | Josué Morales (propuesto) | Reglas de dominio e integridad de datos |
| Revisor 2 | Joshua García (propuesto) | Contrato API, permisos y tenant |
| Secretario | Revisor 2 (propuesto) | Registra hallazgos en el formato de la sección 5 |

> Los nombres son una propuesta; se confirman con cada compañero antes de agendar.

## 3. Criterios de entrada

| # | Criterio |
|---|---|
| E-1 | Los PR de las semanas 3 a 5 están mergeados en `develop` |
| E-2 | `php pruebas/ejecutar.php` corre sin fallas inesperadas y su salida se adjunta al paquete |
| E-3 | Contrato API y diagramas actualizados con el código |
| E-4 | Paquete enviado a los revisores con 48 horas de anticipación |
| E-5 | Cada revisor dedicó al menos 45 minutos a la preparación individual con el checklist |

Si falta uno, el moderador reprograma.

## 4. Desarrollo (60 minutos)

| Tiempo | Actividad |
|---|---|
| 5 min | El moderador confirma criterios de entrada y roles |
| 10 min | El autor presenta el flujo y los artefactos, sin defenderlos |
| 35 min | Recorrido del checklist; cada revisor expone sus hallazgos; el secretario registra |
| 10 min | Clasificación de severidad y decisión: aceptado, aceptado con cambios o nueva revisión |

## 5. Formato de hallazgos

| Campo | Descripción |
|---|---|
| ID | `RT-NN` (consecutivo) |
| Ítem del checklist | `C-NN` relacionado |
| Artefacto y ubicación | Archivo y línea, o sección del documento |
| Descripción | Qué está mal y por qué, sin proponer la solución en la reunión |
| Severidad | Mayor (rompe una regla, la seguridad o la integridad) · Menor (no rompe, pero confunde o dificulta mantener) · Observación |
| Responsable y fecha | Quién corrige y cuándo |
| Estado | Abierto · Corregido · Verificado por el moderador |

Los hallazgos que resulten defectos de código se registran también como `D-NN` (semana 15).

## 6. Criterios de salida

| Resultado | Condición |
|---|---|
| Aceptado | Sin hallazgos mayores |
| Aceptado con cambios | Hallazgos mayores con corrección acordada; el moderador verifica la corrección sin nueva reunión |
| Nueva revisión | Tres o más hallazgos mayores, o uno que afecte la integridad de `lab_test` o el aislamiento por hospital |

La revisión se cierra cuando todos los hallazgos mayores están en estado «Verificado».
