# Registro del cambio solicitado — Semana 12

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

## 1. El pedido

| | |
|---|---|
| **Quién lo pide** | Responsable de producto (actor de la semana 1, hasta ahora sin pantalla en el prototipo) |
| **Texto** | «Antes de liberar quiero saber qué **requisito** queda en riesgo, no qué caso falló. Y no quiero poder cerrar nada por error.» |
| **Por qué tiene sentido** | El Responsable de producto decide sobre funcionalidades, y las funcionalidades se miden en requisitos. Ver «TC-107 falla» lo obliga a ir a la matriz para saber que eso significa «RF-07 en riesgo». |
| **Requisitos** | RF-11 (trazabilidad desde el requisito: la interfaz no la mostraba, ver la [matriz](matriz-decision-requisito-evidencia.md)) y RF-14 (permiso por operación) |

## 2. Análisis de impacto (antes de tocar código)

| Pieza | ¿Cambia? | Por qué |
|---|---|---|
| Contrato API 1.1.0 | **No** | `GET /campanas/{id}/trazabilidad` ya devuelve `requisitos` (semana 3). La API simulada no lo hacía: se agrega para que coincida con el contrato |
| Permisos del servidor | **No** | Leer la trazabilidad pide `qa.consultar`, que ya existe; cerrar pide `qa.cerrar`, que el rol no tiene (APP-09 → 403) |
| Ejecutar, registrar, reenviar, cerrar | **No** | El cambio es una vista de lectura. Los flujos A y B deben dar las mismas 46 comprobaciones |
| `app.js` | Sí | Rol nuevo, selector «Ver por», vista por requisito |
| `api-simulada.js` | Sí | Agrupa los casos por requisito (3 líneas) |
| `estilos.css` | Sí | 3 reglas para las tarjetas de requisito, todas con tokens |

**Riesgo principal:** que el selector «Ver por» re-pinte la vista y el foco se pierda (vuelva al `<main>`). Es el mismo problema que la semana 11 resolvió en los diálogos. El recorrido debe comprobar el foco después de cada cambio de vista.

## 3. Prueba primero (rojo)

Se escribió primero el **flujo C** en [recorrido.cjs](herramientas/recorrido.cjs):
1. El tester registra TC-107 FALLA con foto y defecto («Se puede editar una nota firmada»).
2. Entra el Responsable de producto → Ver trazabilidad. **c01:** debe verse «Responsable de producto», «Ver por» y TC-107 ★, y **no** debe existir [Cerrar campaña…].
3. Ver por requisito. **c02:** RF-07 y RF-08, «2 casos», «1 falla», «⛔ 1 defecto bloquea»; el foco queda en la opción «Requisito»; RF-07 va **primero**.
4. Ver por caso. **c03:** vuelven TC-107 ★ y TC-108 ★, con el foco en «Caso».

Resultado antes del cambio ([cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt)): A y B pasan (23 OK en escritorio) y C se detiene en `No element found for selector: [data-prueba="entrar-producto"]`.

## 4. Implementación

| Archivo | Líneas | Qué |
|---|---|---|
| [app.js](prototipo/app.js) | +33 −8 | Entrada «Responsable de producto» (avatar RP); su navegación solo tiene Trazabilidad. Fieldset «Ver por» con dos radios (el patrón de la semana 11, 24 px); al cambiar se re-pinta y **se devuelve el foco** a la opción elegida. `porRequisito(t)`: una tarjeta por requisito con casos, fallas, pendientes y defectos que bloquean, ordenada por (bloquean ↓, fallan ↓, nombre). [Cerrar campaña…] sigue condicionado a `rol === 'lider'` |
| [api-simulada.js](prototipo/api-simulada.js) | +3 −1 | `requisitos: { 'RF-07': ['TC-107', 'TC-108'], 'RF-08': ['TC-108'] }`, como el contrato |
| [estilos.css](prototipo/estilos.css) | +5 | `.requisito .titulo`, `ul.items-caso` |

**Una sola fuente para el bloqueo.** La tarjeta por requisito cuenta `bloquea_go` de los defectos que ya trae la trazabilidad. No recalcula R-09 ni R-15 en el cliente. Es la lección de H-3 de la semana 11: la regla escrita dos veces diverge.

Los RF-07 y RF-08 del prototipo son requisitos **del expediente** (el producto que se prueba), no los RF-07/RF-08 de este módulo. Es el mismo dato de demostración desde el prototipo móvil de la semana 10.

## 5. Después (verde) y lo que apareció en el camino

| Corrida | Resultado | Evidencia |
|---|---|---|
| 1 | 55/56: **falla c01 en móvil**: «Responsable de producto» no está en la pantalla | [cambio-2-movil-sin-rol.txt](evidencia/cambio-2-movil-sin-rol.txt) |
| 2 | **56/56, 0 violaciones de axe-core** | [cambio-2-despues-verde.txt](evidencia/cambio-2-despues-verde.txt) |

**H-1 · En móvil no se sabía el rol.** A 360 px la etiqueta del rol en la barra está oculta (`.rol-actual { display: none }` desde la semana 10, para que la barra entre). Solo queda el avatar «RP». Para el tester y el Líder QA no importaba, porque sus botones dicen qué pueden hacer. Para un rol de **solo lectura** sí importa: sin botones, nada le dice por qué no puede cerrar. La prueba no era demasiado estricta: encontró un problema real. **Corrección:** la vista dice «👁 Solo lectura · Responsable de producto» bajo el build, en todos los anchos.

**H-3 · El mapa de la semana 11 estaba mal dibujado.** Al actualizar el mapa se vio que el de la semana 11 decía «1. /ingresar», «1. /campanas», etc.: PlantUML lee `#` al inicio de una línea como lista numerada. Nadie lo había notado porque el mapa se revisó por su estructura, no por su texto. **Corrección:** `<U+0023>` en los dos mapas ([semana 11](../semana-11/diagramas/semana-11-mapa-navegacion.png) y [semana 12](diagramas/semana-12-mapa-navegacion.png)).

(H-2, que RF-11 estaba a medias en la interfaz, lo mostró la matriz; ver [matriz-decision-requisito-evidencia.md](matriz-decision-requisito-evidencia.md).)

## 6. Lo que no cambió (verificado)

- **Flujos A y B:** las mismas 46 comprobaciones en verde (56 − 10 del flujo C).
- **0 violaciones de axe-core** en las 44 capturas, incluidas las 6 nuevas.
- **Móvil:** `desborde=0` y `toque<44=0` en c01–c03.
- **Contrato y servidor:** sin cambios. El `git diff` de esta semana no toca `micro-his-qa/`.

## 7. Pendiente

- **Requisitos sin caso** (la otra mitad de RF-11): la API simulada debe exponer el alcance de la matriz. Va a la revisión técnica de la semana 13.
- **Probar con un Responsable de producto real** si «⛔ 1 defecto bloquea» es suficiente o si necesita ver el defecto sin pasar a la vista por caso.
