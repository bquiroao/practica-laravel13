# Guía de defensa oral — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documentos:** [presentación](semana-12-presentacion.md) y [recorrido, matriz y cambio](semana-12-defensa-prototipo.md)

> «Con el prototipo muestro el dashboard en 10 pasos: cada tarjeta carga y falla por su cuenta, vacío no es error, reintentar es a pedido, y lo que el rol no permite se explica dentro del layout. El cambio solicitado fue avisar antes de que venza la sesión: un aviso con `role="alertdialog"` que recibe el foco, no deja salir con Tab y se cierra con Esc. Así nadie pierde lo que estaba viendo por un 401 inesperado.»

**¿Por qué `alertdialog` y no un mensaje en la región de estado?** Porque pide una decisión urgente: seguir o salir. Un mensaje de estado se anuncia, pero no lleva el foco ni exige respuesta.

**¿Por qué el foco no puede salir del aviso con Tab?** Porque el aviso es modal: si el foco se fuera detrás, el usuario de teclado seguiría trabajando sin saber que la sesión está por vencer. Al cerrarlo, el foco vuelve donde estaba.

**¿Qué criterio de WCAG cumple?** El 2.2.1, tiempo ajustable: avisar antes de que se acabe el tiempo y permitir extenderlo con una acción simple.

**¿El aviso reemplaza el control de sesión?** No. La sesión la controla el backend (R-01) y el 401 sigue limpiando la sesión (R-07). El aviso solo evita perder el trabajo.

**¿Qué error tuviste al hacerlo?** Al reintentar un reemplazo que había fallado a medias, el aviso quedó duplicado en `index.html`, con dos elementos con el mismo `id`. Lo vi en el diff y lo quité antes de medir.
