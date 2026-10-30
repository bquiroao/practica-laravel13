# Semana 12 – Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

Módulo: Signos vitales y alertas clínicas · Grupo G1 · Autor: Joshua Eduardo García Reyes · Cubre las semanas 7 a 11.
Tema defendido: «captura de signos vitales, evaluación de umbrales y generación de alerta».

## 1. Presentación breve (6 diapositivas, 8 minutos)

| # | Diapositiva | Mensaje en una frase | Evidencia que se muestra |
|---|---|---|---|
| 1 | Qué se defiende | Una enfermera captura una toma y el sistema clasifica cada valor y crea alertas; nada se guarda si un valor es imposible. | `semana-11/prototipo/index.html` |
| 2 | Componentes | Cuatro capas; el Dominio no conoce a nadie y la regla de umbral es intercambiable. | `micro-his-signos-vitales/src/Dominio/Signos.php` |
| 3 | UX por rol | Enfermería captura; médico y enfermería consultan; el error marca el campo y el foco va al mensaje. | `semana-11/prototipo/index.html` |
| 4 | Accesibilidad | Etiqueta por campo, `aria-live`, `role="alert"`, la alerta se distingue por texto y no solo por color. | `semana-11/verificar-prototipo.php` |
| 5 | Móvil | Se usa a 390 px sin scroll horizontal; botones de 44 px. | `semana-11/evidencia/movil-camino-feliz.png` |
| 6 | Qué falta y el cambio pedido | Rangos solo de adultos; el cambio de marcapasos se resuelve sin tocar código existente. | `semana-12/demostrar-cambio.php` |

## 2. Matriz decisión → RF/RNF → evidencia

Todas las rutas son relativas a `docs/modulos/signos-vitales-alertas/`; `semana-12/demostrar-cambio.php` comprueba que existen (las de la semana 11, que se entrega en otro PR, pueden aparecer como «aún sin fusionar»).

| Decisión de diseño | RF / RNF | Evidencia |
|---|---|---|
| Solo enfermería del mismo hospital registra; el médico solo consulta | RF-02, RNF-02 | `micro-his-signos-vitales/src/Aplicacion/RegistrarToma.php`, prueba APP-02 en `micro-his-signos-vitales/tests/ejecutar.php` |
| La toma exige un episodio activo | RF-03 | `micro-his-signos-vitales/src/Aplicacion/RegistrarToma.php` (función `exigirEpisodio`) |
| Una toma lleva de 1 a 6 mediciones con unidad y cada una se valida contra límites físicos | RF-01, RF-04 | `micro-his-signos-vitales/src/Dominio/Signos.php`, prueba DOM-01 |
| Si un valor es imposible se rechaza **toda** la toma y se nombra el signo | RF-04, RNF-07 | `semana-11/prototipo/index.html` (error crítico), `semana-11/evidencia/escritorio-error-critico.png` |
| Cada medición se clasifica normal / anormal / crítica / sin rango según la edad | RF-05, RNF-04 | `micro-his-signos-vitales/sql/esquema.sql` (rangos), prueba DOM-02 |
| Se crea una alerta por medición anormal o crítica | RF-06 | `semana-11/verificar-prototipo.php` (P-03), prueba APP-01 |
| Toma, mediciones y alertas se guardan en una sola transacción | RNF-01 | `micro-his-signos-vitales/src/Persistencia/TomasPdo.php`, prueba PER-01 |
| La regla de umbral es una interfaz: una regla nueva no obliga a editar el evaluador | RNF-05, RNF-06 | `semana-02/semana-02-lsp-requerimientos-diseno.md`, `semana-02/ejemplo-lsp/verificar.php`, `semana-12/demostrar-cambio.php` |
| Consultar tomas es de solo lectura y las tomas no se editan ni se borran (se corrige con otra toma) | RF-08, RF-10 | `micro-his-signos-vitales/src/Aplicacion/ConsultarTomas.php`, prueba MVC-01 |
| Las alertas críticas quedan «pendiente_envio» para M21; el envío real y el reconocimiento de alertas **aún no existen** y se declaran como límites | RF-07, RF-09 | `micro-his-signos-vitales/src/Persistencia/TomasPdo.php`, prueba APP-01 |
| Las pantallas, el error y la alerta no dependen solo del color | RNF-07 | `semana-11/semana-11-prototipo-navegable.md` |

## 3. Recorrido navegable (lo que se hace frente al docente)

1. Abrir `semana-11/prototipo/index.html`. Pantalla `inicio`: dos episodios activos.
2. «Tomar signos» en `epi-1`. Escribir FC 500 y «Guardar toma»: bloque rojo, campo marcado, nada guardado (**error crítico**).
3. Corregir: FC 118, PAS 150, PAD 85, SpO2 97, temperatura 36,8. Guardar: PAS y FC anormales → dos alertas (**camino feliz**).
4. Volver y elegir `epi-2` (niño): la toma se guarda como «sin rango» y se pide revisión manual; no inventa umbrales.
5. Reducir la ventana a 390 px: sin scroll horizontal. Correr `php semana-12/demostrar-cambio.php` y mostrar las líneas `PASA`.

## 4. Registro del cambio solicitado

| Campo | Contenido |
|---|---|
| Cambio pedido (simulado en la defensa) | «Para pacientes con marcapasos, la FC solo debe alertar por exceso: el piso lo fija el dispositivo.» |
| Impacto en requisitos | Modifica RF-05 solo para esa población; RNF-04 se mantiene (todo valor alto crítico sigue siendo crítico). |
| Decisión | Una regla nueva `ReglaLimiteSuperior` que implementa `ReglaDeUmbral` y se entrega al caso de uso con `new EvaluadorUmbrales(['fc' => new ReglaLimiteSuperior(), …])`. |
| Qué se cambia | Una clase nueva y el cableado de quien arma `RegistrarToma`. |
| Qué **no** se cambia | `EvaluadorUmbrales`, `Toma`, `RegistrarToma`, `ReglaPorIntervalo`, `ReglaLimiteInferior`, el esquema SQL y las pruebas existentes. |
| Evidencia | `semana-12/demostrar-cambio.php`: contrato LSP de las tres reglas, FC 35 → normal con la regla nueva y crítica con la estándar, FC 105 y 140 iguales, y prueba de punta a punta por `RegistrarToma`. |
| Riesgo y límite | El modelo de datos aún no guarda «tiene marcapasos»; hoy elegiría el evaluador quien arma la petición. Una bradicardia real en un paciente sin marcapasos **no** debe pasar por esta regla: requiere decisión clínica antes de implementarse. |
| Conclusión | El diseño de la semana 2 permitió el cambio extendiendo, no modificando; los pasos de código quedan para el siguiente PR si el docente lo aprueba. |
