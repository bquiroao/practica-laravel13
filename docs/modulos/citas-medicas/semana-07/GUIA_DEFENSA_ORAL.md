# Guía de defensa oral — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

> «La agenda tiene cuatro componentes de frontend y nueve de backend, cada uno con un contrato de entrada y salida. Medí el acoplamiento: el tipo más acoplado es `ServicioCitas`, con Ce 5. Dentro de él, la disponibilidad cruza capas: qué conflicto se informa primero está escrito en el SQL de PDO y copiado en el adaptador en memoria, y viaja como texto hasta el servicio. El refactor hace que la persistencia informe solo hechos y que una pieza del dominio, `Disponibilidad`, decida el código.»

**¿Por qué la precedencia del médico es una regla de negocio?** Porque decide qué mensaje ve la recepcionista cuando cruzan el médico y el paciente: E4 o E5. Eso no depende de la base, depende de lo que el hospital quiere comunicar.

**¿Qué tiene de malo devolver `'medico'` o `'paciente'`?** Que es un protocolo de texto: el adaptador y el servicio deben escribir la misma cadena, y un error de tipeo no lo detecta nadie hasta que falla en ejecución. Con hechos tipados y el código decidido en el dominio, eso desaparece.

**¿El refactor cambia el contrato o la concurrencia?** No. Los errores E4 y E5 y las rutas son los mismos, y la barrera de concurrencia de la semana 6 sigue en el `INSERT`.

**¿Por qué `ServicioCitas` sigue teniendo Ce alto después?** Porque es el orquestador: conoce el horario, el estado y la disponibilidad. Lo que baja es lo que importa: los adaptadores dejan de tener reglas y la precedencia pasa de 3 archivos a 1.

**¿Cómo se sabría que no rompió nada?** Las pruebas «médico ocupado = 409 E4» y «paciente ocupado = 409 E5» ya corren con los dos adaptadores y seguirían siendo la red.
