# Reportes y analytics básicos — Semana 9

**Tema:** evaluación del diseño, usabilidad y accesibilidad.
**Tarea individual:** evaluar el flujo UX del módulo con heurísticas de usabilidad y WCAG (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y priorizar las correcciones según su impacto en indicadores hospitalarios, agregaciones, filtros, fuentes, precisión y visualización.
**Entregable:** checklist completado, evidencia de al menos 6 hallazgos y backlog priorizado con corrección propuesta y criterio verificable.

**Qué se evaluó:** los 6 wireframes y los user flows de la semana 8, y el cliente funcional `micro-his/public/cliente.html` de la semana 5, probado en Chrome con recorrido solo con teclado y con las herramientas de desarrollador (estilos, HTML y contraste).

## 1. Checklist completado

Resultado: ✅ cumple · ⚠️ cumple parcialmente · ❌ no cumple.

### Heurísticas de Nielsen

| # | Heurística | Resultado | Observación |
|---|---|---|---|
| N1 | Visibilidad del estado del sistema | ⚠️ | Hay «Calculando…», pero no se indica en qué nivel de trazabilidad está el usuario (H-05) |
| N2 | Relación con el mundo real | ⚠️ | Lenguaje hospitalario en general, pero la ficha de trazabilidad muestra claves técnicas (H-08) |
| N3 | Control y libertad del usuario | ⚠️ | No hay «volver» entre niveles en el cliente (H-05) |
| N4 | Consistencia y estándares | ❌ | La precisión cambia entre el cliente y los wireframes (H-04) |
| N5 | Prevención de errores | ⚠️ | Las fechas se validan solo al enviar (H-06) |
| N6 | Reconocer antes que recordar | ✅ | Ficha de trazabilidad siempre visible |
| N7 | Flexibilidad y eficiencia | ✅ | Los filtros se conservan entre consultas |
| N8 | Diseño estético y minimalista | ✅ | Una tarjeta principal por pantalla |
| N9 | Ayudar a reconocer y recuperarse de errores | ⚠️ | El 503 no tiene botón «Reintentar» en el cliente (H-03) |
| N10 | Ayuda y documentación | ⚠️ | Los wireframes tienen ayuda «i» con la fórmula; el cliente todavía no |

### WCAG 2.2 (nivel AA)

| Criterio | Tema | Resultado | Observación |
|---|---|---|---|
| 1.1.1 | Texto alternativo | ✅ | No hay imágenes informativas |
| 1.3.1 | Información y relaciones | ❌ | Las tablas no tienen `caption` ni `scope` en los encabezados (H-02) |
| 1.4.3 | Contraste mínimo 4.5:1 | ✅ | Aviso `#865` 5.15:1, error `#a00` 7.75:1, enlaces `#06c` 5.57:1 |
| 1.4.11 | Contraste de componentes (3:1) | ❌ | El borde `#999` de las tablas da 2.85:1 (H-01) |
| 2.1.1 | Teclado | ✅ | Todo se alcanza con Tab; las partes del desglose son botones |
| 2.4.3 | Orden del foco | ⚠️ | Al cargar el resultado el foco se queda en el botón (H-07) |
| 2.4.7 | Foco visible | ✅ | Chrome muestra el contorno de foco en los botones del desglose |
| 3.3.1 | Identificación de errores | ⚠️ | El 422 se muestra en el área del resultado, no junto al campo (H-06) |
| 3.3.2 | Etiquetas o instrucciones | ✅ | Todos los campos tienen etiqueta |
| 3.3.4 | Prevención de errores | ✅ | Solo lectura: no hay acciones irreversibles |
| 4.1.3 | Mensajes de estado | ✅ | `role="status"` con `aria-live` en la carga |

## 2. Hallazgos (8)

| ID | Hallazgo | Dónde (evidencia) | Criterio | Impacto en el módulo |
|---|---|---|---|---|
| H-01 | Los bordes de las tablas tienen contraste 2.85:1 | `cliente.html`, regla `td, th { border: 1px solid #999 }` | WCAG 1.4.11 | Con baja visión no se distinguen las celdas: se confunde qué cantidad pertenece a qué sala en el desglose |
| H-02 | Tablas de desglose y registros sin `caption` ni `th scope` | `cliente.html` y W4/W5 | WCAG 1.3.1 | Un lector de pantalla no relaciona «18» con «Medicina»: se pierde la trazabilidad |
| H-03 | El error 503 no ofrece «Reintentar» | `cliente.html`, función `mostrar` | N9 | El usuario recarga y pierde los filtros |
| H-04 | Ocupación aparece como «78 %» en el cliente y «78,0 %» en el wireframe W3 | `cliente.html` vs. W3 | N4 | Dos formas distintas del mismo indicador: duda sobre la precisión |
| H-05 | No hay indicador de nivel ni «volver» entre indicador, desglose y registros | `cliente.html` | N1, N3 | El usuario se pierde en la trazabilidad y repite la consulta |
| H-06 | La fecha inválida solo se avisa al enviar y lejos del campo | W1 y `cliente.html` | N5, WCAG 3.3.1 | Consultas con período invertido (RN-02) |
| H-07 | Tras «Calcular», el foco no pasa al resultado | `cliente.html` | WCAG 2.4.3 | Con teclado o lector de pantalla no se percibe que el número ya llegó |
| H-08 | La ficha de trazabilidad muestra nombres técnicos (`formula`, `periodo`, `calculado_en`) en lugar de etiquetas legibles | `cliente.html`, tabla de la ficha | N2 | El Jefe de área no entiende con claridad de dónde sale el número, que es el propósito de la ficha (RN-05) |

**Cómo se obtuvo la evidencia:** recorrido del cliente solo con teclado (Tab, Enter), revisión de estilos y HTML con las herramientas de desarrollador de Chrome, y prueba del error con la base de datos detenida. Las capturas están en `evidencia/` con el nombre de cada hallazgo (`h-01-bordes.png`, `h-02-tablas.png`, `h-03-sin-reintentar.png`, `h-05-sin-migas.png`, `h-06-fechas.png`, `h-08-ficha-tecnica.png`).

## 3. Backlog priorizado

Prioridad = impacto en la integridad o lectura del indicador × alcance (cuántos usuarios), corregido por esfuerzo.

| Prioridad | ID | Corrección propuesta | Criterio verificable | Esfuerzo |
|---|---|---|---|---|
| 1 | H-02 | `caption` en cada tabla y `scope="col"`/`scope="row"` en los encabezados | Un lector de pantalla (NVDA) lee «Medicina, Camas ocupadas, 18» al recorrer la tabla | Bajo |
| 2 | H-04 | Usar el formateador único con la precisión del indicador en todas las vistas | Ocupación se ve «78,0 %» en tarjeta, ficha, desglose y cliente | Bajo |
| 3 | H-03 | Botón «Reintentar» en el error 503 que repite la consulta con los mismos filtros | Con la base detenida, «Reintentar» envía la misma consulta y los filtros siguen llenos | Bajo |
| 4 | H-01 | Bordes de tabla `#767676` (contraste 4.54:1) | Herramienta de contraste del navegador ≥ 3:1 en los bordes | Bajo |
| 5 | H-06 | Validar fechas al salir del campo y mostrar el mensaje debajo, con `aria-describedby` | Con «desde» > «hasta» el mensaje aparece junto al campo antes de enviar | Medio |
| 6 | H-08 | Mostrar etiquetas legibles: Fórmula, Período, Filtros, Fuente, Calculado el, Detalle | La ficha no muestra guiones bajos ni nombres sin tilde | Bajo |
| 7 | H-07 | Mover el foco al título del resultado al terminar la carga | Tras «Calcular», el foco queda en el nombre del indicador | Bajo |
| 8 | H-05 | Migas «Indicador › Desglose › Medicina» con botones para volver | Desde registros se vuelve al desglose en un clic sin repetir la consulta | Medio |

Las correcciones 1 a 4 y la 6 entran en el prototipo de la semana 11; el resto se incorpora al diseño de la semana 10.