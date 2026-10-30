# Matriz decisión → RF/RNF → evidencia (semanas 7–12)

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas»

Continúa la [matriz de la semana 6](../semana-06/matriz-decision-evidencia.md) (D-01..D-21). Cada decisión se enlaza con el requisito que la motiva y con evidencia **ejecutable**: una prueba, una mutación, un paso de paridad o una comprobación del recorrido.

**Estado verificable hoy:**

| Verificación | Resultado |
|---|---|
| `php tests/ejecutar.php` con PostgreSQL 18 | 98/98 |
| `php tests/mutaciones.php` | 26/26 |
| `node semana-12/herramientas/paridad.cjs` | 26 pasos y la semilla, 0 diferencias |
| `node semana-12/herramientas/recorrido.cjs` | 58 comprobaciones, 0 fallas |

## 1. Requisitos que se formalizan en esta evaluación

Las semanas 8 a 11 tomaron decisiones de UX que conviene volver requisitos para poder verificarlas:

| ID | Requisito | Origen |
|---|---|---|
| RNF-12 | Ninguna lectura se dispara sin una acción explícita del usuario | Semana 8 (R-08 en la UI) |
| RNF-13 | Ningún dato de la consulta (fechas, usuario, recurso, cursor) en la URL ni en el almacenamiento del navegador | Semanas 8 y 10 (R-D1) |
| RNF-14 | WCAG 2.2 AA en escritorio y en 320–430 px: objetivos táctiles ≥ 44 px y campos ≥ 16 px | Semanas 9 y 10 |
| RNF-15 | El prototipo responde igual que el backend (paridad) | Semana 11 |
| RF-18..21, RNF-11 | Filtro por recurso | Semana 12 ([registro](registro-cambio-solicitado.md)) |

## 2. Matriz

| ID | Decisión | RF / RNF / regla | Evidencia | Sem. |
|---|---|---|---|:-:|
| D-22 | `Alcance` es el único dueño de la decisión de tenant en las lecturas | RF-02, RF-13, R-05 | REF-04, M22; Alc 17 → 4 en la Aplicación (`bin/acoplamiento.php`) | 7 |
| D-23 | Los parámetros de consulta los interpreta `CatalogoFiltros` (Dominio), no el caso de uso | RF-08, RNF-01 | REF-01, REF-05, M24 | 7 |
| D-24 | El permiso de lectura según el alcance está en `AutorizacionLectura` | RF-02, CU-07 | REF-06, M23, API-01 | 7 |
| D-25 | Refactor sin ampliar: caracterización antes de mover código | Tarea S7 | REF-01..03 sobre el código anterior; demos idénticas | 7 |
| D-26 | La inmutabilidad se ve: sin editar, borrar ni reparar; correcciones enlazadas | RF-06, R-02, RF-14..17 | Recorrido: «Editar», «Borrar» y «Reparar» no aparecen (pasos 04, 16) | 8 |
| D-27 | Sin carga automática; cerrar el detalle no vuelve a consultar | RNF-12, R-08 | Recorrido 08: la misma lista (58) sin una consulta nueva | 8, 11 |
| D-28 | Un 404 idéntico para «no existe» y «es de otro hospital» | RF-13, R-05 | Paridad 6–7; REF-02 | 8 |
| D-29 | Confirmar la red **al buscar**, no al marcar el radio | CU-07, WCAG 3.2.2 (H-01) | Recorrido: ↓ no abre diálogo; foco en «Cancelar» | 9, 11 |
| D-30 | Renovar la sesión sobre la página, sin guardar datos | RNF-13, WCAG 3.3.8 (H-02) | Recorrido 09–10; 0 claves en el almacenamiento | 9, 11 |
| D-31 | Paleta `#5F6670` / `#7D838A` | RNF-14, WCAG 1.4.3/1.4.11 | `contraste.py` (exit 0); axe sin `color-contrast` | 9 |
| D-32 | «Verificar» nunca se deshabilita; resumen de errores | WCAG 3.3.1 (H-07) | Recorrido 14 | 9, 11 |
| D-33 | Móvil: tarjetas por prioridad, atajos de período, filtros plegados | RNF-14 | `verificar-responsive.cjs`: 39 vistas, 0 fallas | 10 |
| D-34 | Sin reintentos automáticos de lecturas | RNF-12, R-08 | `reintento.php`: cada reintento que llega deja una meta-auditoría | 10 |
| D-35 | Verificación interrumpida: «no sabemos si terminó»; repetir es seguro | RF-07, RF-11 | `reintento.php`; estado `incierto` (M4) | 10 |
| D-36 | ALTERADA guía sin empeorar: contacto, informe y nada de reparar | RF-07, H-08 | Paridad 18; recorrido 16 | 11 |
| D-37 | Una API simulada con paridad probada contra el kernel | RNF-15 | `paridad.cjs`: 0 diferencias; el control con un defecto inyectado da 3 | 11 |
| D-38 | Filtro por recurso como extensión OCP | RF-18..20 | REC-01..03, M25, M26, paridad 22–25 | 12 |
| D-39 | Guarda contrato = código para los parámetros de consulta | RNF-11 | REC-04: falló con el código sin documentar ([evidencia](evidencia/cambio-2-codigo-sin-contrato.txt)) | 12 |

## 3. Cómo leer la matriz en la defensa

1. Elegir una decisión.
2. Ejecutar su evidencia.
3. Si es una prueba, aplicar su mutación y verla fallar. Ejemplo para D-22: en `Alcance::incluye()`, `return true;` (M22) hace fallar REF-04, APP-07 y REF-02.
4. Si es de UX, abrir el prototipo y repetir el paso del recorrido.

## 4. Riesgos que la matriz no cierra

| Riesgo | Estado |
|---|---|
| DEF-07-01: el cursor acepta fechas imposibles | Registrado; se corrige en la semana 15 |
| Los sellos del prototipo son una imitación | Declarado: el prototipo no detecta contenido alterado; el backend sí |
| No hay prueba con lectores de pantalla reales | Declarado: axe y las pruebas de teclado no lo reemplazan |
| El recurso es texto libre del emisor | Propuesto para la revisión técnica (semana 13) |
