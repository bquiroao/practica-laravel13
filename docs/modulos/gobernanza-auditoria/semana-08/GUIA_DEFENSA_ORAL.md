# Guía de defensa oral — Semana 8 (UX)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-08-ux-auditoria.md](semana-08-ux-auditoria.md)

## 1. Resumen en 60 segundos

Dos roles usan esta pantalla: el auditor del hospital y el super administrador. Los emisores no tienen UI. El diseño se apoya en tres ideas:
- **la inmutabilidad se ve:** no hay editar ni borrar, y las correcciones aparecen enlazadas;
- **leer deja rastro:** aviso permanente y sin carga automática;
- **el alcance siempre a la vista:** la red pide confirmación.

Son seis wireframes, y cada respuesta de la API tiene su estado y su mensaje.

## 2. Orden sugerido

1. Roles (§2) y principios (§3).
2. Flujo del auditor, mostrando las decisiones y los tres finales de error.
3. W1 → W2 → W3, siguiendo una corrección.
4. Flujo del super administrador, con W4 (red) y W5 (verificar).
5. W6: errores y protección de datos.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no se cargan resultados al abrir la página? | Porque cada consulta queda registrada (R-08). Una carga automática llenaría la bitácora de lecturas que nadie pidió. |
| ¿Por qué no hay un botón para corregir un movimiento? | Corregir es responsabilidad del emisor, que conoce el dato. Además, el auditor no debe poder alterar lo que audita. |
| ¿Por qué el 404 no dice «es de otro hospital»? | Revelaría que el movimiento existe en otro tenant (R-05). |
| ¿Por qué confirmar la consulta de red? | Es la lectura más amplia del sistema y queda en la cadena de red. La confirmación evita hacerla por accidente. |
| ¿Por qué no hay números de página? | El cursor es estable aunque lleguen movimientos nuevos; con números de página se repetirían o se saltarían filas. |
| ALTERADA: ¿por qué no hay un botón «reparar»? | Reparar sería modificar la bitácora. Lo correcto es investigar; el informe es la evidencia. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Exportar a CSV» | Amplía el módulo y cada exportación debería auditarse como lectura. Se anota como mejora, no se improvisa. |
| «Que el auditor vea dos hospitales» | Es otro permiso de RBAC; el selector de W4 ya admite un hospital o la red. |
| «Rango por defecto de 7 días» | Se cambia el valor inicial de W1; el servidor no limita el rango. |
