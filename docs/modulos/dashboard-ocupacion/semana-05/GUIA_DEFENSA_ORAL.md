# Guía de defensa oral — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-05-cliente-servidor.md](semana-05-cliente-servidor.md)

> «La ocupación tiene dos operaciones: consolidar un corte y consultar el último. La consulta nunca recalcula, así que una lectura caída no deja el tablero en blanco. Una prueba de contrato hace pasar 7 escenarios reales por las rutas y el controlador. Evalué extraerlo como modelo de lectura alimentado por eventos; ninguno de los cuatro criterios lo pide hoy, así que sigue como módulo.»

**¿Por qué un modelo de lectura y no un servicio que lea las tablas de otros?** Porque leer la base de otro servicio rompe su frontera: si Catálogo cambia su esquema, ocupación se cae. Con eventos, cada dueño publica lo que cambió y ocupación arma su propia proyección. Solo vale la pena cuando Catálogo o Admisión ya sean servicios.

**¿Cómo se acepta la consistencia eventual en un tablero clínico?** Haciéndola visible. Cada corte dice su hora, y a los 15 minutos aparece el aviso. Jefatura sabe cuán vieja es la cifra antes de decidir dónde ingresar a un paciente.

**¿Por qué documentar un 403 que hoy no ocurre?** Porque el contrato describe la seguridad que el módulo debe tener (R-08) y el lint pedía un 4xx. Lo que no hice fue inventarle un código de error: se fija al corregir H-02.
