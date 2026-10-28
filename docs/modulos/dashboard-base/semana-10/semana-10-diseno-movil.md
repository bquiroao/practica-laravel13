# Semana 10 — Diseño móvil de «navegación del dashboard con estados de carga, vacío, error y resumen inicial»

| | |
|---|---|
| **Tarea Odoo** | 2437 — Semana 10: Diseño para movilidad |
| **Módulo** | Dashboard base UI/UX e implementación transversal |
| **Base** | Flujo y wireframes de la semana 8 (PR #205) y backlog de la semana 9 (PR #222); reglas R-01 a R-09 de la [Semana 1](../semana-01/semana-01-uml-navegacion-dashboard.md); textos de las tarjetas del Micro-HIS (`Dominio/Dashboard.php`) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Jerarquía en móvil (320–430 px)

El dashboard es la primera pantalla que se ve después de entrar, y en el teléfono se usa para echar un vistazo rápido entre tareas. Por eso, el layout, la navegación, las tarjetas, los permisos y los estados de carga, vacío y error se ordenan así:

1. **Layout:** una barra superior con «≡ Menú» y el hospital y el rol de la sesión, para saber de quién son los datos. El enlace «Saltar al contenido» aparece al enfocarlo (B-10).
2. **Tarjetas:** una por fila, ordenadas por el rol. Enfermería ve primero las camas disponibles; el personal de citas, las citas de hoy. Cada tarjeta muestra un solo número grande y su acción. Por permisos, las tarjetas sin permiso no aparecen (R-02).
3. **Estados de carga, vacío y error:** cada tarjeta muestra su propio estado (R-03). El vacío sugiere una acción («agenda una»), y el error ofrece «Reintentar» solo en esa tarjeta (R-08).
4. **Navegación:** el menú se abre a pantalla completa con solo los módulos del rol. «Cerrar sesión» queda al final, separado, para no tocarlo por error.

Para reducir la carga cognitiva hay un solo número por tarjeta, ningún gráfico, y textos de error sin códigos técnicos (R-09).

## 2. Pantallas

| M | Pantalla | Navegación | Tabla o formulario | Confirmación y error |
|---|---|---|---|---|
| [M1](diagramas/semana-10-m1-inicio.png) | Inicio | Raíz del sistema; «≡ Menú» abre M2 | Tarjetas apiladas; la que carga muestra un esqueleto con `aria-busy` | Más de 10 s sin respuesta pasa a error (R-04) |
| [M2](diagramas/semana-10-m2-menu.png) | Menú | Pantalla completa; «× Cerrar menú» vuelve sin navegar | Lista de módulos; el actual va subrayado y dice «(estás aquí)» | «Cerrar sesión» abre la confirmación de M4 |
| [M3](diagramas/semana-10-m3-error-vacio.png) | Tarjeta en error y vacía | Las tarjetas sanas siguen enlazando a su módulo | — | «No pudimos cargar las camas disponibles. Intenta de nuevo.» y «Reintentar»; el vacío lleva a «Agendar» |
| [M4](diagramas/semana-10-m4-sesion.png) | Sesión | Se abre sobre cualquier pantalla | — | Aviso 2 minutos antes de expirar, con «Seguir conectado» (B-04); confirmar el cierre de sesión con «Volver» como alternativa |
| [M5](diagramas/semana-10-m5-conexion-limitada.png) | Conexión limitada | Franja fija arriba; los módulos piden conexión para abrir | Cada tarjeta muestra su último valor con la hora | «Reintentar» vuelve a cargar las tarjetas solo cuando el usuario lo pide (R-08) |

Cada pantalla completa recibe el foco en su título al abrirse, y al navegar el foco va al `<h1>` del módulo (B-01 y B-02).

## 3. Reglas de breakpoint

| Ancho | Regla |
|---|---|
| 320–375 px | Una columna: una tarjeta por fila, a todo el ancho. El nombre del hospital se abrevia («H. Central»). |
| 376–430 px | Dos columnas, porque caben 2 tarjetas de 170 px con 3 márgenes de 12 px (2 × 170 + 3 × 12 = 376). |
| 431 px o más | Fuera del alcance móvil: vuelve la cuadrícula de tres tarjetas y el menú horizontal de W1 de la semana 8. |

**Reglas comunes:** sin scroll horizontal a 320 px (WCAG 1.4.10); objetivos táctiles de al menos 44 × 44 px (2.5.8 exige 24 px); el número de la tarjeta se lee con el texto ampliado al 200 % (1.4.4); funciona en vertical y en horizontal (1.3.4); el anillo de foco mide 2 px en `#2563eb` (5.17:1, semana 9).

## 4. Escenarios móviles

| | E-1: turno nocturno con red débil | E-2: la sesión expira con el teléfono en el bolsillo |
|---|---|---|
| **Quién y dónde** | Enfermera en el piso, con un teléfono de 360 px | Médico entre consultas, con un teléfono de 320 px |
| **Tarea** | Ver cuántas camas hay disponibles antes de recibir un ingreso | Volver al dashboard después de 20 minutos sin usarlo |
| **Decisión de contenido** | «Camas disponibles» va primero porque su rol es enfermería. Las tarjetas que tardan no frenan a las demás (R-03) | Al volver, primero se ve el aviso de sesión, no tarjetas que fallarían con 401 |
| **Error** | «Citas de hoy» pasa los 10 s sin responder; después se pierde la conexión | La sesión expiró mientras el teléfono estaba sin conexión |
| **Decisión de error** | Solo esa tarjeta pasa a error con «Reintentar». Al perder la conexión, M5 conserva las camas con su hora («7 a las 08:40»): un número sin hora parecería actual. Lo que nunca cargó dice «Sin datos guardados» en vez de mostrar 0 | Si el aviso de M4 llegó a tiempo, «Seguir conectado» renueva la sesión. Si no, al reconectar se limpia la sesión y se va a `/login` (R-07) con «Tu sesión expiró. No se perdió ningún dato», y no se muestran datos de la sesión anterior |

## 5. Trazabilidad con la semana 9

| Corrección del backlog (PR #222) | Dónde queda en móvil |
|---|---|
| B-01 y B-02 Foco al navegar y en diálogos | Pantallas completas con el foco en su título; `<h1>` al cambiar de módulo |
| B-04 Aviso antes de expirar la sesión | M4 |
| B-05 «Reintentar» sin perder el foco | M3 |
| B-06 Carga anunciada con `aria-busy` | M1 |
| B-07 Página actual sin depender del color | M2: subrayado y «(estás aquí)» |
| B-10 «Saltar al contenido» | M1 |

Fuentes editables en [`diagramas/`](diagramas/) (PlantUML Salt); los PNG se generaron con PlantUML local. Salt no fija el ancho en píxeles, así que los wireframes representan la estructura a 320 px y el ancho real se valida en el prototipo de la semana 11.

**Bibliografía:** W3C (2023). *WCAG 2.2*: 1.3.4, 1.4.4, 1.4.10 y 2.5.8 · Wroblewski, L. (2011). *Mobile First*. A Book Apart.
