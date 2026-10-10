# Guía de defensa oral — Semana 3

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md) · **Código:** [`../micro-his-notas-soap/`](../micro-his-notas-soap/)

## 1. Resumen en 60 segundos

> «Hice un micro-HIS en PHP 8.2 sin framework que registra una nota SOAP con su diagnóstico, la firma y la consulta. Está dividido en cuatro capas. Presentación recibe el HTTP; Aplicación coordina los casos de uso; Dominio tiene las reglas, como que las cuatro secciones son obligatorias y que una nota firmada no cambia; y Persistencia guarda en PostgreSQL con PDO y sentencias preparadas. La regla es que todas las dependencias apuntan hacia el Dominio: el Dominio no sabe que existe PDO. Eso lo logro poniendo las interfaces de los repositorios en el Dominio y haciendo que Persistencia las implemente. La vista C4 muestra el sistema en tres niveles y el diagrama de paquetes muestra esa regla. Hay 44 pruebas: camino feliz, reglas de dominio, errores de persistencia con dobles de PDO, y unas pruebas de arquitectura que leen el código y fallan si una capa importa algo que no debe.»

## 2. Orden sugerido de la exposición

1. **Contexto (C4 nivel 1):** quién usa el sistema y de qué otros módulos depende.
2. **Contenedores (C4 nivel 2):** API, base de datos y suite de pruebas.
3. **Paquetes:** la regla Presentación → Aplicación → Dominio ← Persistencia y la tabla de lo permitido.
4. **Vista dinámica:** seguir V-01 a V-30 y luego el `alt` de V-31 a V-35 (reversión).
5. **Demostración:** correr las pruebas y una petición con `curl` (sección 5).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Las flechas apuntan hacia Dominio, no "hacia abajo" a la base | Si el negocio dependiera de la base, cambiar de PostgreSQL a otra cosa obligaría a tocar las reglas clínicas. Así se cambia solo Persistencia. |
| Las interfaces de repositorio están en Dominio | Quien usa la interfaz la define. Si estuvieran en Persistencia, Dominio tendría que importar Persistencia. |
| `contenedor.php` es el único que usa `new` con clases de todas las capas | Es la raíz de composición. Las pruebas arman el mismo sistema con dobles cambiando solo los argumentos. |
| Validar antes de abrir la transacción | Un error del médico (sección vacía, código mal escrito) no debe tocar la base. |
| `PDOException` se convierte en `ErrorPersistencia` | Si subiera hasta el controlador, Presentación tendría que conocer PDO. Además el usuario no debe ver SQL ni el SQLSTATE. |
| `ATTR_EMULATE_PREPARES = false` | El servidor prepara la sentencia; los datos viajan aparte del SQL. |
| Triggers en la base además de reglas en PHP | Si alguien entra directo a la base, PostgreSQL igual impide cambiar una nota firmada. |
| Sin PHPUnit | La consigna pide vanilla y sin dependencias; un arnés de cinco funciones alcanza. |

## 4. Preguntas probables y respuestas

**¿Qué es el modelo C4?**
Una forma de dibujar la arquitectura en niveles de zoom, propuesta por Simon Brown: contexto (el sistema y su entorno), contenedores (lo que se ejecuta o guarda datos), componentes (las piezas dentro de un contenedor) y código. Hice los tres primeros; el cuarto nivel es el diagrama de paquetes.

**¿Cuál es la diferencia entre un contenedor de C4 y un contenedor de Docker?**
En C4 "contenedor" es cualquier cosa que se ejecuta o guarda datos por separado: una API, una base de datos, una app móvil. No tiene que ver con Docker.

**¿Qué es una sentencia preparada y por qué evita la inyección SQL?**
El SQL se envía primero con marcadores (`:id`) y los valores se mandan después, aparte. La base nunca interpreta un valor como parte del SQL, aunque contenga comillas o `DROP TABLE`.

**¿Cómo prueba un error de base de datos sin apagar la base?**
Con dobles. `PdoQueFalla` es una clase que extiende `PDO` pero no se conecta: su `prepare()` lanza la misma excepción que lanzaría PostgreSQL con la conexión caída (SQLSTATE `08006`). Así compruebo que el repositorio la traduce a `ErrorPersistencia` (PER-01). Y en INT-04 uso una base real con un trigger que rompe el `INSERT` del diagnóstico para ver que la nota se revierte.

**¿Qué es un doble de prueba?**
Un objeto que reemplaza a uno real en una prueba. Aquí hay repositorios en memoria (simulan la base), un reloj fijo y dobles de PDO que fallan o que anotan lo que se les pidió.

**¿Cómo sabe que las capas respetan la regla?**
Por las pruebas ARQ. Leen cada archivo de `src/` con `token_get_all()`, sacan los nombres de clase que usa y los comparan con lo permitido. Si un caso de uso importa un adaptador PDO, ARQ-02 falla y dice qué archivo.

**¿Qué pasa si dos médicos intentan firmar la misma nota a la vez?**
El `UPDATE` tiene `WHERE status = 'draft'`. El primero cambia la fila; el segundo afecta cero filas y recibe 409.

**¿Por qué el contenido de la nota es JSON?**
Porque la tabla `clinical_notes` del HIS tiene un solo campo `content` de texto. Guardo `{"S", "O", "A", "P"}` para no perder la separación de secciones.

**¿Qué diferencia hay con lo de la semana 2?**
En la semana 2 separé responsabilidades en clases (SRP). Ahora agrupo esas clases en capas, fijo hacia dónde pueden apuntar las dependencias, agrego PDO real y una API que corre.

**¿Esto reemplaza al HIS en Laravel?**
No. Es un prototipo para probar la arquitectura. Dominio y Aplicación se pueden llevar al HIS tal cual porque no dependen de nada; Persistencia y Presentación se cambian por las de Laravel.

## 5. Recorrido de demostración

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php
```

1. Mostrar el resumen: `Total: 44 ... Fallan: 0` (sin PostgreSQL sale `Omitidas: 1`, que es INT-06).
2. Romper la regla a propósito: agregar en `src/Aplicacion/CasosDeUso/ConsultarNota.php`, junto a los otros `use`, la línea `use NotasSoap\Persistencia\Pdo\PdoRepositorioNotas;`. Correr las pruebas: falla ARQ-02 y dice `Aplicacion/CasosDeUso/ConsultarNota.php usa NotasSoap\Persistencia\Pdo\PdoRepositorioNotas`. Quitar la línea.
3. Si hay PostgreSQL: levantar la API (README del micro-HIS) y enviar `ejemplos/nota.json` con `token-mendez` (201) y con `token-lopez` (403).
4. Abrir `evidencia/salida-api-postgresql.txt` y mostrar el último bloque: el `UPDATE` directo sobre la nota firmada rechazado por el trigger.

## 6. Cambios que podrían pedirte en vivo

| Cambio pedido | Qué modificar |
|---|---|
| «Que el diagnóstico sea opcional» | En `RegistrarNotaConDiagnostico`, crear y guardar el `Diagnostico` solo si viene código; en el controlador, aceptar cuerpo sin `diagnostico`. Ajustar APP-01 y HTTP-04. |
| «Agregar un tipo de diagnóstico `presuntivo`» | Un `case` nuevo en `TipoDiagnostico` y en el `CHECK` de `diagnoses` en `schema.sql`. Ajustar DOM-04. |
| «Cambiar PostgreSQL por MySQL» | Solo Persistencia y `schema.sql`; el DSN cambia en la variable de entorno. Dominio y Aplicación no se tocan. |
| «Validar contra el catálogo CIE-10» | Un puerto nuevo `CatalogoCie10` en Dominio, su adaptador PDO en Persistencia y la verificación en el caso de uso antes de la transacción. |
| «Que enfermería pueda consultar pero no escribir» | Ya es así: `ConsultarNota` no pide rol médico. Para restringir la consulta, agregar la verificación de rol en ese caso de uso. |
| «Mostrar en el diagrama dónde se valida el CIE-10» | En la vista dinámica, V-16/V-17 sobre `Diagnostico`; en componentes, la caja `Diagnostico · TipoDiagnostico` de Dominio. |
