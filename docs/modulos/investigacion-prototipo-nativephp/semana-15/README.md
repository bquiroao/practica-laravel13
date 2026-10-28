# Semana 15 — Pruebas del prototipo NativePHP

**Estudiante:** Lopez Perez, Mercedes Azucena

Flujo acotado: consultar una orden ficticia del HIS y notificar el estado. Se prueba el contrato PHP de [semana 2](../semana-02/semana-02-isp-nativephp.md), sin modificarlo ni probar módulos ajenos. El proyecto no instala NativePHP: la capacidad nativa se sustituye por un doble. No hay dispositivos, permisos del SO, backend autenticado ni sincronización real en esta evidencia.

## Comandos y resultados

Desde la raíz del worktree, PHP 8.2+ CLI sin Composer:
```powershell
php docs/modulos/investigacion-prototipo-nativephp/semana-15/pruebas/run.php
php docs/modulos/investigacion-prototipo-nativephp/semana-15/pruebas/run.php --defectos
php docs/modulos/investigacion-prototipo-nativephp/semana-02/ejemplo-isp/verificar.php
```

PHP 8.2.33: **13 casos, 0 fallidos** (4 unitarios, 3 caja blanca, 6 caja negra). Modo defectos: **2 reproducciones confirmadas**, no dos correcciones ni dos validaciones de comportamiento correcto. La regresión de semana 2 también pasó. Cada fallo inesperado devuelve código de salida 1; la reproducción devuelve 0 solo si observa exactamente el defecto documentado. Fuentes, comandos y salidas se incluyen para repetir el experimento.

## Matriz RF/RNF → prueba

RF-01 a RF-07 y RNF-01 a RNF-06 conservan la definición de semana 2. Se agregan criterios exploratorios propios de la evaluación, no requisitos clínicos globales:

- EV-01: no declarar viabilidad nativa sin verificar runtime y plataforma; si falta permiso o HIS, clasificar como condicional.
- EV-02: refrescar desde el HIS cuando responde; si falla, un snapshot previo debe seguir marcado como obsoleto. No se permite escritura offline.

| Caso | Tipo | RF/RNF | Entrada o condición | Resultado esperado |
|---|---|---|---|---|
| U01 | Unitaria | EV-01, RNF-06 | Runtime/plataforma no verificados; permiso/HIS ausentes; todos verificados | NOT_DEMONSTRATED; CONDITIONAL; VIABLE_FOR_DEMO respectivamente |
| U02 | Unitaria | EV-02, RF-02, RNF-04 | Remoto nuevo; remoto nulo con/sin snapshot | Dato fresco HIS; snapshot obsoleto; sin dato |
| U03 | Unitaria | RF-05 | Notificador UNAVAILABLE | No registra envío |
| U04 | Unitaria | RF-06, RNF-01 | Exportación de texto ficticio | Solo interviene el contrato exportador |
| W01 | Caja blanca | RF-01, RNF-04 | Identificador en blanco | Retorno temprano; cero llamadas gateway/notifier |
| W02 | Caja blanca | RF-04 | Gateway devuelve null | Una consulta, cero notificaciones |
| W03 | Caja blanca | RF-03, RF-05 | SENT frente a UNAVAILABLE | Ambas ramas del resultado y número de envíos |
| B01 | Caja negra | RF-01, RF-03 | Orden conocida | NOTIFIED |
| B02 | Caja negra | RF-01, RNF-04 | Vacío, 1 y 3 espacios, tabulador/salto | INVALID_INPUT |
| B03 | Caja negra | RF-04 | Orden inexistente | HIS_UNAVAILABLE |
| B04 | Caja negra | RF-05, RNF-04 | Capacidad sin permiso, simulada | NOTIFICATION_UNAVAILABLE |
| B05 | Caja negra | RF-07, RNF-03 | Respuesta reconocida | Texto genérico sin paciente, ID ni valor clínico |
| B06 | Caja negra | RF-03 | Los cuatro estados del enum | NOTIFIED para cada estado reconocido |

Las pruebas caja negra usan entradas y resultados públicos sin inspeccionar ramas internas. Se mantiene la salida de notificaciones como observable de privacidad. Caja blanca usa espías para comprobar los retornos tempranos y ambas ramas del resultado. La enumeración de ramas es manual, no una medición de porcentaje con Xdebug. No se inventa un máximo de longitud de ID no definido: los límites probados son vacío y solo blancos.

## Diseño y alcance de políticas

src/Evaluation.php implementa dos políticas pequeñas para evaluar el experimento, separadas del prototipo anterior. ViabilityPolicy recibe evidencias booleanas; no detecta versiones de NativePHP ni compatibilidad del sistema operativo. El caso de todos verdaderos es una prueba de la política, no la afirmación de que este equipo cumpla esas condiciones.

SyncPolicy modela selección de lectura: respuesta actual del HIS reemplaza snapshot; ausencia conserva dato obsoleto explícito. No implementa transporte, caché persistente, reintentos ni sincronización clínica. Las interfaces de semana 2 evitan depender de APIs supuestas del SO.

## Defectos registrados

| ID | Severidad / estado | Reproducción | Esperado | Observado / impacto |
|---|---|---|---|---|
| NP-D01 | Alta / abierto | --defectos, caso D01; gateway lanza RuntimeException | Adaptador traduzca falla a HIS_UNAVAILABLE o error tipado manejado | Excepción llega al llamador; la UI podría interrumpirse sin mensaje recuperable |
| NP-D02 | Media / abierto | --defectos, caso D02; notificador lanza RuntimeException | Traducir revocación/falla a NOTIFICATION_UNAVAILABLE | Excepción llega al llamador aunque la consulta fue válida |

Origen: RunOrderStatusPrototype de semana 2. En esa versión los adaptadores en memoria retornan estados; por ello la regresión original no detecta estas excepciones. La semana 15 agrega dobles que las reproducen. Recomendación: definir contrato de errores de adaptador, traducir excepciones conocidas en el límite y probar recuperación sin notificación de éxito falsa. No capturar indiscriminadamente errores de programación como si fueran fallas de red. La corrección del flujo original queda pendiente, identificada sin ocultarla como PASS funcional.

## Capturas y salidas

- [Salida suite](evidencia/salida-suite.txt) · [Captura suite](evidencia/captura-suite.png).
- [Salida defectos](evidencia/salida-defectos.txt) · [Captura defectos](evidencia/captura-defectos.png).
- [Regresión](evidencia/salida-regresion.txt).

Las capturas son una representación en navegador de las salidas reales guardadas, no pantallas de una app NativePHP. El archivo reporte.html permite revisar su contenido sin instalar herramientas.

## Riesgos y conclusión de viabilidad

Viabilidad demostrada: contratos segregados, flujo de consulta con dobles y políticas exploratorias ejecutables. Viabilidad nativa **no demostrada**: faltan seleccionar distribución/versión, empaquetar en cada destino, permisos reales, notificaciones reales, sesión autenticada, red limitada y almacenamiento seguro. Los dos defectos abiertos deben resolverse antes de integrar adaptadores reales. No se declara listo para producción ni se evalúan módulos clínicos ajenos.