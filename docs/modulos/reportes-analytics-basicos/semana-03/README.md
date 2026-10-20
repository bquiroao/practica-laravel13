# Reportes y analytics básicos — Semana 3

**Tema:** diseño arquitectónico, vistas y patrones.
**Tarea individual:** iniciar «Micro-HIS Reportes y analytics básicos» en PHP 8.2+ vanilla (sin framework), con Presentation, Application, Domain y Persistence, PDO con sentencias preparadas y pruebas de camino feliz, regla de dominio y error de persistencia.
**Entregable:** vista arquitectónica C4/UML del módulo y sus dependencias.
**Proceso guía:** «cálculo y visualización trazable de un indicador hospitalario» (el mismo de las semanas 1 y 2).

## Qué resuelve el Micro-HIS

El Micro-HIS ejecuta el proceso completo para un reporte de ejemplo, **Ocupación de camas**, con los tres niveles de trazabilidad del módulo:

| Nivel | Pregunta | Ejemplo (datos ficticios) | Regla |
|---|---|---|---|
| 1. Indicador | ¿Cuánto? | Ocupación del 01/10 al 31/10: 78 % (39 de 50 camas), con su ficha de trazabilidad | RN-05 |
| 2. Desglose | ¿Dónde? | Medicina 18, Cirugía 14, Pediatría 7 (suma 39) | RN-07 |
| 3. Registros de origen | ¿Qué registros lo forman? | Las 18 camas de Medicina con su estancia y el código del paciente | RN-07, RN-09 |

A diferencia de la semana 2, los datos ya no son constantes en el código. Salen de **PostgreSQL 16 en Docker** (puerto 55440), con tablas que usan los mismos nombres que la base HOSPITAL del SHI (`ward`, `bed`, `bed_stay` y `appointment`), claves UUID, fechas `timestamptz` y datos ficticios (pacientes solo con código, por ejemplo `PAC-00451`). Hay dos bases: `micro_his` para ejecutar y `micro_his_test` para las pruebas. Se eligió PostgreSQL por coherencia con el proyecto: el SHI usa PostgreSQL federado y las pruebas de aceptación del repositorio se hacen en PostgreSQL.

## Las 4 capas

| Capa | Responsabilidad | Componentes |
|---|---|---|
| **Presentation** | Recibir la petición, convertirla en `Filtros` y mostrar el resultado | `public/index.php` (punto de entrada), `VistaIndicador` |
| **Application** | Orquestar el caso de uso y validar el permiso del rol (RN-01) | `CalcularIndicador`, `PoliticaPermisos` |
| **Domain** | Reglas del negocio, sin dependencias externas | `ContratoReporte`, `Filtros`, `Indicador`, `Desglose`, `RegistroOrigen`, `ReporteOcupacion`, `ReportRegistry` y la interfaz `LectorOcupacion` |
| **Persistence** | Leer los datos con PDO y sentencias preparadas | `ConexionPdo` (lee el DSN de `config/app.ini`), `OcupacionPdo` (implementa `LectorOcupacion`) |

### Dependencias permitidas y prohibidas

| Desde | Puede depender de | Nunca de |
|---|---|---|
| Presentation | Application | Domain directamente para lógica, Persistence, PDO |
| Application | Domain y las interfaces que define el dominio | PDO, SQL, HTML |
| Domain | Nada externo (solo PHP) | Application, Presentation, Persistence, PDO |
| Persistence | Domain (implementa sus interfaces) | Application, Presentation |

Todas las dependencias apuntan hacia el **dominio**. El dominio define qué datos necesita (`LectorOcupacion`) y Persistence lo implementa con PDO (inversión de dependencias). Así el cálculo no sabe qué motor de base de datos se usa, y se puede probar con un doble. Las piezas se conectan en un solo lugar, `bootstrap.php`.

## Evolución del contrato de la semana 2

El `ContratoReporte` se conserva tal como quedó al aplicar LSP y suma un método para el nivel 3:

| Método | Nivel | Postcondición |
|---|---|---|
| `tipo()` y `dimensiones()` | — | Cada reporte declara sus dimensiones en lugar de fallar |
| `calcular(Filtros): Indicador` | 1 | Si la unidad es %, el valor está entre 0 y 100; incluye ficha de trazabilidad (RN-05) |
| `desglosar(Filtros, dimension): Desglose` | 2 | `Desglose::total()` es igual a `Indicador::numerador` (RN-07) |
| `registros(Filtros, dimension, parte): RegistroOrigen[]` | 3 | La cantidad de registros es igual al valor de esa parte; el paciente va solo con código (RN-09) |

La nueva postcondición lleva RN-07 hasta el nivel 3: en cada nivel se puede comprobar que el indicador está formado exactamente por los registros que se muestran.

## Patrones aplicados

| Patrón | Dónde aparece | Para qué |
|---|---|---|
| Arquitectura en capas | Las cuatro capas y la tabla de dependencias | Separar responsabilidades y controlar quién depende de quién |
| Inversión de dependencias / Puertos y adaptadores | `LectorOcupacion` (puerto del dominio) y `OcupacionPdo` (adaptador) | Que el dominio no dependa de PDO ni del motor |
| Strategy | Cada reporte implementa `ContratoReporte` | Intercambiar reportes sin cambiar el cliente (LSP de la semana 2) |
| Registry | `ReportRegistry` | Obtener el reporte por su tipo sin conocer clases concretas (OCP) |
| Value Object | `Filtros`, `Indicador`, `Desglose`, `RegistroOrigen` | Datos inmutables que validan sus invariantes al crearse (RN-02, RN-07) |
| Composition Root | `bootstrap.php` | Crear y conectar todas las piezas en un único lugar |
| Test Double | `LectorQueFalla` en las pruebas | Simular una falla de la base sin romper nada real |

## Por qué PDO

- **Seguridad:** las sentencias preparadas evitan la inyección SQL.
- **Configuración fuera del código:** el DSN (`pgsql:host=127.0.0.1;port=55440;dbname=micro_his`) está en `config/app.ini`; cambiar de base o de servidor no toca las capas superiores.
- **Errores controlados:** con `PDO::ERRMODE_EXCEPTION`, una falla de la base se convierte en un error de la aplicación que el usuario ve como un mensaje claro, sin detalles técnicos.

## Vista C4 (PlantUML)

| Diagrama | Nivel C4 | Qué muestra |
|---|---|---|
| `diagramas/c4-contexto.puml` | 1. Contexto | Administrador, Jefe de área y Médico consultan el módulo; los datos de los 10 reportes vienen de los módulos fuente, solo en lectura (RNF-04) |
| `diagramas/c4-contenedores.puml` | 2. Contenedores | Navegador, aplicación PHP del Micro-HIS y las bases PostgreSQL `micro_his` y `micro_his_test` |
| `diagramas/c4-componentes.puml` | 3. Componentes | Los componentes de cada capa y sus dependencias hacia el dominio |

## Pruebas automatizadas

| Prueba | Tipo | Qué demuestra |
|---|---|---|
| Camino feliz | Base de prueba `micro_his_test` | La ocupación da 78 %, el desglose suma 39 y Medicina devuelve 18 registros |
| Regla de dominio | Dominio puro | Un período invertido se rechaza (RN-02) |
| Error de persistencia | Doble `LectorQueFalla` | La aplicación responde un error controlado, sin exponer detalles técnicos |

## Organización de la entrega

- **Parte A:** este README y los tres diagramas C4.
- **Parte B:** capa Domain del Micro-HIS (contrato, objetos de valor, reporte de Ocupación y puerto `LectorOcupacion`).
- **Parte C:** PostgreSQL en Docker, esquema y datos ficticios, y capa Persistence con PDO.
- **Parte D:** capas Application y Presentation, `bootstrap.php`, pruebas y evidencia de ejecución en `evidencia/`.

El código vive en `docs/modulos/reportes-analytics-basicos/micro-his/`, fuera de la carpeta de la semana, porque es el **mismo** Micro-HIS que continúan las semanas 4 y 5.

## Cómo ejecutarlo

```bash
cd docs/modulos/reportes-analytics-basicos/micro-his
docker compose up -d
php database/crear.php
php pruebas/ejecutar.php --evidencia=../semana-03/evidencia/salida-pruebas.txt
php -S localhost:8000 -t public
```

- Nivel 1: `/?reporte=ocupacion&desde=2026-10-01&hasta=2026-10-31`
- Nivel 2: agregar `&nivel=desglose&dimension=sala`
- Nivel 3: agregar `&nivel=registros&dimension=sala&parte=Medicina`

## Fuentes

- Brown, S. *The C4 model for visualising software architecture*. https://c4model.com/
- The PHP Group. *PHP Data Objects (PDO)*. https://www.php.net/manual/es/book.pdo.php
- The PHP Group. *Supported Versions*. https://www.php.net/supported-versions.php
- The PostgreSQL Global Development Group. *PostgreSQL 16 Documentation*. https://www.postgresql.org/docs/16/
