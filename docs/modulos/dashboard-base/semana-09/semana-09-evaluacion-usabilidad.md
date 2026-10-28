# Semana 9 — Evaluación de usabilidad y accesibilidad del flujo UX del Dashboard base

| | |
|---|---|
| **Tarea Odoo** | 2436 — Semana 9: Evaluación del diseño, usabilidad y accesibilidad |
| **Módulo** | Dashboard base UI/UX e implementación transversal |
| **Objeto evaluado** | User flow y wireframes W1–W5 de la semana 8 (commit `15e8c46`, PR #205) y el frontend transversal real en `develop`: `app.blade.php`, `router/index.js`, `AppLayout.vue`, `HomePage.vue` y el estilo de formulario de `LoginPage.vue` |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Método

- **Heurísticas:** las 10 de Nielsen, revisadas pantalla por pantalla.
- **WCAG 2.2 nivel AA:** los criterios del enunciado (teclado, foco, contraste, etiquetas, mensajes y prevención de errores), además del idioma, el título de página y el tiempo de sesión, que dependen del layout transversal.
- **Evidencia:** cada hallazgo cita una línea, del wireframe ([lineas-wireframes-s8.txt](evidencia/lineas-wireframes-s8.txt)) o del código real ([lineas-frontend.txt](evidencia/lineas-frontend.txt)), o una medición. El contraste se mide con [contraste.php](evidencia/contraste.php) (fórmula de luminancia relativa de WCAG, con autoprueba 21:1 y 1:1); su salida está en [salida-contraste.txt](evidencia/salida-contraste.txt).
- **Gravedad** (escala de Nielsen, 0–4) e **impacto transversal**: el layout envuelve las pantallas de todos los módulos, así que un problema del layout se repite en todo el sistema.
- **Limitación:** hubo un solo evaluador, y Nielsen recomienda de 3 a 5. No se probó con un lector de pantalla real; el código se revisó leyéndolo.

## 2. Checklist

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Parcial | Cada tarjeta muestra carga, vacío, resumen o error (W1–W3); la carga no se anuncia (H-05) |
| 2 | Relación con el mundo real | Cumple | «Pacientes activos», «citas hoy» y «camas disponibles», sin códigos técnicos (R-09) |
| 3 | Control y libertad | Cumple | «Reintentar» por tarjeta, «Volver al inicio» en W4 y «Cancelar» al cerrar sesión |
| 4 | Consistencia y estándares | Parcial | El menú es igual en todas las pantallas, pero el enlace activo solo cambia de color (H-09) |
| 5 | Prevención de errores | Parcial | Confirmación al cerrar sesión (W5); la sesión expira sin aviso previo (H-07) |
| 6 | Reconocer antes que recordar | Cumple | Hospital y rol visibles arriba; el menú muestra solo lo permitido (R-02) |
| 7 | Flexibilidad y eficiencia | Parcial | Acceso directo a cada módulo desde su tarjeta; no hay enlace para saltar el menú (H-01) |
| 8 | Diseño estético y minimalista | Cumple | Solo conteos, sin datos de pacientes (R-06) |
| 9 | Reconocer y corregir errores | Cumple | W3 y W4 dicen qué pasó y qué hacer, sin detalles técnicos |
| 10 | Ayuda y documentación | Cumple | «Ayuda» en el menú y la explicación de W4 sobre a quién pedir el permiso |
| 11 | Teclado (2.1.1, 2.4.1) | No cumple | El layout no tiene un enlace para saltar al contenido (H-01) |
| 12 | Foco (2.4.3, 2.4.7, 2.4.11) | No cumple | El router no mueve el foco al cambiar de ruta (H-04); «Reintentar» pierde el foco al deshabilitarse (H-06); 0 menciones de «foco» en la semana 8 y 0 archivos con `focus` en `resources/js` (H-08) |
| 13 | Contraste de texto (1.4.3) | Cumple | Texto 17.06:1, enlace activo 5.17:1, subtítulo 7.58:1 y error 6.47:1 |
| 14 | Contraste de componentes (1.4.11) | No cumple | Borde de campo `#cbd5f5` a 1.46:1, cuando el mínimo es 3:1 (H-10) |
| 15 | Uso del color (1.4.1) | No cumple | El enlace activo solo se distingue por el color, con `text-decoration: none` (H-09) |
| 16 | Etiquetas, idioma y título (2.4.2, 3.1.1, 4.1.2) | No cumple | `lang="en"` en una interfaz en español (H-02); el mismo `<title>` en todas las rutas (H-03) |
| 17 | Mensajes de error y de estado (3.3.1, 4.1.3) | Parcial | Los textos de error son claros (R-09), pero la carga y el resultado de cada tarjeta no se anuncian (H-05) |
| 18 | Prevención de errores y tiempo (2.2.1, 3.3.4) | No cumple | La sesión se cierra sin avisar ni permitir extenderla (H-07) |

## 3. Hallazgos

| ID | Dónde | Problema | Criterio | Gravedad | Evidencia |
|---|---|---|---|---|---|
| H-01 | `AppLayout.vue`, líneas 3 a 16 | No hay un enlace «Saltar al contenido»: con teclado hay que recorrer el menú en cada pantalla | 2.4.1 | 1 | `<header>` y `<nav>` antes de `<main>`, sin enlace de salto |
| H-02 | `app.blade.php`, línea 2 | `lang` sale de `APP_LOCALE=en`: el lector de pantalla pronuncia en inglés toda la interfaz en español | 3.1.1 | 3 | `config/app.php` línea 81 y `.env.example` línea 8 |
| H-03 | `app.blade.php`, línea 7 | Todas las rutas comparten el título «Hospital HIS»: las pestañas y el historial no dicen dónde se está | 2.4.2 | 2 | 0 archivos con `document.title` en `resources/js` |
| H-04 | `router/index.js`, línea 23 | Al navegar, el foco se queda en el enlace pulsado y el lector no anuncia la nueva página | 2.4.3, 4.1.3 | 3 | Solo `beforeEach`, sin `afterEach` ni manejo del foco |
| H-05 | W1, línea 8 | El esqueleto «cargando…» y el cambio a resumen, vacío o error no se anuncian | 4.1.3; heurística 1 | 2 | Tarjeta en carga sin mensaje de estado |
| H-06 | W3, línea 11 | «Reintentar» se deshabilita mientras carga: un botón deshabilitado pierde el foco y el usuario de teclado queda al inicio de la página | 2.4.3 | 2 | «se deshabilita mientras carga» |
| H-07 | W5, líneas 4 y 5 | La sesión expira sin aviso previo ni opción de extenderla; en un formulario de otro módulo se perdería lo escrito | 2.2.1; heurística 5 | 3 | «Tu sesión expiró… cerramos la sesión» |
| H-08 | W3–W5 | No se define el foco: ni el inicial del diálogo «¿Cerrar sesión?», ni a dónde va tras «Reintentar», ni el anillo de foco | 2.4.7, 2.4.11 | 3 | 0 menciones de «foco» en la semana 8 |
| H-09 | `AppLayout.vue`, líneas 55 y 59 | El enlace activo solo cambia de color, sin subrayado ni otra señal | 1.4.1; heurística 4 | 2 | `text-decoration: none`; activo `#2563eb` frente a normal `#0f172a`: 3.45:1 |
| H-10 | Estilo de campo compartido | El borde del campo del login, que los módulos reutilizarían, no se distingue del fondo | 1.4.11 | 2 | `#cbd5f5` sobre `#ffffff`: 1.46:1 |

## 4. Backlog priorizado

La prioridad sale del impacto en layout, navegación, tarjetas, permisos y estados de carga, vacío y error. P1 = afecta a todas las pantallas o impide la tarea con teclado o lector de pantalla · P2 = dificulta la tarea · P3 = mejora menor.

| Orden | Hallazgo | Prioridad | Impacto transversal | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|---|
| B-01 | H-04 | P1 | Navegación de todo el sistema | `router.afterEach` mueve el foco al `<h1>` de la nueva página (`tabindex="-1"`) | Tras cada navegación, `document.activeElement` es el `<h1>` y el lector lee el título |
| B-02 | H-08 | P1 | Diálogos y tarjetas de todos los módulos | Foco inicial en el título del diálogo, Tab sin salir de él, Esc lo cierra y el foco vuelve al control de origen; anillo `:focus-visible` de 2 px en `#2563eb` | Recorrido solo con teclado de W1 a W5; anillo ≥ 3:1 (medido: 5.17:1) |
| B-03 | H-02 | P1 | Layout de todas las pantallas | `APP_LOCALE=es` en `.env.example` y `'locale' => env('APP_LOCALE', 'es')` | El HTML servido tiene `<html lang="es">` |
| B-04 | H-07 | P1 | Permisos y sesión en todos los módulos | Aviso 2 minutos antes de expirar, con «Seguir conectado», anunciado con `role="alertdialog"` | El aviso aparece antes del 401 y «Seguir conectado» renueva la sesión sin perder lo escrito |
| B-05 | H-06 | P2 | Estado de error de las tarjetas | «Reintentar» con `aria-disabled` mientras carga, y al terminar el foco va al contenido de la tarjeta | El foco nunca termina en `body` durante el reintento |
| B-06 | H-05 | P2 | Estados de carga, vacío y error | Tarjeta con `aria-busy="true"` mientras carga y una región `aria-live="polite"` con el resultado | El lector anuncia «Citas de hoy: 18 citas hoy» al terminar la carga |
| B-07 | H-09 | P2 | Navegación | Enlace activo subrayado de 2 px; el lector ya recibe `aria-current="page"`, que Vue Router pone en el enlace exacto | El activo se distingue en escala de grises; subrayado ≥ 3:1 (medido: 5.17:1) |
| B-08 | H-10 | P2 | Formularios dentro del layout | Borde de campo `#64748b` en el estilo compartido | `contraste.php`: 4.76:1 ≥ 3:1 |
| B-09 | H-03 | P2 | Navegación | `meta.titulo` en cada ruta y `document.title = "<página> · Hospital HIS"` en `afterEach` | Cada ruta tiene un título distinto |
| B-10 | H-01 | P3 | Layout | Enlace «Saltar al contenido», visible al enfocarlo, que lleva a `<main id="contenido">` | El primer Tab de cada página muestra el enlace y Enter lleva el foco a `<main>` |

B-01, B-03, B-09 y B-10 cambian archivos compartidos (`resources/`, `config/`), que no pertenecen a la carpeta de este módulo. Se proponen aquí y se implementan en un PR propio del frontend, coordinado con el equipo. Las demás correcciones se aplican al prototipo navegable de la semana 11.

**Bibliografía:** Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2* · Vue Router (2024). *Navigation Guards* (`afterEach`).
