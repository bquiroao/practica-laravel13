# Semana 6 – Primera evaluación parcial: defensa de la arquitectura y cambio práctico

Módulo: Signos vitales y alertas (módulo 13, reasignado dentro del grupo G1) · Grupo G1 · Autor: Joshua Eduardo García Reyes · Cubre las semanas 1 a 5.
Tema defendido: «captura de signos vitales, evaluación de umbrales y generación de alerta».

## 1. Presentación breve (7 diapositivas, 8 minutos)

Archivo: `semana-06/presentacion/semana-06-defensa-signos-vitales.pptx` (y su PDF).

| # | Diapositiva | Mensaje en una frase | Evidencia que se muestra |
|---|---|---|---|
| 1 | Qué se defiende | Una enfermera captura una toma de signos; el sistema valida los valores, los clasifica según la edad y crea alertas. Si un valor es imposible, no se guarda nada. | prueba APP-01 en `micro-his-signos-vitales/tests/ejecutar.php` |
| 2 | Actores y casos de uso (semana 1) | Enfermería registra; enfermería y médico consultan; el sistema genera las alertas. Son seis casos de uso: validar y evaluar son «include» y generar alerta es «extend». | carpeta `semana-01/` |
| 3 | Requisitos y SOLID (semana 2) | Diez RF y siete RNF con criterios de aceptación. La regla de umbral cumple LSP: tres versiones rompían el contrato y la interfaz `ReglaDeUmbral` lo corrige. | `semana-02/ejemplo-lsp/verificar.php` |
| 4 | Capas (semana 3) | Dominio, Aplicación, Persistencia y Presentación. Las dependencias van hacia adentro: el Dominio no conoce a nadie y la prueba ARQ-01 lo vigila. | prueba ARQ-01 en `micro-his-signos-vitales/tests/ejecutar.php` |
| 5 | MVC y Repository (semana 4) | Enrutador, controlador y vista no tienen SQL ni reglas. El caso de uso depende de `RepositorioTomas` y `FuenteClinica`; PDO y memoria responden igual (REP-01). | `micro-his-signos-vitales/src/Aplicacion/Puertos.php` |
| 6 | API y microservicio (semana 5) | Contrato OpenAPI con idempotencia y X-Request-Id. Decisión: no dividir en microservicio mientras no se cumplan criterios medibles. | carpeta `semana-05/` |
| 7 | Cambio práctico y límites | Guardar en archivo se resuelve con un adaptador nuevo del Repository. Límites: el envío real a M21 (RF-07) y reconocer alertas (RF-09) aún no existen. | `semana-06/demostrar-cambio.php` |

## 2. Matriz decisión → RF/RNF → evidencia

Todas las rutas son relativas a `docs/modulos/signos-vitales-alertas/`; `semana-06/demostrar-cambio.php` comprueba que existen y que las pruebas citadas están en `tests/ejecutar.php` (las carpetas de las semanas 1 a 5 que no estén en la copia que se revisa se cuentan aparte).

| Decisión de diseño | RF / RNF | Evidencia |
|---|---|---|
| Solo enfermería registra; enfermería y médico consultan, y solo del mismo hospital | RF-02, RNF-02 | `micro-his-signos-vitales/src/Aplicacion/RegistrarToma.php`, prueba APP-02 en `micro-his-signos-vitales/tests/ejecutar.php` |
| La toma exige un episodio activo | RF-03 | `micro-his-signos-vitales/src/Aplicacion/RegistrarToma.php` (función `exigirEpisodio`), prueba APP-02 |
| Una toma lleva de 1 a 6 mediciones y un valor imposible rechaza toda la toma | RF-01, RF-04 | `micro-his-signos-vitales/src/Dominio/Signos.php`, prueba DOM-01 |
| Cada medición es normal, anormal, crítica o sin rango según la edad; sin rango no se inventa umbral | RF-05, RNF-04 | `micro-his-signos-vitales/sql/esquema.sql`, prueba DOM-02 |
| Se crea una alerta por medición anormal o crítica | RF-06 | `micro-his-signos-vitales/src/Dominio/Toma.php`, prueba APP-01 |
| Toma, mediciones y alertas se guardan en una sola transacción | RNF-01 | `micro-his-signos-vitales/src/Persistencia/TomasPdo.php`, prueba PER-01 |
| La regla de umbral es una interfaz (LSP): una regla nueva no obliga a editar el evaluador | RNF-05, RNF-06 | `semana-02/semana-02-lsp-requerimientos-diseno.md`, `semana-02/ejemplo-lsp/verificar.php` |
| Las dependencias van hacia adentro y el controlador no tiene SQL ni reglas | RNF-05 | prueba ARQ-01 en `micro-his-signos-vitales/tests/ejecutar.php` |
| El caso de uso depende de interfaces: PDO y memoria responden igual y el doble que falla da 500 | RNF-06, RNF-01 | `micro-his-signos-vitales/src/Aplicacion/Puertos.php`, pruebas REP-01 y PER-02 |
| Consultar es de solo lectura y las tomas no se editan ni se borran (se corrige con otra toma) | RF-08, RF-10 | `micro-his-signos-vitales/src/Aplicacion/ConsultarTomas.php`, prueba MVC-01 |
| Las alertas críticas quedan «pendiente_envio» para M21; el envío real y el reconocimiento **aún no existen** y se declaran como límites | RF-07, RF-09 | `micro-his-signos-vitales/src/Persistencia/TomasPdo.php`, prueba APP-01 |

## 3. Recorrido (lo que se hace frente al docente)

1. Correr `php micro-his-signos-vitales/tests/ejecutar.php` desde la carpeta del módulo: todas las líneas en `PASA` (camino feliz, regla de dominio, error de persistencia y contrato del Repository).
2. Mostrar `semana-02/ejemplo-lsp/verificar.php`: antes tres reglas rompen el contrato, después ninguna.
3. Mostrar `src/Aplicacion/Puertos.php`: las dos interfaces que el caso de uso conoce.
4. Correr `php semana-06/demostrar-cambio.php` y mostrar las líneas `PASA` del cambio práctico.

## 4. Registro del cambio práctico

| Campo | Contenido |
|---|---|
| Cambio pedido (simulado en la defensa) | «La base del hospital estará en mantenimiento durante la prueba: las tomas deben guardarse en un archivo.» |
| Impacto en requisitos | RF-01 a RF-06 no cambian; RNF-01 (todo o nada) se mantiene porque cada toma es una sola línea escrita de una vez; episodios y rangos se siguen leyendo de `FuenteClinica`. |
| Decisión | Un adaptador nuevo `TomasEnArchivo` que implementa `RepositorioTomas` (una toma por línea JSON) y se entrega al caso de uso desde `public/index.php`. |
| Qué se cambia | Una clase nueva y el cableado de quien arma `RegistrarToma`. |
| Qué **no** se cambia | `RegistrarToma`, `ConsultarTomas`, `Toma`, `EvaluadorUmbrales`, el controlador, la vista, el esquema SQL y las pruebas existentes. |
| Evidencia | `semana-06/demostrar-cambio.php`: C-01 mismas respuestas que el adaptador en memoria, C-02 una instancia nueva lee lo guardado, C-03 archivo no escribible → `ErrorPersistencia`, C-04 `src/` no menciona la clase nueva. |
| Riesgo y límite | El archivo no da transacciones entre tomas ni escritura desde varios servidores: sirve para la prueba, no para producción. Los episodios y los rangos siguen necesitando la base. |
| Conclusión | Depender de interfaces (semana 4) permitió el cambio extendiendo, no modificando; los pasos de código quedan para otro PR si el docente lo aprueba. |

## 5. Preguntas probables

- **¿Por qué el Dominio no conoce la base?** Porque las reglas de umbral deben poder probarse sin SQL ni HTTP; ARQ-01 falla si alguien importa otra capa.
- **¿Qué pasa si un valor es imposible?** Se rechaza toda la toma y se nombra el signo; no se guarda nada (DOM-01).
- **¿Qué falta?** El envío real a M21 y el reconocimiento de alertas; hoy las críticas quedan «pendiente_envio».
