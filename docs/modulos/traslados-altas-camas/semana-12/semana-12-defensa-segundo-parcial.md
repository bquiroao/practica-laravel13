# Semana 12 – Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

Módulo: Traslados, altas y camas · Grupo G1 · Autor: Joshua Eduardo García Reyes · Cubre las semanas 7 a 11.
Tema defendido: «traslado o alta del paciente con liberación consistente de cama».

## 1. Presentación breve (6 diapositivas, 8 minutos)

| # | Diapositiva | Mensaje en una frase | Evidencia que se muestra |
|---|---|---|---|
| 1 | Qué se defiende | Un movimiento libera la cama de origen y ocupa la de destino en un solo paso, o no hace nada. | `semana-11/prototipo/index.html` |
| 2 | Componentes | Cuatro capas; traslado y alta son piezas con el mismo contrato (LSP) que el caso de uso no distingue. | `micro-his-traslados/src/Dominio/Movimientos.php` |
| 3 | UX por rol | Enfermería y admisión registran; el médico no. El error nombra el motivo, marca el campo y no cambia ninguna cama. | `semana-11/prototipo/index.html` |
| 4 | Accesibilidad | Estado de cama escrito con texto, etiqueta por campo, `aria-live`, `role="alert"`, foco al mensaje. | `semana-11/verificar-prototipo.php` |
| 5 | Móvil | Se usa a 390 px sin scroll horizontal; botones de 44 px; tablas con desplazamiento propio. | `semana-11/evidencia/movil-camino-feliz.png` |
| 6 | Qué falta y el cambio pedido | Faltan historial, aviso a limpieza y salida temporal; la cama de aislamiento se resuelve con una clase de dominio. | `semana-12/demostrar-cambio.php` |

## 2. Matriz decisión → RF/RNF → evidencia

Las rutas son relativas a `docs/modulos/traslados-altas-camas/`; `semana-12/demostrar-cambio.php` comprueba que existen (las de la semana 11, que se entrega en otro PR, pueden aparecer como «aún sin fusionar»).

| Decisión de diseño | RF / RNF | Evidencia |
|---|---|---|
| La orden médica de traslado o alta es obligatoria y vigente antes de mover a nadie | RF-01, RF-09 | `micro-his-traslados/src/Aplicacion/RegistrarMovimiento.php`, prueba APP-03 en `micro-his-traslados/tests/ejecutar.php` |
| El traslado ocupa la cama destino elegida al ejecutar | RF-02 | `micro-his-traslados/src/Dominio/Movimientos.php` (`Traslado`), prueba APP-01 |
| El alta exige motivo y toda alta libera la cama, incluso la voluntaria | RF-03 | `micro-his-traslados/src/Dominio/Movimientos.php` (`Alta`), pruebas APP-02 y DOM-02 |
| El destino debe ser distinto, estar disponible y ser del tipo pedido (UCI) | RF-04 | `micro-his-traslados/src/Dominio/Movimientos.php`, prueba DOM-01 |
| La cama de origen queda en mantenimiento en el mismo movimiento | RF-05, RNF-01 | `micro-his-traslados/src/Persistencia/MovimientosPdo.php`, prueba PER-01 |
| La cama actual del paciente se muestra; el historial completo **no existe** todavía | RF-06 | `semana-11/prototipo/index.html` |
| El aviso a limpieza (RF-07) y la salida temporal (RF-08) **no existen**: se declaran como límites | RF-07, RF-08 | `semana-12/semana-12-defensa-segundo-parcial.md` |
| Cinco códigos de rechazo estables en un solo mapa código → HTTP | RF-09, RNF-06 | `micro-his-traslados/src/Presentacion/VistaJson.php`, prueba MVC-01 |
| Los movimientos no se editan ni se borran; un error se corrige con otro movimiento | RF-10 | `micro-his-traslados/src/Presentacion/Enrutador.php` (solo `POST`) |
| Ocupar, liberar y cerrar episodio van en una transacción; falla a la mitad → rollback | RNF-01, RNF-02 | `micro-his-traslados/src/Persistencia/MovimientosPdo.php`, prueba PER-01 |
| Solo enfermería o admisión; el médico recibe 403 | RNF-03 | `micro-his-traslados/src/Aplicacion/RegistrarMovimiento.php`, prueba APP-03 |
| El rendimiento (< 2 s) **no está medido** | RNF-04 | `semana-12/semana-12-defensa-segundo-parcial.md` |
| Un movimiento nuevo cumple el contrato y no obliga a cambiar el caso de uso | RNF-05, RNF-06 | `semana-02/semana-02-lsp-requerimientos-diseno.md`, `semana-02/ejemplo-lsp/verificar.php`, `semana-12/demostrar-cambio.php` |

## 3. Recorrido navegable (lo que se hace frente al docente)

1. Abrir `semana-11/prototipo/index.html`. Pantalla `pacientes`: cuatro pacientes con cama y orden.
2. «Registrar movimiento» en `epi-1`; elegir como destino la cama A3 (ocupada): bloque rojo, campo marcado, ninguna cama cambió (**error crítico**).
3. Elegir A2 y registrar: Ana está en A2 y A1 quedó en mantenimiento (**camino feliz**).
4. Con el rol Médico intentar lo mismo: `acceso_denegado`.
5. Reducir la ventana a 390 px: sin scroll horizontal de página. Correr `php semana-12/demostrar-cambio.php` y mostrar las líneas `PASA`.

## 4. Registro del cambio solicitado

| Campo | Contenido |
|---|---|
| Cambio pedido (simulado en la defensa) | «Epidemiología pide poder ordenar el traslado a camas de aislamiento.» |
| Impacto en requisitos | Amplía RF-04 con un tipo de cama más; no cambia RF-05, RF-09 ni RNF-01. |
| Decisión | Generalizar el requisito de cama: `Traslado` compara con el tipo de cama que pide la orden en lugar de tener «uci» escrito. La clase `TrasladoGeneral` de la demostración lo hace y se comporta igual que `Traslado` en las 56 combinaciones de hoy. |
| Qué se cambia | `src/Dominio/Movimientos.php` (el enum de requisitos y la comparación), `sql/esquema.sql` (los `CHECK` de `beds.type` y del requisito) y los datos de demostración de `EnMemoria`. |
| Qué **no** se cambia | `RegistrarMovimiento`, `MovimientosPdo`, `Enrutador`, `MovimientosControlador`, `VistaJson` y las pruebas existentes. |
| Evidencia | `semana-12/demostrar-cambio.php`: contrato LSP de tres movimientos, equivalencia con el comportamiento actual, aislamiento aceptado y rechazos con su código, y lista exacta de archivos que mencionan «uci». |
| Riesgo y límite | **El diseño no es cerrado del todo**: el requisito está escrito en tres archivos, no solo en una clase nueva. El cambio es pequeño y localizado, pero es una edición, no una extensión pura. En PostgreSQL falta además la migración del `CHECK` de `beds.type`. |
| Conclusión | El contrato LSP de la semana 2 se cumple y protege el caso de uso; la generalización del requisito queda propuesta para el siguiente PR si el docente la aprueba. |
