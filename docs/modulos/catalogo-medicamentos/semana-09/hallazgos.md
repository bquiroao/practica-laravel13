# Hallazgos (11)

Impacto: **Alto** = puede causar un error en el catálogo o excluye a usuarios de teclado o con baja
visión · **Medio** = obliga a trabajo extra o confunde · **Bajo** = molestia menor.
Las capturas se generaron con Chromium (Playwright) sobre el Micro-HIS en modo `memoria`.

| ID | Hallazgo | Dónde | Criterio | Impacto | Evidencia |
|---|---|---|---|---|---|
| H-01 | El indicador de foco (`#f2b705`) tiene 1,82:1 contra el fondo blanco; mínimo 3:1. | `layout.php` (`:focus-visible`) | WCAG 1.4.11, 2.4.7 | Alto | `capturas/h04-foco-amarillo.png`; `evidencia/contraste.txt` |
| H-02 | Tras enviar con errores (422) el foco queda en `BODY`; el resumen «Revise los 6 campo(s)» no tiene enlaces a los campos. | `formulario.php` | WCAG 2.4.3, 3.3.1; heurística 9 | Alto | `capturas/h02-errores-sin-enlaces.png` (prueba: foco = `BODY`, enlaces = 0) |
| H-03 | «Eliminar MED-000106» se ejecuta con un solo clic, sin confirmación. | `detalle.php` | WCAG 3.3.4; heurística 5 | Alto | `capturas/h03-eliminar-un-clic.png` |
| H-04 | Ningún campo obligatorio está marcado (0 asteriscos, 0 `aria-required`). | `formulario.php` | WCAG 1.3.1, 3.3.2 | Medio | Revisión del HTML con Playwright |
| H-05 | Con concentración «0» y sin unidad, solo se informa la unidad; el error del valor aparece después de corregir la unidad. Contradice RF-02 («todos los errores a la vez»). | `FichaMedicamento::validar` | RF-02, WCAG 3.3.1 | Alto | `capturas/h02-errores-sin-enlaces.png` (no hay mensaje bajo «Concentración») · se registra como defecto en la semana 15 |
| H-06 | «capsula» no encuentra «Amoxicilina 500 mg cápsula»; «cápsula» sí. | Búsqueda (RN-03) | Heurísticas 2 y 7 | Medio | `capturas/h05-acentos.png` (0 resultados con «capsula», 1 con «cápsula») |
| H-07 | Formas y vías se muestran como códigos técnicos (`SOLUCION_INYECTABLE`, `GOTAS_OFTALMICAS`). | Selectores y tablas | Heurística 2 | Medio | `capturas/h02-errores-sin-enlaces.png` |
| H-08 | No existe enlace «Saltar al contenido». | `layout.php` | WCAG 2.4.1 | Bajo | Revisión del HTML |
| H-09 | Un medicamento registrado hoy no se puede dar de baja hoy («La baja debe ser posterior al …») y la pantalla no explica que corresponde «Eliminar registro erróneo». | Detalle, baja | Heurísticas 9 y 10 | Medio | Reproducible: registrar y dar de baja el mismo día → 422 «La baja debe ser posterior al …» |
| H-10 | El historial no muestra quién registró cada versión; la API v1 no expone `creado_por`. | Detalle, API | RNF-05 | Medio | `MedicamentoVista::aArreglo` no incluye el campo |
| H-11 | En 375 px la tabla de resultados necesita desplazamiento horizontal interno (la página no). | `lista.php` | Movilidad | Bajo | `capturas/h07-movil-tabla.png` |

## Lo que funciona bien (para no perderlo)

- Mensajes de error con el límite exacto y el valor actual («tiene 1»).
- 409 `EN_USO` con explicación y acción alternativa en la misma página.
- Contraste de texto amplio (mínimo 6,67:1) y reflow correcto a 320 px.
