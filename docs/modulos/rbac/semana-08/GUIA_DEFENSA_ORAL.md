# Guía de defensa oral — Semana 8

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-08-ux-rbac.md](semana-08-ux-rbac.md)

## 1. Resumen en 60 segundos

> «Diseñé la experiencia de uso de RBAC para tres perfiles: el administrador que asigna y revoca roles, el usuario clínico que intenta una operación, y el auditor que investiga una denegación.
>
> Hay un user flow por rol y seis wireframes anotados. La idea central es que la interfaz no decide: solo muestra las acciones que el servidor permite, y el servidor vuelve a autorizar cada una. Si no puede preguntar, deshabilita todo (fail-closed).
>
> Cuando a alguien se le niega algo, no le decimos por qué, porque eso revelaría la estructura de permisos. Pero le damos una referencia para copiar y le decimos qué hacer. Esa misma referencia es la que el auditor pega en su pantalla para ver el motivo real. Así la denegación es trazable de punta a punta.
>
> Cada mensaje de la interfaz corresponde a un código real del backend, y al diseñar encontré tres endpoints que el contrato todavía no tiene.»

## 2. Orden sugerido de la exposición

1. **Roles y pantallas (§2):** quién ve qué. Mencionar que el auditor es un permiso, no un rol nuevo.
2. **Principios (§3):** P-1 «la UI no decide» y P-3 «denegar sin revelar».
3. **Recorrido de punta a punta** con los wireframes:
   1. W3: el administrador revoca el rol de Ana.
   2. W4: Ana intenta registrar signos y recibe el aviso con la referencia.
   3. W5: así se ve la misma denegación en una página completa.
   4. W6: el auditor pega la referencia y ve `MEMBRESIA_NO_VIGENTE`.
4. **W2:** asignar, con confirmación y validaciones.
5. **Catálogo de mensajes y estados (§6, §8).**
6. **Protección de datos (§10) y brechas (§11).**

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Ocultar las acciones no permitidas en vez de mostrarlas deshabilitadas | Mostrar «Nueva prescripción» en gris a enfermería revela que esa función existe y es de otro rol. Ocultarla es más limpio, y el servidor protege de todos modos. |
| Excepción: si el PDP falla, deshabilitar (no ocultar) y avisar | El usuario debe saber que algo falló y poder reintentar (M-13). Ocultar todo sin explicación sería confuso. |
| El 403 sin motivo | R-08 y RNF-04. El motivo queda en la bitácora para quien tiene `auditoria.consultar`. |
| Referencia copiable en cada denegación | Es el hilo que une al usuario, al administrador y al auditor (CU-04 → CU-06). |
| Confirmación al asignar con los permisos que se otorgan | El administrador piensa en «roles»; el efecto real son permisos. Mostrar los permisos hace la decisión informada. |
| Revocar con diálogo, motivo y casilla; foco en «Cancelar» | Es destructivo e inmediato. El camino por defecto debe ser el seguro. |
| Validar en el cliente con las reglas del contrato | El usuario se entera antes. El servidor sigue validando (es la autoridad). |
| Mismo 404 para «no existe» y «es de otro hospital» | Si fueran distintos, un auditor podría confirmar que existen eventos en otros hospitales. |
| Caché de decisiones de 30 s, solo en memoria | Equilibrio entre no saturar el PDP y no mostrar permisos viejos. Se borra al cambiar de hospital o cerrar sesión. |

## 4. Preguntas probables y respuestas

**¿Por qué no basta con ocultar el botón?**
Porque cualquiera puede llamar a la API directamente. Ocultar es experiencia de uso; proteger es del servidor. Por eso el flujo clínico tiene **dos** autorizaciones: al cargar la pantalla y al ejecutar.

**¿Qué pasa si le revocan el rol a Ana mientras tiene la pantalla abierta?**
La caché puede decir «permitido» hasta 30 s. Pero al pulsar, el servidor vuelve a autorizar, deniega y registra. Ana ve el aviso M-10 con la referencia. Es el caso que une W3 → W4 → W6.

**¿Dónde están los estados vacío, carga y éxito?**
En la matriz §8 y en cada wireframe. Por ejemplo, W1 tiene los tres más el error recuperable dibujados como mini-pantallas.

**¿Qué es un error recuperable?**
Uno en el que el usuario puede seguir sin empezar de cero: corregir un campo, reintentar o elegir otro hospital. En los diagramas se marcan con `detach`: el flujo vuelve a la pantalla.

**¿Por qué el auditor no es un rol?**
Porque RBAC autoriza por permisos. Diseñar por `auditoria.consultar` hace que, si mañana se crea un rol «auditor», W6 no cambie (OCP).

**¿Cómo sabes que los mensajes son correctos?**
Cada uno sale de un código real de `micro-his-rbac/src/` o del `pattern` del OpenAPI. La tabla §11 los cruza wireframe por wireframe.

**¿Qué no pudiste diseñar completo?**
W1 necesita listar los roles de un usuario, y ese endpoint no existe (B-1). Tampoco hay un catálogo de roles por API (B-2). Las documenté como brechas en vez de inventar endpoints.

**¿Y la accesibilidad?**
- Estados con icono y texto, no solo color.
- Avisos con `aria-live`; el 403 con `role="alert"`.
- Foco controlado en el diálogo; `Esc` cierra.

## 5. Demostración en vivo

1. Abrir [wireframes/semana-08-wireframes-rbac.pptx](wireframes/semana-08-wireframes-rbac.pptx) y recorrer W3 → W4 → W5 → W6.
2. Abrir [diagramas/semana-08-flujo-clinico.png](diagramas/semana-08-flujo-clinico.png) y seguir la rama 403.
3. Mostrar que el mensaje existe en el backend:

```bash
cd docs/modulos/rbac/micro-his-rbac
grep -rn "MOTIVO_INSUFICIENTE\|AUTORREVOCACION" src/
```

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Agrega un estado que falta» | Añadir la fila o la celda en §8, una mini-pantalla en el wireframe y la rama en el `.puml`. |
| «Muestra el permiso que falta en el 403» | Responder que no: viola R-08. Ese dato ya está en W6 para quien tiene `auditoria.consultar`. |
| «Agrega “suspender rol”» | Mismo patrón que W3 (diálogo con motivo); mensaje nuevo M-22; nueva rama en el flujo de revocar. |
| «Dibuja el flujo de laboratorio» | Es el mismo flujo clínico con `resultados.registrar`; solo cambia la acción visible en W4. |
