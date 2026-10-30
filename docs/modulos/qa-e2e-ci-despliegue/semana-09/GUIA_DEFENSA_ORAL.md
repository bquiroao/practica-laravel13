# Guía de defensa oral — Semana 9 (evaluación heurística y WCAG)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final

## 1. Resumen en 60 segundos

Evalué los seis wireframes de la consola de release con cuatro métodos:
- las 10 heurísticas de Nielsen;
- 25 criterios de WCAG 2.2 AA;
- contraste **medido** con un script, no estimado;
- un recorrido de teclado.

Además, contrasté cada mensaje con el código del servidor.

**Resultado:** 14 hallazgos, 3 de severidad mayor. Los dos más importantes:
- **H-09.** El botón «Registrar RECHAZADO» promete una decisión que en realidad toma el servidor al registrar.
- **H-10.** El error más frecuente del formulario, 422 `DATOS_INVALIDOS`, no tenía mensaje: el catálogo creía que era un 400.

El backlog tiene 14 ítems, cada uno con un criterio que se comprueba con un comando o con una inspección concreta del prototipo.

## 2. Orden sugerido (8 minutos)

| Paso | Qué mostrar | Minutos |
|---|---|---|
| 1 | Método y escala de severidad | 1:00 |
| 2 | H-09 con su recorte, y B-01 (vista previa sin efectos) | 2:00 |
| 3 | H-10: cómo se encontró leyendo `EscenarioE2E::registrar` | 1:30 |
| 4 | Contraste: correr `contraste.py` (15 fallan → 0 con la paleta propuesta) | 1:30 |
| 5 | H-05 (refresco) y H-01 (botones deshabilitados) en el recorrido de teclado | 1:00 |
| 6 | Backlog: prioridad y en qué semana entra cada ítem | 1:00 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué H-09 es severidad 3 si no es de accesibilidad? | Porque puede registrar una decisión del gate distinta de la que el usuario cree que registra, y una decisión no se edita. La heurística es N-5 (prevención de errores) y el criterio WCAG, 3.3.4. |
| ¿Cómo sabés que el contraste falla? | Lo calculo con la fórmula de luminancia de WCAG sobre los colores exactos del generador. `contraste.py` termina con código 1 si una propuesta no cumple, así que el criterio del backlog se verifica con un comando. |
| ¿El vencimiento de 72 h no viola 2.2.1? | No: es un límite esencial del negocio, y 2.2.1 lo exime. Lo que falla es que no se avisa antes y que no está definido si lo cargado se conserva (H-08). |
| ¿Por qué no deshabilitar los botones? | Un botón deshabilitado no recibe foco: con teclado no se llega ni a él ni a su motivo. Es mejor dejarlo activo y llevar el foco al primer error. |
| ¿Por qué B-01 cambia el contrato? | Porque una vista previa calculada en el cliente duplicaría la política del gate. Con una lectura que usa la misma función pura, la vista y el registro no pueden divergir por código, solo por tiempo, y ese caso se muestra. |
| ¿Cuándo se corrige cada cosa? | Las P1 de UI, en el prototipo móvil de la semana 10. B-01, como el cambio solicitado de la semana 12. Las P2 y P3, en el prototipo navegable de la semana 11, medido con axe-core. |
