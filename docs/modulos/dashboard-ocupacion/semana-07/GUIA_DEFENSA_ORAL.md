# Guía de defensa oral — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

> «El tablero tiene cuatro componentes de frontend y diez de backend, cada uno con su contrato. La medición pone arriba a `Corte`, con Ca 6, y eso está bien: es un objeto estable del dominio que todos usan. El acoplamiento caro está en los indicadores: el prototipo recalcula la ocupación, el "no aplica" y el aviso de 15 minutos, y define por su cuenta el nivel baja, media o alta. El refactor deja todos los indicadores en el dominio, y la UI solo elige el color.»

**¿Por qué un Ca alto en `Corte` no es un problema?** Porque `Corte` es estable (I = 0,25) y cambia poco. El principio de dependencias estables dice que todos deben depender de lo estable. El problema sería un Ca alto en algo volátil, como un controlador.

**¿Por qué el nivel baja, media o alta es una regla del negocio?** Porque decide cuándo una sala está «alta» y eso guía ingresos y traslados. Si solo vive en la UI, el móvil de la semana 10 o un reporte tendrían que copiar los umbrales y podrían usar otros.

**¿El refactor amplía el módulo?** No hay caso de uso ni ruta nueva: el corte que ya devuelve CU-02 suma un campo calculado. La fórmula, el «no aplica» y el aviso ya venían del backend; la UI solo deja de recalcularlos.

**¿Qué se queda en la UI?** Los colores, los textos y cómo se ve el aviso: «alta» en rojo con su palabra, para no depender solo del color (B-04 de la semana 9).

**¿Qué protege el cambio?** Las pruebas «sala A = 50.0 %», «B sin camas operativas = no aplica» y «aviso a los 20 minutos», con los dos adaptadores.
