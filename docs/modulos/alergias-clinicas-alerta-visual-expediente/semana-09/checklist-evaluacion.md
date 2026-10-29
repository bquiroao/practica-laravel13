# Checklist de evaluación — usabilidad (Nielsen) y accesibilidad (WCAG 2.2 AA)

**Objeto evaluado:**
- el flujo y los wireframes de la semana 8 (W1 a W5);
- las vistas **reales** del Micro-HIS: `vistas/alerta.php`, `formulario.php`, `error.php` y `layout.php`.

**Resultado:** C = cumple · P = parcial · N = no cumple. La columna «Evidencia» dice cómo se comprobó.

| # | Criterio | Fuente | W1–W5 | Vistas reales | Evidencia |
|---|---|---|---|---|---|
| 1 | Visibilidad del estado: carga, vacío, éxito y error distinguibles | Nielsen 1 | C | P | Las vistas no tienen estado de carga (render en servidor) → H-08 |
| 2 | Lenguaje del usuario | Nielsen 2 | C | N | `alerta.php` muestra `(moderate)`, `(life_threatening)` → H-01 |
| 3 | Control y libertad: volver sin perder datos | Nielsen 3 | C | P | `error.php` usa `javascript:history.back()` → H-06 |
| 4 | Consistencia: mismo verbo de la acción al mensaje | Nielsen 4 | C | C | «Guardar alergia» → «Alergia registrada» (W4) |
| 5 | Prevención de errores en la acción de mayor riesgo | Nielsen 5 | C | N | Las vistas guardan sin confirmar una severidad `life_threatening` → H-03 |
| 6 | Reconocer antes que recordar: reglas visibles antes del error | Nielsen 6 | C | N | El formulario no dice «nombre **o** código» → H-04 |
| 7 | Mensajes de error que dicen qué pasó y cómo seguir | Nielsen 9 | C | P | El mensaje es claro pero aparece solo arriba y sin ligar al campo → H-02 |
| 8 | Ayuda contextual | Nielsen 10 | C | N | Las vistas no explican qué es reconocer ni qué significa «sin registro» → H-07 |
| 9 | Teclado: todo se alcanza en orden lógico | WCAG 2.1.1, 2.4.3 | C | C | Recorrido con Tab: alérgeno → código → tipo → severidad → reacción → fecha → botón |
| 10 | Foco visible | WCAG 2.4.7, 2.4.13 | C | C | `:focus-visible` 3 px #1d4ed8: 6.70:1 sobre blanco y 5.71:1 sobre el fondo CRÍTICA (`contraste.py`) |
| 11 | Foco tras un error de envío | WCAG 3.3.1, 2.4.3 | C | N | Tras el 422 el foco queda en `BODY` y el campo no tiene `aria-invalid` → H-02 |
| 12 | Contraste de texto ≥ 4.5:1 | WCAG 1.4.3 | C | C | `contraste.py`: 12 pares, 0 fallas (texto: mínimo 7.75:1; no textual: mínimo 5.20:1)|
| 13 | El color no es el único medio | WCAG 1.4.1 | C | C | Nivel con texto `[CRITICA]` además del color |
| 14 | Etiquetas asociadas a cada campo | WCAG 1.3.1, 3.3.2 | C | C | Todos los campos tienen `<label for>` |
| 15 | Anuncio adecuado para lector de pantalla | WCAG 4.1.3 | C | P | `role="alert"` envuelve toda la sección (lista y formulario) → H-05 |
| 16 | Formato de fecha coherente con la ayuda | WCAG 3.3.2 | C | P | La etiqueta dice AAAA-MM-DD, pero el selector nativo en español muestra dd/mm/aaaa → H-09 |
| 17 | Protección de datos según el rol | Propio | C | N | La entrada web no tiene autenticación (usuario demo fijo, DEC-07): cualquiera ve el formulario → H-10 |
| 18 | Revisión automática axe-core 4.10 (wcag2a/aa, 21aa, 22aa) | Herramienta | — | C | 0 violaciones en `alerta` y `formulario` (corrida de preparación) |
