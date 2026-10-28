# Semana 15 — Pruebas de recepción de muestras

**Estudiante:** Lopez Perez, Mercedes Azucena.

**Flujo:** Recepción, identificación inequívoca y rechazo controlado de muestra.

## Base y alcance

Rama basada en d0eed56 de semana 12, que contiene el prototipo ejecutable de [semana 3](../semana-03/ejemplo/README.md). Se prueba su dominio, caso de uso, generador y repositorio PDO SQLite con datos ficticios. Integrar esa base antes de esta entrega. No se modifica código previo ni se procesan resultados clínicos.

La consigna incluye permisos y cadena de custodia: el prototipo no tiene contexto de operador/tenant ni historial append-only de custodia. Esas áreas se registran como cobertura pendiente, no como controles aprobados mediante dobles inventados. La unicidad de una muestra aleatoria de 100 códigos no garantiza ausencia matemática de colisiones; la restricción del almacén y prueba de colisión cubren el rechazo de duplicados.

## Comandos y resultados

PHP 8.2.33 con PDO SQLite, sin base externa:
```powershell
php docs/modulos/recepcion-muestras/semana-15/pruebas/run.php
php docs/modulos/recepcion-muestras/semana-15/pruebas/run.php --defectos
php docs/modulos/recepcion-muestras/semana-03/ejemplo/tests/run.php
```

- **13 casos funcionales exitosos:** 4 unitarios, 3 caja blanca y 6 caja negra.
- **3 defectos reproducidos**, no corregidos.
- **13 aserciones de regresión exitosas.**

El runner devuelve 1 ante resultado inesperado. PASS en --defectos confirma el defecto descrito. SQLite usa bases en memoria; no se altera ninguna base hospitalaria. Las fechas de recepción las genera el código original: se verifica existencia/persistencia, sin exigir una hora real exacta.

## Matriz requisito → prueba

Se conserva la trazabilidad de reglas R-01..R-08 de [semana 1](../semana-01/semana-01-uml-recepcion-muestras.md). Para expresar RF/RNF sin renumerar requisitos históricos: RF-REC-01 recepción ligada a orden/paciente/examen (R-01/02), RF-REC-02 decisión de conformidad/rechazo (R-03/05), RF-REC-03 código único (R-04), RF-REC-04 trazabilidad (R-07), RNF-REC-01 integridad ante fallo (R-06/08), RNF-REC-02 autorización/tenant (criterio de integración de esta consigna). Son alias locales de esta matriz, no requisitos globales nuevos.

| ID | Tipo | RF/RNF / regla | Esperado y observado |
|---|---|---|---|
| U01 | Unitaria | RF-REC-01/02 | Referencias vacías/blancos y motivo explícito vacío rechazados en ReceptionRequest. |
| U02 | Unitaria | RF-REC-02, R-03/05 | Motivo manual, falta de etiqueta, recipiente y sello de sangre acumulan cuatro razones. |
| U03 | Unitaria | RF-REC-03 | Formato SP- más 32 hex; muestra de 100 códigos sin duplicado, sin afirmar garantía estadística. |
| U04 | Unitaria | RF-REC-02 | Modelo rechaza REJECTED sin lista de razones y ACCEPTED con razones. D03 cubre la brecha dentro de la lista. |
| W01 | Caja blanca | RF-REC-02 | Rama sello: sangre no íntegra rechaza; orina/hisopo no usan esa condición específica del ejemplo. No se propone criterio clínico nuevo. |
| W02 | Caja blanca | RF-REC-02/03 | Ramas aceptación/rechazo generan un identificador y llaman save exactamente una vez. |
| W03 | Caja blanca | RNF-REC-01 | Fallo de save lanza excepción y no devuelve recepción exitosa ni guarda en el doble. No prueba presentación recuperable. |
| B01 | Caja negra | RF-REC-01/02 | Conforme se acepta y PDO conserva referencias; razones vacías. No comprueba existencia clínica de orden. |
| B02 | Caja negra | RF-REC-02 | Sin etiqueta o con recipiente no íntegro devuelve rechazo con razón persistida. |
| B03 | Caja negra | RF-REC-02 | Rechazo manual persiste motivo y no acepta. Catálogo real pendiente. |
| B04 | Caja negra | RF-REC-03, RNF-REC-01 | Colisión de PK provoca excepción sin sobrescribir: conserva primera orden y conteo 1. Recuperación UX no implementada. |
| B05 | Caja negra | RF-REC-02 | Tres tipos reconocidos conformes; enum desconocido no se admite. |
| B06 | Caja negra | RNF-REC-01 | PDO sin esquema falla sin devolver éxito. |
| D01 | Defecto de integración | RF-REC-01 | Orden ficticia inexistente aceptada al no consultar fuente autorizada. |
| D02 | Defecto de límite | RF-REC-03 | Generador defectuoso de espacios crea identificador inválido que el modelo acepta. |
| D03 | Defecto de dominio | RF-REC-02 | Constructor SpecimenReception admite REJECTED con reasons=[' ']. |
| G-PERM | Cobertura pendiente | RNF-REC-02 | No hay actor/tenant/autorización en ReceiveSpecimen; no se puede aprobar prueba de permisos con este contrato. |
| G-CUST | Cobertura pendiente | RF-REC-04 | Solo hay received_at, no actor/transferencia/eventos append-only; no se puede probar custodia íntegra inexistente. |

Caja negra usa solicitudes y resultados públicos y consulta del estado guardado. Caja blanca cuenta llamadas con dobles y recorre condiciones de política. No se declara porcentaje de cobertura sin instrumentación. Los criterios de recipiente/sello son los codificados por el ejemplo, no validación del protocolo clínico institucional.

## Defectos y brechas registrados

| ID | Severidad propuesta / estado | Reproducción / observado | Esperado / acción |
|---|---|---|---|
| REC-D01 | Alta para integración / abierto | --defectos D01 acepta ORDEN-NO-EXISTE y guarda registro. | Verificar existencia y relación orden/paciente/examen mediante fuente autorizada; retest positivo/negativo sin persistencia ante fallo. No demuestra un ataque a un HIS desplegado. |
| REC-D02 | Media / abierto | --defectos D02: identificador de tres espacios pasa constructor y guarda en doble. | Validar trim/formato conforme al contrato de identificador antes de persistir; probar generador inválido/colisión/identificador correcto. |
| REC-D03 | Media / abierto | --defectos D03: lista no vacía de motivo blanco satisface modelo aunque no explica rechazo. | Validar cada razón no vacía; preservar separación entre catálogo aprobado y observación. No inventar catálogo clínico. |
| G-PERM | Alta / pendiente | Caso de uso recibe solo solicitud; no conoce identidad/tenant. | Diseñar límite autorizado y probar rol denegado, sesión ausente y acceso cruzado antes de integrar. |
| G-CUST | Alta / pendiente | Esquema de semana 3 guarda fecha pero no eventos/actor/transferencia. | Acordar eventos con laboratorio y probar append-only e integridad; fecha aislada no es cadena de custodia. |
| G-RECUP | Media / pendiente | Colisión/falla PDO propaga excepción; B04/B06 comprueban integridad, no mensaje recuperable. | Definir traducción y recuperación sin recrear/duplicar muestra. |

Propietaria propuesta de corrección: autora del módulo, con reglas/catálogo/eventos acordados por laboratorio y revisión independiente por asignar. Cierre exige corrección y retest, no un PASS del modo reproducción. La entrega documenta cobertura parcial de permisos/custodia y no declara completa la integración de la consigna.

## Escenarios pendientes de integración

| Escenario | Criterio verificable futuro |
|---|---|
| Técnico permitido frente a rol denegado | Solo permitido recibe; denegado no lee ni escribe referencias ajenas. |
| Hospital H1 frente a H2 | Contexto confiable y bloqueo cruzado antes de persistir. |
| Recepción y transferencia | Eventos con actor, hora, referencia, origen/destino; historial no se reescribe. |
| Orden desconocida/asociación errónea | No aceptar ni guardar muestra como conforme. |
| Etiqueta fallida/resultado desconocido | No confirmar aceptación falsa; resolver operación antes de reintentar. |

Estos son criterios diseñados, no casos ejecutados sobre funcionalidades ausentes.

## Evidencia y defensa

[Suite](evidencia/salida-suite.txt) · [Captura](evidencia/captura-suite.png). [Defectos](evidencia/salida-defectos.txt) · [Captura](evidencia/captura-defectos.png). [Regresión](evidencia/salida-regresion.txt).

Las capturas representan las salidas PHP reales guardadas, renderizadas en reporte.html; no son pantallas clínicas ni una terminal interactiva. Mostrar casos de no conformidad, código, PK duplicada y persistencia; luego --defectos. Explicar por qué no hay una prueba de permisos/custodia exitosa cuando el contrato no los implementa.