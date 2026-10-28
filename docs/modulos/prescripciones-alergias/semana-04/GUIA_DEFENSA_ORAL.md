# Guía de defensa oral — Semana 4

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-04-capas-mvc-repositorio.md](semana-04-capas-mvc-repositorio.md)

## 1. Resumen en 60 segundos

> «Continué el mismo Micro-HIS.
>
> La entrada quedó en MVC: un formulario que solo valida el formato y toma el usuario y el hospital del token; un controlador que solo coordina; y vistas que son las únicas que conocen HTTP. Una prueba lee el controlador y falla si aparece SQL, un `new`, un código de negocio o un estado HTTP.
>
> El repositorio de prescripciones es una interfaz del Dominio, sin métodos para modificar ni borrar. Cada puerto de datos tiene un adaptador PDO y uno en memoria de producción, que se eligen por configuración. Una suite de contrato corre las mismas siete verificaciones contra los dos, y en su primera ejecución encontró que la dosis volvía como entero en un adaptador y como decimal en el otro.
>
> Para el repositorio compartido analicé la base de cada hospital del HIS. Apliqué mi propuesta sobre las seis migraciones reales y comprobé 13 afirmaciones:
> - el módulo lee las alergias por una vista, sin privilegios sobre la tabla del Expediente;
> - solo prescribe un médico asignado al episodio, la misma regla que el HIS usa para los signos vitales.»

## 2. Orden sugerido de la exposición

1. **Qué cambió** (tabla de §1) y qué **no** cambió: la Aplicación y el Dominio.
2. **MVC:** mostrar el método `emitir()` del controlador y ejecutar ARQ-07.
3. **Repository:** el puerto en el Dominio, los dos adaptadores y la suite de contrato. Contar H-4.
4. **Repositorio compartido:** el diagrama, las tres opciones y por qué B. Ejecutar el validador.
5. **H-5:** la mutación que sobrevivió y la prueba que faltaba.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| El repositorio en el Dominio; los demás puertos en la Aplicación | El repositorio guarda el agregado del dominio; las alergias, el catálogo o la traza son necesidades del caso de uso |
| Adaptadores InMemory **de producción**, no solo de prueba | La tarea los pide; permiten una demo y un servidor sin base; y las pruebas usan código real, decorado con fallas |
| Suite de contrato | Dos implementaciones de un puerto deben comportarse igual. Sin contrato, los dobles «mienten» (H-4) |
| Leer alergias por una **vista** | El Expediente es dueño de `allergies`: Prescripciones no debe poder leer columnas que no necesita ni escribir nada |
| Trigger de asignación del prescriptor | El HIS ya aplica esa regla a `vital_signs`; prescribir sin estar asignado al paciente sería un hueco |
| Repositorio compartido (opción B) y no API (opción C) para alergias | R-03 exige verificar en el momento de emitir: en la misma base, la lectura y la escritura son consistentes, y no se suma un punto de falla remoto |

## 4. Preguntas probables y respuestas

**¿Por qué el formulario no valida las reglas, por ejemplo la dosis?**
Porque son responsabilidades distintas. El formulario valida que la petición se pueda **leer** (400). Si fuera más allá, la regla quedaría duplicada en dos capas y podrían contradecirse. MVC-04 muestra los dos casos en el mismo endpoint.

**¿Qué significa que el repositorio esté «en el límite apropiado»?**
Que la interfaz la define quien la usa (el Dominio) y habla su idioma: prescripciones, claves y hospitales, no tablas ni SQL. Por eso los casos de uso no saben si detrás hay PostgreSQL o memoria.

**¿Cómo sabe que el adaptador en memoria se comporta como el de PostgreSQL?**
Por la suite de contrato: las mismas 7 verificaciones corren contra los dos. Las mutaciones M16 y M17 rompen solo el de memoria y solo falla su fila.

**¿Qué es H-5 y por qué importa?**
Al hacer más fiel el adaptador en memoria, una mutación que antes se detectaba empezó a sobrevivir. La prueba de idempotencia pasaba gracias a que el doble **no** reproducía la restricción de la base. Faltaba probar que un reintento no vuelve a verificar; ahora lo prueba APP-17.

**Un repositorio compartido, ¿no acopla demasiado?**
Sí acopla, y por eso se pone en tres lugares concretos:
- una vista con solo las columnas necesarias;
- FK a tablas estables (pacientes, episodios, usuarios);
- privilegios por rol.

Lo que compramos con ese acoplamiento es consistencia en una verificación de seguridad del paciente.

## 5. Recorrido de demostración

1. `php bin/demo.php` (sin base): 8 pasos.
2. `php tests/ejecutar.php --solo=CON,MVC,ARQ` con base.
3. `php bin/validar-integracion-hospital.php`: 13/13.
4. Abrir `evidencia/6-contrato-h4-en-rojo.txt` y `evidencia/7-mutaciones-m8-sobrevive.txt`.
