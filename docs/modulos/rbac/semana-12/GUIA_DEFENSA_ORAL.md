# Guía de defensa oral — Semana 12 (segunda evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-12-defensa-ux-rbac.md](semana-12-defensa-ux-rbac.md)

## 1. Plan de la defensa (12–15 minutos)

| Min. | Qué | Material |
|---|---|---|
| 0–1 | Portada: 3 cifras | Diapositiva 1 |
| 1–6 | Componentes, UX, accesibilidad y movilidad | Diapositivas 2–5 |
| 6–10 | **Recorrido en vivo**: parte A (camino feliz) y parte B (error crítico) | Prototipo + [recorrido-navegable.md](recorrido-navegable.md) |
| 10–13 | Cambio solicitado: problema, opciones, pruebas, medición, costo | Diapositiva 7 + [registro](registro-cambio-solicitado.md) |
| 13–15 | Matriz y riesgos; preguntas | Diapositiva 8 + [matriz](matriz-decision-rf-rnf-evidencia.md) |

Las notas del orador de cada diapositiva están en el `.pptx`.

## 2. Resumen en 60 segundos

> «Defiendo cuatro tipos de decisión.
>
> En **componentes**, la interfaz solo muestra; el servidor decide cada operación.
>
> En **UX**, toda denegación deja una referencia y un siguiente paso, pero nunca dice por qué.
>
> En **accesibilidad**, medí con axe y con teclado: cero infracciones en 32 pantallas, después de corregir 24 fallas reales.
>
> En **movilidad**, es una sola app mobile-first, y medí qué reintentos son seguros cuando se corta la red.
>
> Lo demuestro con el prototipo: Carlos asigna un rol a Luis y Luis opera; a Ana le revocan el rol con la pantalla abierta, el servidor la deniega y el auditor encuentra el motivo con la referencia.
>
> El cambio solicitado fue que la bitácora tenía denegaciones que nadie intentó. Lo resolví separando lo que la interfaz muestra, que ya no se audita, de lo que una persona intenta, que sí. Pasé de 9 a 0 denegaciones fantasma sin perder ninguna real, con pruebas escritas antes del código.»

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación corta |
|---|---|
| La UI no decide | Cualquiera puede llamar a la API sin la interfaz. Ocultar es experiencia de uso; proteger es del servidor. |
| 403 sin motivo | Decir «te falta tal permiso» le enseña a un atacante qué buscar. El motivo lo ve quien tiene `auditoria.consultar`. |
| Confirmar con el nombre | Fue el hallazgo más grave de la S9: una errata en el identificador da acceso clínico a otra persona. |
| Mobile-first, una sola app | Lo esencial se diseña con el menor espacio. Una base de código evita que escritorio y móvil digan cosas distintas. |
| No encolar cambios de permisos sin red | Un permiso debe cambiar cuando el administrador lo decide, con el contexto que tiene a la vista. |
| B-6 con la opción D | Reutiliza la **misma** política del PDP (PEF-03: 42 combinaciones iguales) y no toca puertos ni adaptadores. |
| Ampliar ARQ-08 por nombre | La guarda sigue fallando ante cualquier otra ruta sin permiso. |

## 4. Preguntas probables y respuestas

**¿Por qué no simplemente dejar de auditar las denegaciones del PDP?**
Porque otros módulos del HIS usan el PDP para intentos reales, y R-06 exige que toda denegación quede registrada. El problema no era el PDP sino que la interfaz lo usaba para algo que no es un intento.

**¿Permisos efectivos no permite «tantear» sin dejar rastro?**
Solo responde por la propia sesión, que sale del token, y no da motivos. Saber qué permisos tengo yo no es sensible. Y cualquier intento real (una operación, entrar a una ruta del servidor) se sigue auditando: PEF-10 y el recorrido lo verifican.

**¿Cómo sabes que lo que muestra la UI coincide con lo que decide el servidor?**
Las dos cosas usan la misma instancia de `PoliticaAutorizacion`. PEF-03 compara 7 usuarios × 6 permisos contra el PDP: 0 diferencias. Y si la caché de la UI queda vieja, el servidor igual decide: es el error crítico B4.

**¿Qué cuesta el cambio?**
- Tres clases nuevas.
- El controlador de autorización sube de 7 a 10 dependencias.
- El Ce medio pasa de 2.47 a 2.49.
- El contrato pasa a v1.2.0, compatible porque solo agrega.

Podría separar el controlador, pero ambas operaciones son del mismo recurso y no mezclan más preocupaciones.

**¿Qué es la paridad y por qué importa?**
El prototipo trae un servidor simulado para abrirse sin instalar nada. La paridad envía la misma secuencia de 38 solicitudes al kernel PHP real y a la simulación, y compara respuestas y número de eventos en la bitácora. Si alguien cambia una regla en un lado y no en el otro, falla.

**¿De dónde salen los RNF-10 a RNF-14?**
Las semanas 8 a 11 ya verificaban esos criterios, pero no estaban escritos como requisitos. Los formalicé en esta entrega y lo dejo dicho en la matriz.

**¿Qué falta?**
- Probar con un lector de pantalla real.
- Correr el prototipo contra el backend por red con PostgreSQL.
- Cerrar B-1, B-4 y B-5 en el contrato.

## 5. Recorrido en vivo (atajos)

- Si se rompe el estado: Ingresar → «Reiniciar demostración».
- Cambiar de persona: avatar → «Cambiar de usuario».
- Revocar a Ana sin cambiar de sesión: avatar → «Otra sesión: Carlos revoca Enfermería de Ana».
- Vista móvil: F12 → modo dispositivo → 360 px.

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Agrega un permiso a lo que consulta la UI» | `PERMISOS_UI` en `app.js` (y en la prueba si es nuevo). La ruta acepta hasta 20. |
| «Haz que la consulta también devuelva el rol» | No: violaría R-08 en la UI. Si se pide, se discute; el rol se ve en la tabla de roles con `roles.asignar`. |
| «Muestra que un intento sigue auditado» | Ana → `#/roles` por URL → Carlos → Auditoría con esa referencia: `PERMISO_NO_ASIGNADO`. |
| «Corre las pruebas del cambio» | `php tests/ejecutar.php` y buscar `PEF-` |
