# Reportes y analytics básicos — Semana 11

**Tema:** mejores prácticas para diseño móvil/web.
**Tarea individual:** construir un prototipo navegable desktop y móvil del flujo principal, cubriendo el camino feliz y un error crítico relacionado con indicadores, agregaciones, filtros, fuentes, precisión y visualización, con consistencia de roles, estados y accesibilidad.
**Entregable:** enlace o archivo del prototipo, mapa de navegación y capturas desktop/móvil del camino feliz y del error crítico.

## Prototipo

| | |
|---|---|
| Archivo | `prototipo/index.html` (un solo archivo, sin instalar nada; datos ficticios del Micro-HIS) |
| Cómo abrirlo | Doble clic en el archivo, o `php -S localhost:8080 -t prototipo` y abrir `http://localhost:8080` |
| Vista móvil | En el navegador: F12 → «Alternar barra de dispositivo» (Ctrl+Shift+M) → ancho 360 px |

## Qué cubre

| Flujo | Cómo recorrerlo |
|---|---|
| **Camino feliz** | Rol Jefe de área → Ocupación → Octubre 2026 → Calcular → 78,0 % → «¿De dónde sale?» → Medicina → 18 registros → «Cargar más» |
| **Error crítico: la fuente de datos no responde** | Marcar «Simular caída de la fuente de datos» → Calcular → mensaje claro, filtros conservados, código de seguimiento y «Reintentar» (desmarcar y reintentar recupera el flujo) |
| Estado vacío | Asistencia a citas → Septiembre 2026 → «Sin datos para este período» (RN-06) |
| Validación de filtros | Poner «Desde» mayor que «Hasta» → mensaje junto al campo antes de enviar (RN-02) |
| Rol restringido | Rol Médico → solo aparece «Asistencia a citas» (RN-01, RN-08) |

## Consistencia con las semanas anteriores

| Decisión | Origen | En el prototipo |
|---|---|---|
| Tres niveles con migas «Indicador › Desglose › Medicina» | Semanas 1 y 8, hallazgo H-05 | Navegación de ida y vuelta sin repetir la consulta |
| Precisión única (78,0 %) | Refactor de la semana 7, H-04 | Un solo formateador para valor, desglose y porcentajes |
| La suma del desglose coincide con el total | RN-07 | Fila total con «La suma coincide con el total» |
| Paciente solo con código | RN-09 | Leyenda en la tabla de registros |
| Tablas accesibles | H-02 | `caption` y `scope` en todas las tablas |
| Bordes con contraste 4.54:1 | H-01 | Color `#767676` |
| Validación de fechas junto al campo | H-06 | Mensaje debajo de las fechas antes de enviar |
| Foco movido al resultado y contorno de foco reforzado | H-07 | El foco va al título del resultado; `:focus-visible` de 3 px |
| Etiquetas legibles en la ficha de trazabilidad | H-08 | La ficha dice Fórmula, Período, Filtros, Fuente y Calculado |
| Reintentar sin perder filtros | H-03 | Botón en el error crítico |
| Tablas como tarjetas en móvil | Semana 10 | Por debajo de 768 px cada fila es una tarjeta; botones de 44 px |

## Mapa de navegación

`diagramas/mapa-navegacion.puml` muestra las pantallas, los estados y las transiciones del prototipo.

## Capturas

Tomadas del prototipo y guardadas en `capturas/`:

| Archivo | Contenido |
|---|---|
| `desktop-camino-feliz.png` | Desglose de ocupación en escritorio |
| `movil-camino-feliz.png` | Registros de Medicina en móvil (360 px) |
| `desktop-error-critico.png` | Error de fuente no disponible en escritorio |
| `movil-error-critico.png` | Error de fuente no disponible en móvil |