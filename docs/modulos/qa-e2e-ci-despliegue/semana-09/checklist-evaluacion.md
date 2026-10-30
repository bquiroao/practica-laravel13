# Checklist de evaluación heurística y WCAG 2.2 AA — consola de release (wireframes W1–W6 y flujos de la semana 8)

**Estudiante:** Boris Alexander Quiroa Orellana

**Objeto evaluado** (semana 8):
- [wireframes](../semana-08/wireframes/semana-08-wireframes-qa-e2e.pdf);
- [flujos](../semana-08/semana-08-ux-qa-e2e.md#4-user-flows-por-rol);
- catálogo de mensajes;
- contrato `api/openapi.yaml` 1.1.0 y el código del servidor.

**Leyenda:** ✅ cumple · ⚠️ cumple en parte · ❌ no cumple. Cada ⚠️ y ❌ remite a un hallazgo H-xx.

## 1. Heurísticas de Nielsen

| # | Heurística | Estado | Evidencia en el diseño |
|---|---|---|---|
| N-1 | Visibilidad del estado del sistema | ⚠️ | Hay progreso «4 de 6», cuenta regresiva de la aprobación y estados por pantalla. Pero el refresco automático no dice qué cambió, y el aviso de REVERTIDO no tiene pantalla → **H-05, H-12** |
| N-2 | Coincidencia con el mundo real | ⚠️ | Los mensajes van en lenguaje natural (M-01…M-21). Pero aparecen códigos del sistema: EN_CURSO, TENANT_AJENO, `e2e.consultar` → **H-13** |
| N-3 | Control y libertad | ✅ | [Cancelar] en cada formulario; el plan se puede rehacer hasta que se congela; nada se registra sin confirmar |
| N-4 | Consistencia y estándares | ⚠️ | «✕ no cumple» lleva texto y «✓» no. El catálogo dice que un formato inválido es 400, pero el servidor responde 422 `DATOS_INVALIDOS` → **H-10, H-11** |
| N-5 | Prevención de errores | ⚠️ | Los críticos no se pueden quitar, el digest se valida en vivo y el botón nombra la decisión. Pero ese botón nombra una decisión que el servidor podría no tomar → **H-09** |
| N-6 | Reconocer antes que recordar | ⚠️ | La política, la guía y el entorno actual están a la vista. Pero el digest abreviado obliga a comparar de memoria → **H-07** |
| N-7 | Flexibilidad y eficiencia | ✅ | Propuesta automática del plan, copia del digest, exportar JSON y enlace directo desde el PR |
| N-8 | Diseño estético y minimalista | ✅ | Una acción principal por pantalla; las acciones de otros roles no se muestran |
| N-9 | Ayudar a reconocer, diagnosticar y recuperarse de errores | ✅ | Cada 409 y 422 dice qué hacer: [Agregar los que faltan], [Ver E2E-107], «evaluar otra vez» |
| N-10 | Ayuda y documentación | ⚠️ | Las ayudas A-01…A-04 explican crítico, INESTABLE, gate y smoke. Pero los botones deshabilitados no dicen por qué → **H-01** |

## 2. WCAG 2.2 nivel AA

| Tema | Criterio | Estado | Evidencia |
|---|---|---|---|
| **Teclado** | 2.1.1 Teclado | ⚠️ | Todo tiene acción de teclado, salvo los botones de envío deshabilitados: no reciben foco ni explican nada → **H-01** |
| | 2.1.2 Sin trampa de teclado | ✅ | No hay diálogos modales en W1–W6; las confirmaciones son en la misma página |
| | 2.4.1 Evitar bloques | ✅ | La cabecera es corta; se define «Saltar al contenido» |
| **Foco** | 2.4.3 Orden del foco | ✅ | El orden sigue la lectura. Tras un error, el foco va al campo marcado |
| | 2.4.7 Foco visible | ✅ | Contorno de 2 px #3D4F6B definido en la paleta |
| | 2.4.11 Foco no oculto | ✅ | Sin barras fijas que tapen contenido |
| | 3.2.1 Al recibir el foco | ✅ | Nada cambia de contexto al enfocar |
| | 3.2.2 Al introducir datos | ✅ | Pegar el digest valida en el lugar; elegir FALLA en el smoke muestra el aviso, sin navegar |
| **Tiempo** | 2.2.1 Tiempo ajustable | ⚠️ | El vencimiento de 72 h es una regla del negocio (excepción: esencial). Pero no se avisa antes de que venza ni se dice si lo cargado se conserva → **H-08** |
| | 2.2.2 Pausar, detener, ocultar | ❌ | W3 se actualiza cada 15 s, sin forma de pausar → **H-05** |
| **Contraste** | 1.4.1 Uso del color | ✅ | Los estados llevan palabra: PASA, FALLA, INESTABLE, «no cumple», «4 de 6» |
| | 1.4.3 Contraste del texto | ❌ | 14 pares fallan: texto secundario (2,85–3,26:1), placeholder (2,51:1) y chips de estado (3,75–4,25:1) → **H-02, H-03, H-04** ([contraste-wcag.txt](evidencia/contraste-wcag.txt)) |
| | 1.4.11 Contraste de no-texto | ❌ | Bordes de campos y casillas: 2,64:1 → **H-03** |
| | 2.5.8 Tamaño del objetivo | ❌ | Botones de 0,34 in (≈ 33 px) ✅. Pero el icono de copiar el digest mide ≈ 14 px → **H-06** |
| **Etiquetas** | 1.1.1 Contenido no textual | ❌ | El icono de copiar, el 🔒 de los críticos, el ★ de W3 y el ✓ de W4 no tienen texto → **H-06, H-11** |
| | 1.3.1 Información y relaciones | ✅ | Las tablas tienen encabezados; los campos, su etiqueta visible |
| | 2.4.4 Propósito de los enlaces | ⚠️ | «log», «traza» y «[Descargar]» se repiten sin el escenario → **H-14** |
| | 3.3.2 Etiquetas o instrucciones | ✅ | Todo campo tiene etiqueta; la guía muestra el formato «guia-AAAA.N» |
| | 4.1.2 Nombre, función, valor | ❌ | El icono de copiar no tiene nombre. Un botón deshabilitado no expone el motivo → **H-01, H-06** |
| **Mensajes** | 3.3.1 Identificación de errores | ✅ | El error va junto al campo, en texto y con borde, después de la interacción |
| | 3.3.3 Sugerencias ante errores | ⚠️ | Cada mensaje del catálogo dice qué hacer. Pero `DATOS_INVALIDOS`, el que más devuelve el servidor en W1, no tiene mensaje → **H-10** |
| | 4.1.3 Mensajes de estado | ❌ | El refresco de W3 cambia resultados sin anunciarlos → **H-05** |
| **Prevención de errores** | 3.3.4 Prevención (legal, financiera, datos) | ⚠️ | Decisión del gate y despliegue con confirmación y consecuencia. Pero la decisión registrada podría no ser la de la vista previa → **H-09** |
| | 3.3.7 Entrada redundante | ⚠️ | Un 409 o un 422 conserva lo escrito. Pero no está definido para `APROBACION_VENCIDA` → **H-08** |
| | 3.3.8 Autenticación accesible | ✅ | La identidad la da el HIS, sin pruebas cognitivas |

## 3. Criterios propios del módulo

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| P-1 | No existe «aprobar igual» ni «editar decisión» | ✅ | W4: solo «Registrar \<decisión\>»; el historial no se edita |
| P-2 | El digest se puede verificar en cada pantalla donde importa | ⚠️ | Está en W2, W5 y W6, pero abreviado → **H-07** |
| P-3 | Lo que la UI anticipa, el servidor lo exige (y no al revés) | ✅ | Críticos 🔒 ↔ `PLAN_INCOMPLETO`; digest en vivo ↔ `DIGEST_DISTINTO`; formato ↔ `DATOS_INVALIDOS` |
| P-4 | Cada código que la UI puede recibir tiene su mensaje | ❌ | Falta `DATOS_INVALIDOS` (W1, W5) → **H-10** |
| P-5 | Un resultado de despliegue nunca se oculta | ⚠️ | W5 avisa REVERTIDO, pero el responsable de release no tiene dónde verlo → **H-12** |
| P-6 | Solo datos sintéticos en pantalla | ✅ | W1 exige `SINT-…`; la evidencia se muestra por huella, sin previsualizar |

**Resultado:** 41 ítems (10 heurísticas, 25 criterios WCAG y 6 propios):

| Estado | Cantidad |
|---|---|
| ✅ | 19 |
| ⚠️ | 14 |
| ❌ | 8 |

## 4. Recorrido de teclado

Se simuló Tab, Shift+Tab, Enter, Espacio, Esc y las flechas, en el orden que definen las anotaciones.

| Pantalla | Recorrido | Problema |
|---|---|---|
| W1 | Buscar → Registrar escenario → filas → Id → Título → Módulo → Crítico (Espacio) → «?» → Requisitos → Datos → Cancelar → Registrar | Con un campo en rojo, «Registrar» está deshabilitado: Tab lo salta y el usuario no sabe por qué (**H-01**) |
| W2 | Casillas opcionales (Espacio) → Guardar plan | Los críticos 🔒 están deshabilitados: Tab los salta y el lector no dice que son obligatorios (**H-11**) |
| W3 | Filas → enlaces de evidencia → copiar huella → Descargar | El refresco cada 15 s reescribe la tabla bajo el foco (**H-05**); «log» y «Descargar» se repiten (**H-14**) |
| W4 | Política ⓘ → reglas → Ver evidencia → Cancelar → Registrar RECHAZADO | Sin problema de teclado; el problema es de contenido (**H-09**) |
| W5 | Abrir guía → copiar digest → Entorno → Guía → Digest → PASA/FALLA (flechas) → Cancelar → Registrar | El icono de copiar es una parada sin nombre (**H-06**); «Registrar» deshabilitado se salta (**H-01**) |
| W6 | Exportar JSON → pasos de la línea de tiempo → acciones según el rol | La acción deshabilitada («Desplegar») no recibe foco y su motivo es texto suelto (**H-01**) |
