# Semana 2 — Requerimientos, criterios de aceptación y aplicación de ISP

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Investigación y prototipo NativePHP para extensión móvil/escritorio |
| **Tema** | Proceso y modelo de diseño; principios SOLID |
| **Flujo acotado** | «Consultar el estado de una orden de laboratorio de demostración y notificar el resultado» |
| **Principio aplicado** | ISP — Principio de segregación de interfaces |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## Índice

1. [Objetivo y alcance](#1-objetivo-y-alcance)
2. [Requerimientos funcionales](#2-requerimientos-funcionales)
3. [Requerimientos no funcionales](#3-requerimientos-no-funcionales)
4. [Criterios de aceptación](#4-criterios-de-aceptación)
5. [ISP y capacidades de la extensión nativa](#5-isp-y-capacidades-de-la-extensión-nativa)
6. [Diseño antes de ISP](#6-diseño-antes-de-isp)
7. [Diseño después de ISP](#7-diseño-después-de-isp)
8. [Responsabilidades, dependencias y decisión de viabilidad](#8-responsabilidades-dependencias-y-decisión-de-viabilidad)
9. [Evidencia verificable](#9-evidencia-verificable)
10. [Trazabilidad con Semana 1](#10-trazabilidad-con-semana-1)
11. [Conclusión y trabajo pendiente](#11-conclusión-y-trabajo-pendiente)
12. [Referencias](#12-referencias)

## 1. Objetivo y alcance

En Semana 1 se modeló la evaluación de NativePHP como una extensión acotada del HIS: Laravel conserva reglas, permisos y datos; la extensión aporta una experiencia de ejecución nativa para un flujo específico. Esta semana aplica **ISP** al límite de integración entre el flujo de aplicación y las capacidades del dispositivo.

El prototipo escogido consulta el estado de una **orden de demostración** y notifica el resultado. Se eligió porque recorre de forma pequeña la idea de cliente nativo —solicitud al HIS, respuesta y una capacidad del dispositivo— sin intentar implementar todo el HIS ni procesar datos clínicos reales.

**Dentro del alcance:** validar un identificador de demostración, consultar un estado mediante un puerto, distinguir una consulta no disponible, notificar solo estados permitidos y mostrar cómo un flujo que notifica no debe depender de las capacidades de exportación, impresión o lectura de códigos.

**Fuera del alcance:** empaquetar una app nativa, instalar NativePHP, consumir un backend Laravel real, autenticar usuarios, usar datos de pacientes, definir reglas bioquímicas o afirmar compatibilidad móvil/escritorio. En este worktree `composer.json` no declara NativePHP; por ello, el prototipo ejecutable comprueba la arquitectura con adaptadores en memoria y **no** se presenta como ejecución nativa.

**Decisión de viabilidad en esta entrega: viable parcialmente como diseño y prototipo de contrato.** Las pruebas muestran que las capacidades pueden segregarse y sustituirse. La viabilidad del runtime todavía no está demostrada: se debe elegir el paquete/destino NativePHP y verificar su instalación, ciclo de permisos, notificaciones y llamada autenticada al Laravel del HIS con datos de prueba.

## 2. Requerimientos funcionales

| ID | Requerimiento | Origen |
|---|---|---|
| RF-01 | El operador debe poder solicitar el estado de una orden ficticia identificada por un código de demostración. | CU-02 Semana 1 |
| RF-02 | La consulta debe delegarse a un puerto que represente el servicio del HIS; el prototipo no debe mantener una segunda fuente de verdad. | RF-01, R-02 Semana 1 |
| RF-03 | La aplicación debe notificar un estado solo cuando el gateway de demostración devuelve un estado reconocido. | CU-02, E-03 Semana 1 |
| RF-04 | Si la consulta del HIS no está disponible, la aplicación debe mostrar ese resultado sin emitir una notificación de éxito. | R-04, E-03 Semana 1 |
| RF-05 | Si la capacidad de notificación no está disponible, el resultado de consulta debe distinguirse de un envío de notificación exitoso. | R-03, E-03 Semana 1 |
| RF-06 | La aplicación debe ofrecer exportación de evidencia mediante un contrato independiente, sin exigirlo al cliente que consulta y notifica. | CU-03 Semana 1; ISP |
| RF-07 | La notificación del ejemplo debe limitarse a un estado de demostración y no contener nombre, identificador de paciente ni resultado clínico. | Límite de seguridad de esta entrega |

## 3. Requerimientos no funcionales

| ID | Atributo | Requerimiento | Verificación |
|---|---|---|---|
| RNF-01 | Segregación de interfaces | Cada consumidor dependerá solo de las operaciones que usa; no habrá métodos obligatorios que respondan “no soportado” en capacidades ajenas. | CA-07 a CA-10 |
| RNF-02 | Portabilidad | Los contratos de aplicación no incluirán clases de plataforma, llamadas específicas del SO ni APIs supuestas de NativePHP. | Revisión de `despues/` |
| RNF-03 | Privacidad | La evidencia y las notificaciones de este prototipo usarán identificadores y estados ficticios; no se incorporarán datos clínicos reales. | CA-02 |
| RNF-04 | Respuestas explícitas | Entrada inválida, backend no disponible y capacidad nativa no disponible no deben representarse como éxito. | CA-03 a CA-05 |
| RNF-05 | Testabilidad | El flujo se ejecutará sin runtime nativo mediante gateway y adaptadores en memoria sustituibles. | `verificar.php` |
| RNF-06 | Alcance honesto | Las pruebas unitarias no se describirán como prueba de empaquetado o ejecución de NativePHP. | Informe de viabilidad y evidencia |

## 4. Criterios de aceptación

Formato *Dado / Cuando / Entonces*. Las comprobaciones automáticas se ejecutan en [`ejemplo-isp/verificar.php`](ejemplo-isp/verificar.php).

| ID | RF/RNF | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01, RF-03 | una orden ficticia con estado conocido | se ejecuta el flujo | devuelve notificación enviada y solicita una notificación |
| CA-02 | RF-07, RNF-03 | una orden con estado ficticio permitido | se compone el texto de notificación | contiene solo una descripción genérica y el estado permitido, no datos de paciente ni valores clínicos |
| CA-03 | RF-01, RF-04 | un identificador vacío | se solicita el flujo | devuelve entrada inválida y no notifica |
| CA-04 | RF-02, RF-04 | el gateway no encuentra o no puede consultar la orden de demostración | se solicita el flujo | devuelve HIS no disponible y no notifica éxito |
| CA-05 | RF-05, RNF-04 | existe un estado ficticio y la notificación no está disponible | se ejecuta el flujo | devuelve notificación no disponible, distinto de consulta fallida y de éxito notificado |
| CA-06 | RF-06, RNF-01 | se exporta evidencia del prototipo | se ejecuta el exportador | solo se requiere el contrato de exportación y el archivo queda disponible en el adaptador de prueba |
| CA-07 | RNF-01 | se inspecciona `NativeNotifier` | se enumeran sus operaciones | contiene solo `notify`, no exportación ni escaneo |
| CA-08 | RNF-01, RNF-02 | se construye el caso de uso de consulta/notificación | se inspeccionan sus dependencias | recibe `DemoOrderStatusGateway` y `NativeNotifier`, no una interfaz general del dispositivo |
| CA-09 | RF-06, RNF-01 | se construye el caso de uso de exportación | se inspecciona su constructor | recibe únicamente `PrototypeEvidenceExporter` |
| CA-10 | RNF-01 | se inspecciona la capacidad de escaneo | se enumeran sus operaciones | `BarcodeScanner` contiene solo `scan`, independiente de notificación y exportación |

## 5. ISP y capacidades de la extensión nativa

El principio de segregación de interfaces indica que ningún cliente debe depender de métodos que no usa. En una extensión de escritorio/móvil, las capacidades varían por destino y por caso de uso. Una consulta que solo notifica no necesita implementar exportación, escaneo, cámara o impresión; un exportador no necesita pedir permisos de notificación.

El problema no se resuelve creando una interfaz general y dejando que los métodos irrelevantes lancen “no soportado”. Eso mantiene el acoplamiento y obliga a cada adaptador a fingir capacidades. Se separan interfaces pequeñas según la operación:

- `DemoOrderStatusGateway`: consultar el backend del HIS (puerto de aplicación, no API de dispositivo).
- `NativeNotifier`: presentar una notificación nativa.
- `PrototypeEvidenceExporter`: exportar evidencia de la evaluación.
- `BarcodeScanner`: escanear cuando otro flujo realmente lo requiera.

Los consumidores reciben solo sus contratos por constructor. En una integración real, los adaptadores implementarán únicamente las interfaces que el destino soporta y el punto de composición escogerá el adaptador correspondiente. No se definen nombres ni llamadas concretas de NativePHP sin tener instalada y seleccionada una distribución del paquete.

## 6. Diseño antes de ISP

En [`NativeDeviceCapabilities.php`](ejemplo-isp/antes/NativeDeviceCapabilities.php), la interfaz agrupa seis capacidades distintas. `RunOrderStatusPrototype` solo necesita `notify`, pero su constructor requiere todo el dispositivo. El adaptador de escritorio implementa `share`, `scanBarcode`, `captureImage` e `print` con excepciones porque no forman parte del prototipo:

```text
RunOrderStatusPrototype
          │ depende de
          ▼
NativeDeviceCapabilities
 ├─ notify       ← usada
 ├─ share        ← ajena al flujo
 ├─ saveEvidence ← ajena al flujo
 ├─ scanBarcode  ← ajena al flujo
 ├─ captureImage ← ajena al flujo
 └─ print        ← ajena al flujo
```

Este contrato hace que agregar o cambiar una capacidad obligue a todos los dispositivos y consumidores a conocerla, aunque no la utilicen. Las excepciones “no soportado” señalan la violación de ISP; no son una estrategia recomendada.

## 7. Diseño después de ISP

El código de [`NativeCapabilities.php`](ejemplo-isp/despues/NativeCapabilities.php) separa los puertos por cliente. El flujo solo recibe el gateway del HIS y el notificador:

```text
RunOrderStatusPrototype
 ├── DemoOrderStatusGateway
 └── NativeNotifier

ExportPrototypeEvidence
 └── PrototypeEvidenceExporter

Flujo futuro de lectura de código
 └── BarcodeScanner
```

`InMemoryAdapters.php` implementa contratos para la verificación automatizada. Representa doubles de prueba, no adaptadores productivos. Las capacidades pueden crecer sin modificar consumidores no relacionados.

## 8. Responsabilidades, dependencias y decisión de viabilidad

| Componente | Responsabilidad | Lo que no hace |
|---|---|---|
| `RunOrderStatusPrototype` | Coordinar consulta de demostración y notificación del estado permitido. | No consulta una base directamente, no implementa API de sistema operativo ni decide reglas clínicas. |
| `DemoOrderStatusGateway` | Puerto para consultar el estado desde el HIS. | No notifica ni exporta archivos. |
| `NativeNotifier` | Puerto para pedir una notificación nativa. | No consulta órdenes, escanea códigos o exporta evidencia. |
| `ExportPrototypeEvidence` | Coordinar la exportación de la evidencia de evaluación. | No consulta órdenes ni solicita permisos de notificación. |
| `PrototypeEvidenceExporter` | Puerto de salida de evidencia. | No conoce el estado de la orden ni su origen. |
| `BarcodeScanner` | Expresar la capacidad opcional para un flujo que la necesite. | No forma parte del flujo probado en esta semana. |
| Adaptadores en memoria | Simular resultados y operaciones para pruebas repetibles. | No son NativePHP ni persistencia del HIS. |

La evaluación es **parcialmente favorable al diseño**: ISP permite que el flujo de notificación no arrastre capacidades que no usa. La evaluación **no concluye compatibilidad del producto NativePHP**. Para concluir viabilidad en la etapa de prototipo, se deberá seleccionar explícitamente el destino, instalar el paquete apropiado, comprobar el permiso/notificación nativa, conectar a Laravel autenticado sobre TLS y probar respuesta, desconexión y manejo seguro de credenciales con registros ficticios.

## 9. Evidencia verificable

Desde la raíz del repositorio:

```bash
php docs/modulos/investigacion-prototipo-nativephp/semana-02/ejemplo-isp/verificar.php
```

El script comprueba CA-01 a CA-10 con adaptadores en memoria y contratos pequeños. La salida obtenida se conserva en [`evidencia/salida-verificar.txt`](evidencia/salida-verificar.txt). Esta evidencia valida el diseño PHP; no demuestra ejecución, instalación ni empaquetado nativo.

## 10. Trazabilidad con Semana 1

| Regla Semana 1 | Aplicación en Semana 2 | Criterio |
|---|---|---|
| R-01 — flujo acotado y objetivo medible | Se limita a consultar el estado de una orden ficticia y notificarlo. | CA-01 a CA-05 |
| R-02 — Laravel como fuente de verdad | La aplicación consulta `DemoOrderStatusGateway`; no conserva una copia maestra local. | RF-02, CA-04 |
| R-03 — resultado de evaluación con evidencia | El adaptador de exportación es independiente del flujo de consulta. | RF-06, CA-06 |
| R-04 — errores separados de una decisión de negocio | Backend no disponible y notificación no disponible tienen resultados distintos. | CA-04, CA-05 |
| R-05 — evidencia acotada y segura | Solo se admiten estados de demostración y texto genérico. | RF-07, CA-02 |

## 11. Conclusión y trabajo pendiente

El ejemplo aplica ISP al límite nativo mediante contratos por capacidad: el flujo que notifica no depende de exportación o escaneo, y el exportador no necesita notificar. Se preserva el rol de Laravel como fuente de verdad y se excluyen datos reales del prototipo.

Esta es una demostración de diseño y no una aplicación NativePHP empaquetada. La viabilidad queda parcial hasta elegir el destino y validar el runtime, las capacidades soportadas, la integración autenticada con Laravel y los criterios de aceptación en un entorno nativo. Esos límites evitan atribuir a pruebas en memoria resultados que no se han ejecutado en un dispositivo.

## 12. Referencias

- Captura de Semana 2 «Investigación y prototipo NativePHP para extensión móvil/escritorio», compartida por la estudiante.
- [Semana 1 — Modelado UML de la evaluación y prototipo NativePHP](../semana-01/semana-01-uml-investigacion-prototipo-nativephp.md).
- Robert C. Martin, *Agile Software Development: Principles, Patterns, and Practices*, capítulo sobre Interface Segregation Principle.
- `composer.json` del proyecto: no declara dependencia NativePHP en la base utilizada por esta entrega.
