# Guía de defensa oral — Semana 12 (segunda evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-12-evaluacion-parcial-qa.md](semana-12-evaluacion-parcial-qa.md) · **Presentación:** [PDF](presentacion/semana-12-evaluacion-qa-regresion.pdf)

## 1. Resumen en 60 segundos

El prototipo se recorre en escritorio y en móvil con tres flujos:
- **Camino feliz:** el tester registra y el Líder QA cierra con GO.
- **Error crítico:** una falla con foto clínica sin red, que se reenvía sin duplicar.
- **Cambio de esta semana:** el Responsable de producto ve la trazabilidad por requisito, en solo lectura.

Un recorrido automático lo hace todo: 56/56 comprobaciones y 0 violaciones de axe-core.

La matriz une 17 decisiones con su requisito y su evidencia, y mostró dos cosas:
- cinco decisiones de accesibilidad no tienen requisito (propongo RNF-09 y RNF-10);
- RF-11 se cumplía en el servidor y no en la interfaz.

El cambio lo hice con la prueba primero. La prueba encontró que en móvil el rol no se veía.

## 2. Orden de la presentación (10 minutos)

| Diapositiva | Minutos | Mensaje |
|---|---|---|
| 1. Portada | 0:30 | Tres cifras: 56/56, 0 violaciones, 17 decisiones |
| 2. Recorrido | 1:30 | Tres flujos, dos anchos, cada paso verificado |
| 3. Matriz | 2:00 | Dos huecos: accesibilidad sin requisito y RF-11 a medias |
| 4. Cambio | 2:30 | Pedido → rojo → implementación → verde |
| 5. Hallazgos | 1:30 | Cada instrumento vio algo distinto |
| 6. Cierre | 1:00 | Lo que no cambió, y cómo verificarlo en vivo |
| Demostración | 1:00 | Pasos 8 y 9 del guion: producto → Ver por requisito, en 360 px |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué ese cambio y no otro? | Lo pidió un actor que existía desde la semana 1 y no tenía pantalla. Además, la matriz mostraba que RF-11 estaba a medias en la interfaz. |
| ¿Ocultar el botón Cerrar es seguridad? | No. La seguridad la da el servidor: sin `qa.cerrar` responde 403 (APP-09). Ocultar el botón es UX, para no ofrecer lo que va a fallar. |
| ¿Por qué el cliente no calcula si el requisito bloquea? | Porque usa `bloquea_go`, que manda el servidor. Si la regla se escribe dos veces, una copia diverge: eso pasó en la semana 11 (H-3). |
| ¿Cómo sabe que no rompió nada? | Los flujos A y B siguen con sus 46 comprobaciones, axe-core da 0 en las 44 capturas y el `diff` no toca `micro-his-qa/`. |
| ¿Por qué la prueba falló en móvil y no en escritorio? | A 360 px la etiqueta del rol se oculta para que la barra entre. Para un rol sin botones eso dejaba al usuario sin saber por qué no podía cerrar. |
| ¿Qué es lo que todavía no funciona? | Los requisitos sin caso, la otra mitad de RF-11. El prototipo arma los requisitos desde los casos, así que no puede mostrar uno que no tenga caso. |
| ¿Por qué proponer RNF-09 si ya cumple WCAG? | Porque hoy nada obliga a seguir cumpliéndolo. Una decisión sin requisito es la primera que se quita cuando hay prisa. |

## 4. Si algo falla en vivo

- **El navegador no abre el archivo:** usar las capturas c01–c03 de la carpeta `capturas/movil/`.
- **El recorrido tarda:** mostrar [cambio-2-despues-verde.txt](evidencia/cambio-2-despues-verde.txt).
