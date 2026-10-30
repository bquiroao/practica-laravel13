# Semana 11 — Mejores prácticas para diseño móvil/web

**Tarea:** prototipo navegable desktop y móvil del flujo principal, con camino feliz y un error crítico, manteniendo consistencia de roles, estados y accesibilidad.
**Entregable:** archivo del prototipo, mapa de navegación y capturas desktop/móvil del camino feliz y del error crítico.

## 1. Prototipo

`prototipo/index.html`: un solo archivo HTML+CSS+JS, **sin servidor y con datos ficticios en memoria**. Se abre con doble clic en el navegador. Aplica las mismas reglas que el Micro-HIS:

| Regla | En el prototipo |
|---|---|
| RN-06 acuse idempotente | Acusar dos veces muestra «ya estaba acusada; no hubo cambios» |
| RN-07 solo notificados | `jefe.servicio01` en una alerta de nivel 0 → aviso RN-07 |
| RF-08 cierre por rol | «Cerrar alerta» solo aparece para médico y jefe de servicio |
| RN-09 nota obligatoria | Error en el campo y foco al campo |
| RN-04 / RN-05 plazos | «Vence en mm:ss → escala a …» en la bandeja y el detalle |

**Camino feliz:**
1. `enf.turno01` abre la bandeja.
2. Abre PAC-00451 (CRITICA) y acusa.
3. `med.tratante01` cierra con nota y confirmación.
4. La alerta queda CERRADA con su historial.

**Error crítico, sin conexión al acusar** (casilla «Simular sin conexión»):
- El acuse **no se registra**, y la alerta sigue ESCALADA: no se pinta como acusada sin confirmación.
- El mensaje explica que reintentar es seguro y el foco va al botón «Reintentar ahora».
- Es el escenario A de la semana 10.

## 2. Mapa de navegación

`diagramas/mapa-navegacion.png`: Bandeja (`#bandeja`) → Detalle (`#alerta/{id}`) → Cierre (`#cierre/{id}`), con los estados de error sin conexión y sin permiso.

## 3. Capturas (Chromium, generadas con Playwright sobre el archivo del prototipo)

| Paso | Desktop 1280×800 | Móvil 375×760 |
|---|---|---|
| Bandeja (tabla / tarjetas) | `capturas/desktop-1-bandeja.png` | `capturas/movil-1-bandeja.png` |
| Acuse registrado | `capturas/desktop-2-acuse-registrado.png` | `capturas/movil-2-acuse-registrado.png` |
| Alerta cerrada | `capturas/desktop-3-cerrada.png` | `capturas/movil-3-cerrada.png` |
| **Error crítico:** sin conexión | `capturas/desktop-4-error-sin-conexion.png` | `capturas/movil-4-error-sin-conexion.png` |

Comprobado en los dos tamaños:
- Sin errores de JavaScript.
- Sin desplazamiento horizontal.
- Después del error, el estado de la alerta sigue siendo ESCALADA.

## 4. Accesibilidad y consistencia

- Incorpora del backlog de la semana 9: enlace «Saltar al contenido» (H-01), plazo visible (H-03), confirmación de cierre (H-04), ayuda visible en el campo (H-07) y `<caption>` en el historial (H-08).
- Botones de al menos 44 px de alto, prioridad con texto y símbolo, y mensajes en `role="status"` con `aria-live`.
- Contrastes calculados (WCAG): ALTA `#93370d` sobre blanco 7.52:1; texto de ayuda `#475467` 7.69:1.
- **Corrección hecha al revisar:** el contorno de foco naranja `#f79009` daba 2.35:1 sobre blanco, por debajo del 3:1 exigido. Se cambió a un contorno `#1a1a1a` con halo blanco, que da 16.24:1 sobre el fondo claro; en la cabecera azul, el halo blanco da 12.46:1. Las capturas no muestran el foco, así que no cambian.
- Los roles, estados y mensajes son los mismos de las semanas 8 y 10.

Los .png de diagramas se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.
