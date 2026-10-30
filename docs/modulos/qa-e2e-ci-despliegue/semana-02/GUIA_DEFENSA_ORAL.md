# Guía de defensa oral — Semana 2 (DIP y requerimientos)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Documento:** [semana-02-dip-requerimientos-diseno.md](semana-02-dip-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

Definí 14 requerimientos funcionales, 8 no funcionales medibles y 14 criterios de aceptación, todos ejecutables.

Apliqué DIP al quality gate:
- **Antes:** el gate creaba el cliente de GitHub, el de S3 y la conexión a la base, leía archivos de `/tmp` y usaba el reloj del sistema. Son 12 dependencias de la regla hacia detalles.
- **Después:** el dominio define cuatro abstracciones con su propio vocabulario, la regla es pura y los adaptadores implementan esas abstracciones. Son 0 dependencias.

Resultado:
- los 14 criterios corren en 0,2 s sin red ni CI;
- la misma ejecución leída en JUnit y en el JSON de otro proveedor da la misma decisión;
- 8 de 8 mutaciones se detectan.

Separar la regla mostró dos cosas: el antes nunca comparaba el digest, y mi primera regla dejaba que omitir un escenario que falla subiera la tasa.

## 2. Demostración sugerida (3 minutos)

1. Abrir `antes/EvaluadorGate.php`: señalar los `new` y el `time()` dentro de la regla.
2. `php medir-dip.php`: 12 → 0.
3. `php verificar.php`: CA-09 prueba el límite de 24 h con un reloj fijo, sin esperar.
4. `php mutaciones.php`: M5 (los omitidos salen de la tasa) lo detecta CA-07.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿DIP no es inyección de dependencias? | No. Inyectar es cómo llega el objeto; invertir es quién define el contrato. Aquí el dominio define `FuenteResultados` con sus palabras, no con las de GitHub. |
| ¿Por qué `QualityGate` no recibe el almacén entero? | Solo necesita preguntar si una huella está confirmada. Pasarle una función es la abstracción mínima. |
| ¿Por qué `EvaluarGate` sí depende de `QualityGate` concreto? | Están en el mismo nivel: es la regla del dominio. Abstraer todo no es DIP, es ruido. |
| ¿Qué demuestra CA-13? | RNF-03: cambiar de proveedor de CI es escribir un adaptador. El mismo run-101 en dos formatos da la misma decisión. |
| ¿Por qué un OMITIDO cuenta como no aprobado? | Si saliera de la tasa, omitir el escenario que falla la subiría. Lo encontré al escribir el criterio. |
| ¿El antes funciona? | No se puede ejecutar sin el runner, la red y las credenciales, y eso es lo que el principio corrige. Además nunca comparaba el digest. |
