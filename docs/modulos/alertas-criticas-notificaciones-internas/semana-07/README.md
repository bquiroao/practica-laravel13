# Semana 7 — Diseño de componentes y refactorización

**Tarea:** diseñar los componentes backend y frontend propios del módulo para «detección, envío, acuse y escalamiento de una alerta crítica», y refactorizar conceptualmente el punto con mayor acoplamiento, separando UI, aplicación, dominio y persistencia sin ampliar el módulo.

## 1. Componentes

`diagramas/componentes.png`.

| Componente | Lado | Responsabilidad |
|---|---|---|
| `BandejaAlertas` | Frontend | Lista del tenant ordenada por prioridad y antigüedad; estados vacío, carga y error |
| `TarjetaAlerta` | Frontend | Código de paciente, prioridad (texto + color), estado, nivel y tiempo restante |
| `PanelDetalle` | Frontend | Datos de la alerta y acciones según estado y rol |
| `BotonAcuse` | Frontend | Llama al acuse; seguro de repetir porque el acuse es idempotente |
| `FormularioCierre` | Frontend | Nota obligatoria; solo visible para médico o jefe de servicio |
| `LineaTiempoHistorial` | Frontend | Historial (RN-10) en orden |
| `AvisoEscalamiento` | Frontend | Aviso de escalamiento y de escalamiento agotado |
| `ClienteApiAlertas` | Frontend | Único lugar que conoce la URL, el token y el formato de error de la API |
| `ApiAlertas` / `AlertaController` | Backend · Presentation | Entrada JSON / HTML |
| Casos de uso, `NotificarNivel`, `DespachadorNotificaciones` | Backend · Application | Orquestación |
| `Alerta`, `PoliticaEscalamiento`, puertos | Backend · Domain | Reglas |
| `PdoAlertaRepository`, `NotificadorSimulado` | Backend · Persistence | Adaptadores |

## 2. Contratos de entrada y salida

| Componente | Entrada | Salida | Errores |
|---|---|---|---|
| `ClienteApiAlertas.registrar` (sistema origen) | `{codigo_paciente, servicio, id_origen, prioridad, descripcion}` + token | `{alerta, duplicada}` | 400, 401, 403, 422, 503 |
| `ClienteApiAlertas.listar` | token | `{alertas[], total}` | 401, 403, 503 |
| `ClienteApiAlertas.detalle` | `id` + token | `{alerta + historial[]}` | 404 |
| `ClienteApiAlertas.acusar` | `id` + token | `{alerta, cambio}` | 403 (RN-07), 404 |
| `ClienteApiAlertas.cerrar` | `id`, `{nota}` + token | `{alerta}` | 403 (rol), 422 (RN-09) |
| `RegistrarAlertaCritica.ejecutar` | arreglo de datos con tenant y origen | `{alerta, duplicada}` | `ReglaDominioException`, `ErrorPersistenciaException` |
| `AcusarAlerta.ejecutar` | `id, usuario, tenant` | `{alerta, cambio}` | `AlertaNoEncontradaException`, `ReglaDominioException` |
| `NotificarNivel.ejecutar` | `Alerta`, momento | (modifica la alerta) | — |
| `PuertoNotificacion.enviar` | `usuario, mensaje` | `bool` | excepción = intento fallido |
| `AlertaRepository` | ver semana 4 | ver semana 4 | `ErrorPersistenciaException` |

## 3. Punto de mayor acoplamiento

`NotificarNivel` (semana 3) mezcla en un mismo bucle cuatro de los siete temas del flujo: **destinatarios**, **entrega y reintentos**, **escalamiento** y **prioridad del nivel**. La decisión «¿termino, subo de nivel o agoto?» (RN-03, RN-05, RN-08) está repartida entre seis `if` intercalados con llamadas al directorio y al despachador. Por eso solo se puede probar armando un escenario completo con dobles.

### Antes
`diagramas/refactor-antes.png`: un bucle con decisiones y efectos mezclados.

### Después (conceptual)
`diagramas/refactor-despues.png`: la decisión se extrae a una regla pura del dominio, `ReglaEscalamientoInmediato::decidir(nivel, entregados, fallidos, hayDestinatarios)`, que devuelve `TERMINAR`, `SUBIR_NIVEL` o `AGOTAR`. `NotificarNivel` solo orquesta: pide destinatarios, despacha, registra y aplica la decisión.

| Criterio | Antes | Después |
|---|---|---|
| Dónde vive la regla RN-08 de «escalar de inmediato» | Application, mezclada con efectos | Domain, función pura |
| Cómo se prueba | Escenario con dobles por cada combinación | Tabla de verdad de 6 filas, sin dobles |
| Ramas en `NotificarNivel` | 6 decisiones | 1 `match` sobre la decisión |
| Capas | Sin cambio de responsabilidades externas | UI, aplicación, dominio y persistencia quedan separadas |
| Alcance | — | **No amplía el módulo:** mismas reglas y mismo comportamiento |

**Justificación:** es el único punto donde cambia la combinación de destinatarios, entrega, escalamiento y reintentos. Una regla pura la deja revisable de un vistazo (semana 13) y probable por caja blanca (semana 15).

**Estado:** refactor **conceptual**, como pide la tarea. El código actual sigue con la versión «antes», cubierta por las pruebas CA-04, CA-06 y CA-13. Aplicarlo es un cambio candidato que se registra en la semana 13.

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.
