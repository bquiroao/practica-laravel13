# Guía de defensa oral — Semana 2

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-02-dip-requerimientos-diseno.md](semana-02-dip-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «Convertí las 12 reglas de la semana 1 en 14 requerimientos funcionales, 8 no funcionales y 17 criterios de aceptación ejecutables. Después apliqué DIP. En el diseño "antes", una sola clase creaba su conexión PDO, escribía el SQL de la tabla de alergias, tenía el catálogo y los permisos como constantes y escribía la traza en un archivo. Como conocía la excepción de PDO, alguien la manejó ahí: si la consulta fallaba, seguía con una lista vacía. Lo medí: con el expediente inaccesible, el "antes" le dice al médico que el paciente no tiene alergias y, si confirma, emite amoxicilina a un alérgico. En el "después", el caso de uso define sus propios puertos, y el de alergias dice en su contrato que una falla se lanza y nunca se devuelve vacía. El adaptador PDO cumple eso, y el resultado es un 503. Lo pruebo con 24 pruebas y 13 mutaciones, todas detectadas.»

## 2. Orden sugerido de la exposición

1. **RF/RNF y criterios:** mostrar que cada criterio es una prueba (CA-01..CA-17) y que corren sin base.
2. **Antes:** el diagrama y el `catch` que devuelve `[]`. Ejecutar DIP-05 y leer el 201.
3. **Definición de DIP:** las dos partes, y que la interfaz es **del alto nivel**.
4. **Después:** el diagrama; las flechas de implementación apuntan hacia la política.
5. **Evidencia:** DIP-01 (análisis del código), DIP-03 (FHIR), M13 (regresión de DIP detectada).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Los puertos viven en `Aplicacion\Puertos` y no junto a los adaptadores | Es la inversión: el que necesita define la interfaz. Si la definiera el adaptador, la política seguiría dependiendo del paquete del detalle |
| La excepción `FuenteAlergiasNoDisponible` es parte del puerto | La política no debe conocer `PDOException`. Si la conoce, termina manejándola, que es justo el defecto del antes |
| El contrato dice «nunca vacío» | Es R-07 escrito donde cualquier adaptador futuro lo hereda |
| `EvaluacionClinica` y las reglas del dominio no tienen interfaz | Son política estable con una sola implementación posible. DIP se aplica a lo volátil, no a todo |
| `composicion.php` es el único lugar con `new` de adaptadores | Cambiar un detalle toca un solo archivo; lo comprueba DIP-02 |
| Se mantuvo una prueba de paridad | Para demostrar que DIP no cambió el comportamiento normal, solo el de la falla |

## 4. Preguntas probables y respuestas

**¿DIP es lo mismo que inyección de dependencias?**
No. La inyección es la técnica (pasar las dependencias por el constructor). DIP es la regla de diseño sobre **la dirección** de la dependencia y **quién es dueño** de la abstracción. Se puede inyectar un `\PDO` por el constructor y seguir violando DIP.

**¿Por qué no hay una interfaz para `VerificadorAlergias`?**
Porque no es un detalle: es la regla. No hay una «otra» implementación válida de «la coincidencia directa bloquea». Una interfaz ahí solo agregaría indirección.

**¿Qué demuestra DIP-03?**
Que se puede escribir una fuente nueva (FHIR `AllergyIntolerance`) en otro lugar, sin tocar ni una línea del caso de uso, y obtener la misma decisión. Es la prueba práctica de que la política no depende del detalle.

**¿Por qué DIP-04 y DIP-05 se omiten sin base?**
Porque prueban el adaptador PDO, que es un detalle y solo se prueba contra una base real. Que la política se pruebe sin base y el detalle con base es justamente el resultado de DIP.

**¿Cómo se sabe que el análisis de DIP-01 no es decorativo?**
Por la mutación M13: meter `new \DateTimeImmutable('now')` en el caso de uso hace fallar DIP-01. Y porque su primera versión dio un falso positivo (un comentario con la palabra PDO), que se corrigió en el instrumento.

## 5. Recorrido de demostración

1. `php ejemplo-dip/verificar.php` → 21 pasan, 3 omitidas.
2. Con `PRESCRIPCIONES_DB_DSN` → 24/24; leer la línea de DIP-05.
3. `php ejemplo-dip/mutaciones.php` → 13/13; señalar M1 (solo la detecta la prueba con base) y M13.
