# Guía de defensa oral — Semana 2: ISP y NativePHP

**Estudiante:** Lopez Perez, Mercedes Azucena · **Módulo:** Investigación y prototipo NativePHP · **Flujo:** consultar el estado de una orden ficticia y notificar el resultado.

## Resumen en 60 segundos

> «Apliqué ISP a un flujo nativo acotado: consultar el estado de una orden de laboratorio ficticia y notificar el resultado. Antes, una sola interfaz de dispositivo obligaba al flujo a conocer también exportación, cámara, escaneo y uso compartido; el adaptador de escritorio terminaba lanzando excepciones para operaciones que no soporta. Después, el flujo depende solo del gateway del HIS y del puerto de notificación; la exportación y el escaneo son contratos separados. El ejemplo en PHP pasa diez criterios con dobles en memoria. Eso comprueba el diseño, no una app NativePHP instalada: el runtime y la integración autenticada con Laravel quedan por validar en el prototipo siguiente.»

## Orden sugerido para exponer

1. Recordar el alcance de Semana 1: NativePHP es una extensión evaluada, Laravel sigue siendo fuente de verdad.
2. Presentar el flujo de demostración y aclarar que usa datos ficticios, sin pantalla ni base productiva.
3. Mostrar la interfaz amplia del diseño antes y los métodos que el adaptador debe rechazar.
4. Explicar ISP: cada cliente debe depender solo de las operaciones que usa.
5. Mostrar `NativeNotifier`, `PrototypeEvidenceExporter` y `BarcodeScanner` por separado.
6. Ejecutar `php docs/modulos/investigacion-prototipo-nativephp/semana-02/ejemplo-isp/verificar.php`.
7. Explicar por qué “10 pruebas aprobadas” no equivale a “NativePHP funciona en el dispositivo”.
8. Concluir la decisión: viable parcialmente como diseño; falta validar runtime, destino e integración con Laravel.

## Preguntas probables

**¿Qué se demuestra exactamente con el prototipo?**

La consulta y notificación del estado ficticio, las respuestas ante errores y la segregación de interfaces. Se ejecuta en PHP de consola con adaptadores en memoria.

**¿Esto ya es una app NativePHP?**

No. En esta entrega no se instala NativePHP ni se genera un paquete móvil/escritorio. El código define los contratos que podrán conectar adaptadores NativePHP cuando se escoja la plataforma y el paquete correspondiente.

**¿Por qué mantener una interfaz separada para escanear si el flujo actual no escanea?**

Para ilustrar que una capacidad futura no debe añadirse a la interfaz de notificaciones. Un flujo que escanee dependerá de `BarcodeScanner`; el flujo probado no.

**¿Qué pasa si falla Laravel o la notificación?**

Se obtienen resultados diferentes: `HIS_UNAVAILABLE` o `NOTIFICATION_UNAVAILABLE`. El primero no genera notificación de éxito; el segundo no se presenta como notificación enviada.

**¿Qué datos llegan a la notificación?**

Solo texto genérico y un estado enumerado de una orden de demostración. Nunca nombre de paciente, identificador clínico ni valor analítico.

**¿Qué falta para declarar viable NativePHP?**

Elegir el destino, instalar el paquete oficial apropiado, comprobar el permiso y envío de notificación en runtime y verificar una llamada autenticada al backend Laravel con datos ficticios.

## Recorrido del código

1. Abrir [el documento de diseño](semana-02-isp-nativephp.md) y leer CA-01 a CA-10.
2. Mostrar [el contrato antes de ISP](ejemplo-isp/antes/NativeDeviceCapabilities.php).
3. Compararlo con [las interfaces segregadas](ejemplo-isp/despues/NativeCapabilities.php).
4. Revisar [los adaptadores de prueba](ejemplo-isp/despues/InMemoryAdapters.php).
5. Ejecutar [verificar.php](ejemplo-isp/verificar.php) y localizar los casos CA-07 a CA-10.

## Límites y cambios de alcance

| Si se cambia… | Se deben revisar… |
|---|---|
| El flujo HIS evaluado | RF-01 a RF-05, gateway, criterios y alcance de Semana 1. |
| Una capacidad nativa (escaneo, cámara, exportación) | Añadir o revisar su contrato independiente y sus consumidores, no una interfaz universal. |
| El destino (escritorio o móvil) | Adaptadores, permisos, empaquetado y pruebas en el runtime elegido. |
| La respuesta del backend | Tipos de estado, CA-01 a CA-05, privacidad y manejo de errores. |
