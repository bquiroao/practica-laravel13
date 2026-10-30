# Guía de defensa oral — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

> «El catálogo tiene cuatro componentes de frontend y ocho de backend, cada uno con una sola responsabilidad y un contrato de entrada y salida. Medí el acoplamiento del backend: las dependencias ya apuntan hacia el dominio estable. El acoplamiento caro está entre capas: las transiciones de estado están escritas en el dominio y otra vez en el prototipo, con una regla por rol que solo existe en el cliente. El refactor deja una sola fuente: el dominio publica los destinos y la UI solo los dibuja.»

**¿Por qué el controlador con I = 1 no es un problema?** Porque es concreto y nadie depende de él: está arriba, cambia con facilidad y no rompe a nadie. El dominio tiene I = 0, y todo depende de él, como pide el principio de dependencias estables.

**¿Qué mide `medir-acoplamiento.php` y qué no?** Cuenta los tipos propios que cada clase usa (Ce) y los que la usan (Ca). No ve el JavaScript, por eso la duplicación de R-05 en `app.js` la encontré leyendo el prototipo.

**¿Por qué la regla de rol en el cliente es un riesgo?** Porque el cliente se puede saltar: un `PATCH` directo con un rol de supervisor podría inactivar una cama. Las reglas de autorización van en el backend (R-07), y la UI solo refleja lo que recibe.

**¿El refactor amplía el módulo?** No. Los destinos son la parte de lectura de CU-03, que ya pide elegir el nuevo estado. No hay caso de uso nuevo, y el cambio de R-05 pasa de tocar 2 archivos a tocar 1.

**¿Cómo se sabría que el refactor no rompió nada?** Las pruebas de `EstadoCama` (R-05 y R-06) siguen siendo la red, y la prueba de contrato de la S5 obliga a documentar el campo `destinos` antes de que la UI lo use.
