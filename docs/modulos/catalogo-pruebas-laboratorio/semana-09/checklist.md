# Checklist de usabilidad y accesibilidad

**Objeto evaluado:** flujo «alta y consulta de prueba con requisitos de muestra y vigencia» en dos fuentes: los wireframes W-01 a W-05 (semana 8) y las vistas reales del Micro-HIS (`micro-his/src/Presentation/vistas/`, semana 4).
**Método:** revisión del código de las vistas y de los wireframes contra las 10 heurísticas de Nielsen y criterios WCAG 2.1 nivel AA. Los puntos que solo se confirman usando la página quedan como **Por verificar**, con el procedimiento.

Resultado: **C** = cumple · **NC** = no cumple (genera hallazgo) · **PV** = por verificar manualmente.

## 1. Heurísticas de Nielsen

| # | Heurística | Micro-HIS | Wireframes | Hallazgo |
|---|---|---|---|---|
| 1 | Visibilidad del estado del sistema | NC: el botón «Guardar prueba» no muestra estado de guardado | C: estados carga, vacío y guardando definidos | H-07 |
| 2 | Relación con el mundo real | C: términos del laboratorio (contenedor, estabilidad, ayuno) | C | — |
| 3 | Control y libertad del usuario | NC: se guarda sin poder revisar antes | C: W-04 permite volver a editar | H-03 |
| 4 | Consistencia y estándares | NC: fecha como texto en el alta y como selector de fecha en la consulta | C | H-05 |
| 5 | Prevención de errores | NC: sección, tipo de muestra y conservación son texto libre aunque la lista es cerrada | C: listas desplegables | H-01 |
| 6 | Reconocer antes que recordar | NC: hay que recordar los valores válidos (por ejemplo `quimica-clinica`) | C | H-01 |
| 7 | Flexibilidad y eficiencia | C: filtros por texto y fecha en la URL | C | — |
| 8 | Diseño estético y minimalista | C | C | — |
| 9 | Ayudar a reconocer y recuperarse de errores | NC: la página «no vigente» no ofrece ver la versión vigente ni cambiar la fecha | C: W-02 ofrece la otra versión | H-08 |
| 10 | Ayuda y documentación | NC: sin ayuda contextual en estabilidad ni vigencia | C: íconos «¿Qué es?» en W-03 | H-09 |

## 2. WCAG 2.1 AA

| Criterio | Qué se revisó | Resultado | Evidencia / procedimiento | Hallazgo |
|---|---|---|---|---|
| 1.3.1 Información y relaciones | Tabla de resultados | NC | `lista.php` líneas 11–12: `<table>` sin `<caption>` y `<th>` sin `scope` | H-04 |
| 1.3.1 / 3.3.2 Etiquetas | Campos del alta y de la consulta | C | `formulario.php` línea 15: `<label for>` en cada campo; `lista.php` líneas 3–4: inputs dentro de `<label>` | — |
| 1.4.3 Contraste del texto | Colores de error y aviso | C | Calculado con la fórmula WCAG: rojo `#aa0000` sobre blanco 7.75:1 y verde `#006600` 7.24:1 (mínimo 4.5:1) | — |
| 1.4.10 Reflujo | Ancho 320 px | PV | Chrome → DevTools → modo dispositivo a 320 px: comprobar que no haya scroll horizontal (la tabla podría desbordar) | — |
| 2.1.1 Teclado | Todo el flujo | PV | Solo usa elementos nativos (`a`, `input`, `button`), que son alcanzables por teclado; confirmar recorriendo con Tab y Enter | — |
| 2.4.1 Saltar bloques | Navegación repetida | NC | `layout.php` línea 14: no hay enlace «Saltar al contenido» | H-06 |
| 2.4.2 Título de página | Título por vista | NC | `layout.php` línea 6: todas las páginas se llaman igual | H-06 |
| 2.4.3 Orden del foco | Tras un error 422 | NC | El foco queda en el botón; no va al primer campo con error | H-02 |
| 2.4.7 Foco visible | Enlaces, campos y botones | PV | Sin estilos que oculten el foco; confirmar con Tab que el contorno se ve en todos | — |
| 3.1.1 Idioma | Documento | C | `layout.php` línea 2: `lang="es"` | — |
| 3.3.1 Identificación de errores | Alta con datos inválidos | C (parcial) | `formulario.php` líneas 17–18: mensaje por campo con `aria-invalid` y `aria-describedby`; falta un resumen anunciado | H-02 |
| 3.3.3 Sugerencia ante errores | Mensajes | C | Cada mensaje dice el valor esperado (por ejemplo «mayor que 0 mL») | — |
| 3.3.4 Prevención de errores (datos importantes) | Alta que cierra otra versión | NC | No hay confirmación antes de guardar | H-03 |
| 4.1.2 Nombre, función y valor | Botones | C | Botones con texto visible | — |
