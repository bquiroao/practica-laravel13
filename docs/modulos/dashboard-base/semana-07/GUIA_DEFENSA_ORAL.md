# Guía de defensa oral — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

> «El dashboard tiene cuatro componentes de frontend y diez de backend, cada uno con su contrato. La medición pone arriba a `ServicioDashboard`, con Ce 7, porque es el orquestador. El acoplamiento caro está entre la UI y el dominio: el prototipo copió los textos y plurales de las tarjetas y tiene su propio mapa de permisos, que además ofrece un módulo que el backend no conoce. El refactor deja que la UI dibuje lo que el backend ya devuelve: menú, estado y texto.»

**¿Qué se queda en el frontend y qué no?** Se quedan cómo se ve cada estado, el esqueleto, el límite de 10 s y el botón «Reintentar»: eso es interfaz. Salen los textos, los plurales y el mapa de permisos, porque son reglas del dominio (R-02, R-05, R-09).

**¿Por qué copiar los textos es un problema si hoy coinciden?** Porque coinciden por casualidad. Cuando se corrija un texto en `Dashboard.php`, como pasó con el plural de la semana 4, la UI seguirá con el viejo y nadie lo notará hasta verlo en pantalla.

**¿Qué demuestra «Usuarios» en el prototipo?** Que ya hay dos reglas R-02 distintas: el menú del prototipo ofrece un módulo que el backend no tiene. Con el refactor, el menú sale del `Catalogo` y no puede ofrecer nada que el backend rechace.

**¿Por qué `ServicioDashboard` con Ce 7 no es el punto a refactorizar?** Porque orquestar es su trabajo y depende de piezas estables (I = 0). Partirlo movería líneas sin reducir lo que hay que tocar cuando cambia una regla.

**¿Qué protege el cambio?** Las pruebas «menú y tarjetas según el rol», «1 cita hoy» en singular y «0 camas = vacío», más la prueba de contrato de la semana 5.
