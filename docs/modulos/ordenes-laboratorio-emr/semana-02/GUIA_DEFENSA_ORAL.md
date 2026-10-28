# Guía de defensa oral — Semana 2

**Estudiante:** Lopez Perez, Mercedes Azucena · **Módulo:** Órdenes de laboratorio desde EMR · **Tema:** SRP aplicado a emisión y seguimiento.

## Resumen en 60 segundos

> «Partí del flujo de Semana 1: un profesional autorizado emite una orden desde el paciente y episodio del expediente, selecciona pruebas vigentes y después consulta el estado confirmado. Apliqué SRP separando la emisión, la consulta y la aplicación de una actualización recibida del laboratorio. Cada caso de uso coordina sus reglas mediante contratos de autorización, contexto EMR, catálogo, persistencia y ciclo de vida. El ejemplo PHP prueba errores y resultados sin usar datos clínicos. Los estados del test son sintéticos: el ciclo de vida real todavía debe acordarse con los módulos de laboratorio.»

## Recorrido sugerido

1. Explicar el alcance y las reglas R-01–R-06 de Semana 1.
2. Mostrar el [diseño antes de SRP](diagramas/semana-02-clases-antes.puml): un servicio cambia por autorización, catálogo, persistencia y estados.
3. Mostrar el [diseño después de SRP](diagramas/semana-02-clases-despues.puml): emisión, consulta y actualización son casos de uso distintos.
4. Ejecutar el comando indicado en el [README del ejemplo](ejemplo-srp/README.md) y relacionar CA-01–CA-15 con su salida.
5. Explicar el límite de los estados sintéticos y por qué no se declaran como contrato productivo.

## Preguntas probables

**¿Por qué separar emisión y consulta?**
La emisión escribe una orden completa y valida paciente, episodio y catálogo; la consulta es de lectura, necesita autorización y debe distinguir vacío de falla. Cambian por motivos diferentes y tienen resultados distintos.

**¿Por qué la actualización de estado es otro caso de uso?**
La recibe un sistema relacionado, valida el origen y depende de un contrato de ciclo de vida. No debe mezclarse con el acto clínico que emite la solicitud ni con la lectura del seguimiento.

**¿Qué estados tiene la orden?**
No se definen aquí. `TEST_INITIAL`, `TEST_NEXT` y `TEST_FINAL` solo ejercitan la política del fixture. Los estados reales requieren acuerdo con recepción, procesamiento y resultados.

**¿Cómo se evitan órdenes parciales?**
El contrato `createAtomically` requiere que el repositorio confirme la orden completa. En producción deberá implementarse con una transacción; el prototipo solo verifica el comportamiento observable del caso de uso.

**¿El adaptador en memoria mezcla responsabilidades?**
Implementa varias interfaces como fixture para que la suite comparta estado. Es intencionalmente solo una comodidad de prueba; los adaptadores productivos se separarían.

**¿El seguimiento muestra datos en caché si falla la consulta?**
Este ejemplo devuelve explícitamente `UNAVAILABLE` y no reporta `FOUND` ni `EMPTY`. No simula una caché de interfaz.

**¿Ya existe esta funcionalidad en el HIS?**
No se afirma eso. La entrega demuestra diseño y comportamiento del prototipo; no implementa pantalla, API ni persistencia productiva.

## Evidencia

```bash
php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php
```

La salida más reciente se registra en [`evidencia/salida-verificar.txt`](evidencia/salida-verificar.txt). Antes de entregar, verificar que el conteo final de aserciones coincida con la ejecución.

## Revisión previa a la defensa

- [ ] Comprendo las responsabilidades de los tres casos de uso y sus contratos.
- [ ] Puedo explicar CA-07 (persistencia fallida) y CA-10 (consulta no disponible).
- [ ] No presento los estados de test como estados reales del HIS.
- [ ] Revisé la salida de prueba y el vínculo con Semana 1.
