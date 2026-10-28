# Guía de defensa oral — Semana 10 (movilidad)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-10-movil-auditoria.md](semana-10-movil-auditoria.md)

## 1. Resumen en 60 segundos

En el teléfono nadie hace una auditoría completa: se responde **una pregunta concreta**. Por eso hay atajos de rango, tarjetas con qué, quién y cuándo, el detalle plegado y la verificación pensada para pegar el ancla. Son 4 pantallas responsive, y 5 reglas de breakpoint se **verifican** con un script en 39 vistas.

Lo más defendible es que **medí** qué pasa al reintentar con mala señal:
- los movimientos nunca cambian;
- cada lectura que llega deja su registro;
- la bitácora se observa a sí misma: repetir la consulta trae arriba la consulta anterior.

Por eso no hay reintentos automáticos.

## 2. Orden sugerido

1. Jerarquía de contenido (§2) con una captura de 360 px.
2. Reglas de breakpoint y `verificar-responsive.cjs` en vivo (§4).
3. Los tres defectos que encontró el proceso (§4.1), incluido el del propio instrumento.
4. La tabla de reintentos (§6.3) y las decisiones que salen de ella.
5. Los dos escenarios.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no reintentar solo, como hacen muchas apps? | Medido: cada reintento que llega al servidor deja otro registro de lectura. En un módulo de auditoría, esa decisión es del usuario. |
| ¿Por qué no guardar los resultados para leerlos sin conexión? | La bitácora no debe quedar en el teléfono (semana 8). Lo cargado se puede leer mientras la pantalla sigue abierta. |
| ¿Cómo sabe que a 320 px no se rompe nada? | El script mide desborde, objetivos táctiles, fuente de los campos, alcance visible y datos en la URL en 39 vistas: 0 fallas. Además miré las capturas, y así encontré un defecto que el script no veía. |
| ¿Qué pasó con el instrumento? | Chrome en modo móvil ensancha `innerWidth` cuando el contenido no cabe. Lo corregí midiendo contra `clientWidth`, y probé que ahora detecta el defecto (6 fallas sin el arreglo). |
| ¿Por qué «Cancelar» abajo en la hoja? | Es donde cae el pulgar, y es la opción segura. |

## 4. Demostración

```bash
cd docs/modulos/gobernanza-auditoria/semana-10
node herramientas/verificar-responsive.cjs prototipo capturas
php herramientas/reintento.php 2>/dev/null
# Abrir prototipo/m1-consultar.html?estado=red en modo móvil (F12) y pulsar «Buscar en toda la red»
```
