# Semana 7 — Catálogo de medicamentos: diseño de componentes y refactorización

| Entregable | Archivo |
|---|---|
| Diagrama de componentes backend (Laravel 12) | `diagramas/componentes-backend.png` |
| Diagrama de componentes frontend (Vue 3) | `diagramas/componentes-frontend.png` |
| Contratos de entrada/salida | `contratos-componentes.md` |
| Refactor concreto antes/después con justificación | `refactor-antes-despues.md`, `diagramas/refactor-antes.png`, `diagramas/refactor-despues.png` |

## Criterios de diseño

1. **Separación por capas** igual que el Micro-HIS: UI → aplicación → dominio ← persistencia.
2. **Un componente, una razón para cambiar**: el formato HTTP (FormRequest) no conoce reglas; las reglas no conocen HTTP.
3. **Interfaces de rol (ISP)** entre aplicación y persistencia, ya probadas en las semanas 2–4.
4. **Frontend con un solo punto de acceso a la API** (`useCatalogoApi`), que traduce el formato único de errores; los componentes de UI no hacen `fetch`.
5. **Permisos en el servidor**: `usePermisos` solo oculta acciones; `CatalogoPolicy` decide.

## Relación con semanas anteriores

| Componente | Origen |
|---|---|
| Casos de uso, modelo e interfaces | Micro-HIS, semana 3 (sin cambios) |
| Repositorio PDO / InMemory | Semanas 3 y 4 |
| Controlador API, permisos, `X-Request-Id` | Semana 5 (en Laravel se reparten en middleware, policy y controller) |
| Estados de UI (vacío, carga, error) | Se detallan en la semana 8 |

## Pendientes

- Nombres de clases Laravel/Vue: **propuesta**; deben alinearse con las convenciones del repo del
  grupo (no se pudo leer su estructura actual).
- `UsoPrescripcionesAdaptador`: depende del contrato con `prescripciones-alergias`.
