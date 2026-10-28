# Declaración de uso de inteligencia artificial — Semana 11

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 11 — Mejores prácticas para diseño móvil/web |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Construir el prototipo navegable: `index.html`, `app.js`, `estilos.css`, `datos.js` y `api-simulada.js`.
- Leer el kernel, las reglas y las vistas del Micro-HIS para que la API simulada responda igual.
- Escribir la prueba de paridad (`paridad.cjs` + `paridad.php`) contra el kernel PHP real.
- Escribir el recorrido automático (`recorrido.cjs`, con puppeteer-core y axe-core), que genera las capturas y comprueba el comportamiento y la accesibilidad.
- Dibujar el mapa de navegación (PlantUML) y armar las láminas.
- Redactar el documento, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Construya un prototipo navegable desktop y móvil del flujo principal de RBAC: roles, permisos y protección de rutas, cubriendo camino feliz y un error crítico relacionado con roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables; mantenga consistencia de roles, estados y accesibilidad. Entregable/Evidencia: Enlace o archivo del prototipo, mapa de navegación y capturas desktop/móvil del camino feliz y del error crítico.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | Borrador completo. Los estilos parten de los de la semana 10 y la semilla de datos es copia de `datos-demo.php`. |
| `herramientas/*` | Borrador completo |
| `capturas/`, `laminas/` | Generados por `recorrido.cjs` (Chrome headless) y Pillow |
| `diagramas/semana-11-mapa-navegacion.*` | Borrador completo (PlantUML 1.2024.7) |
| `evidencia/*.txt` | Salidas reales de los scripts (Node 24, PHP 8.4) |
| `semana-11-prototipo-rbac.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- La primera corrida del recorrido tuvo **24 fallas en 38 comprobaciones**, de cuatro tipos:
  - contraste de dos colores de avatar;
  - un `role="status"` dentro de una lista;
  - `aria-pressed` en un `menuitem`;
  - el enlace «HIS» por debajo de 44 px.

  Se corrigieron y la corrida final dio 0 fallas. El documento lo reporta (§6).
- Al revisar el código antes de probar se encontró un defecto: tras un 403 se borraba toda la caché, y el guard sacaba a la persona de la pantalla antes de que viera la referencia. Se corrigió borrando solo la decisión denegada.
- Se encontró y corrigió una inconsistencia: la referencia salía agrupada en un aviso y sin agrupar en otro.
- El hallazgo B-6, ruido en la auditoría, se midió con la API simulada. Esa API replica el comportamiento del Micro-HIS, que audita toda decisión DENEGADO. No se cambió el backend.
- La API simulada **no** es el backend real. La paridad está comprobada en 33 casos. B-1 y B-4 existen solo en la simulación.
- No se probó con lectores de pantalla ni en teléfonos reales.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Prototipo (vistas, guard, caché, diálogo) | | |
| Elección del error crítico (rol revocado con la pantalla abierta) | | |
| API simulada y prueba de paridad | | |
| Recorrido automático y criterios de accesibilidad | | |
| Hallazgo B-6 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí `prototipo/index.html` y recorrí el camino feliz y el error crítico, en escritorio y en modo móvil.
- [ ] Ejecuté `node herramientas/paridad.cjs` (33/33) y revisé `evidencia/recorrido.txt`.
- [ ] Completé la revocación de un rol usando solo el teclado.
- [ ] Puedo explicar por qué Ana ve «Guardar» y aun así recibe un 403.

**Fecha de validación:** ____________
