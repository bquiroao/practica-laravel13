# Respuesta documentada a un cambio solicitado (segundo parcial)

El cambio real lo pide el ingeniero en la defensa. Esto es un **caso preparado**; la sección 3 se llena después.

## 1. Caso preparado — aviso de pocas camas disponibles

**Enunciado supuesto:** «En la lista de camas, mostrar cuántas quedan y avisar cuando queden 2 o menos para ese requerimiento.»

| Capa | Cambio | Regla o requisito afectado |
|---|---|---|
| UI (`ListaCamasDisponibles`) | Leyenda «Quedan N camas» y aviso «Pocas camas disponibles» cuando N ≤ 2, con `role="status"` | RNF-07: mensaje de estado anunciado |
| API | Ninguno: `GET /camas-disponibles` ya devuelve `total` | Contrato v1 sin cambio incompatible |
| Application / Domain | Ninguno: es presentación, no una regla clínica | — |
| Móvil | La leyenda va bajo el título del paso 2; no agrega una pantalla | Semana 10, jerarquía |

**Riesgos:**
- El número puede cambiar enseguida, por concurrencia. El texto dice «al momento de la consulta» y muestra «Actualizado hace N s».
- Una alerta muy frecuente se ignora. El umbral (2) queda como parámetro y se acuerda con supervisión de camas.

**Pruebas:**
1. Lista con 2 camas → aparece el aviso.
2. Lista con 3 → no aparece.
3. Lista vacía → estado vacío, sin el aviso.
4. El aviso tiene `role="status"`.

**Esfuerzo:** pequeño (solo UI). Es un buen ejemplo de por qué la UI no tiene reglas: el cambio no toca dominio ni persistencia.

## 2. Otro caso preparado — el médico pide ver la cama en el móvil

Ya cubierto: W-06 es de solo lectura y en móvil se apila. Basta confirmar que la ruta de detalle no exponga datos que no sean el código.

## 3. Registro de la defensa (llenar después)

| Campo | Valor |
|---|---|
| Fecha | `<por llenar>` |
| Cambio solicitado (textual) | `<por llenar>` |
| Capas y archivos afectados según la respuesta | `<por llenar>` |
| Reglas y requisitos afectados | `<por llenar>` |
| Demostración usada (prototipo o captura) | `<por llenar>` |
| Observaciones del ingeniero | `<por llenar>` |
