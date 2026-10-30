# Guía de defensa oral — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-05-cliente-servidor.md](semana-05-cliente-servidor.md)

> «El expediente tiene tres operaciones: abrir, consulta local y consulta longitudinal, descritas en un OpenAPI que pasa el lint. Una prueba de contrato hace pasar 9 escenarios reales por las rutas y el controlador de la semana 4. Evalué la frontera y concluí que lo que se separaría no es el expediente, sino el índice central que comparten los hospitales; hoy hay una sola instalación, así que ningún criterio lo pide.»

**¿Por qué no separar el expediente si es el dato más sensible?** Porque sus datos son del hospital: separarlo no los protege más, solo añade red. Lo que sí cruza hospitales es el índice central (quién tiene expediente dónde y con qué consentimiento), y por eso esa es la frontera que vale la pena vigilar.

**¿Qué pasa con una revocación de consentimiento si hay caché?** La caché dura como máximo 60 s y se invalida con el evento de revocación. Además, la vigencia se vuelve a comprobar en cada consulta (R-10). La reversa de la migración se activa si un consentimiento revocado se acepta después de ese plazo.

**¿Por qué la prueba de contrato no corre en esta rama?** Porque las rutas llegan con la semana 4 (PR #349), aún sin integrar, y no copié su código. El script lo dice y sale con 1; la evidencia se tomó sobre una copia de ese commit. Cuando se integre, corre en `develop` sin cambios.
