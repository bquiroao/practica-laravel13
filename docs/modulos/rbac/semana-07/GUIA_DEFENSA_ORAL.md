# Guía de defensa oral — Semana 7

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

## 1. Resumen en 60 segundos

> «Diseñé los componentes de RBAC en backend y frontend, separados en UI, aplicación, dominio y acceso a datos, y comunicados solo por el contrato OpenAPI. El frontend no decide nada: le pregunta al backend y solo oculta o muestra.
>
> Para elegir qué refactorizar, medí el acoplamiento de las 91 clases con un script. El peor punto eran AsignarRol y RevocarRol: cada uno mezclaba cinco preocupaciones (roles, permisos, decisión, auditoría y persistencia) y repetían el mismo bloque de transacción y evento.
>
> Moví el qué cambia y qué evento produce al Dominio (CambioDeMembresia), y la transacción con su evidencia a un solo servicio (RegistradorDeCambios). Los casos de uso bajaron de 13 y 11 dependencias a 8, y de 5 preocupaciones a 3.
>
> Para demostrar que no cambié el comportamiento, escribí pruebas de caracterización y las corrí también sobre el código viejo. Las 70 pruebas dan el mismo resultado.»

## 2. Orden sugerido de la exposición

1. **Diagrama de componentes:** recorrer frontend → contrato → backend; insistir en que el frontend no autoriza.
2. **Contratos:** mostrar 2 o 3 filas (`AsignarRol`, `RegistradorDeCambios`, `usePermiso`).
3. **Medición:** la tabla del «antes» y por qué ese punto.
4. **Antes / después:** los dos diagramas y el fragmento de código.
5. **Comparación medida y justificación:** SRP, OCP y DIP.
6. **Prueba de no ampliación:** caracterización sobre el código viejo.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Elegir el punto midiendo | Evita opinar: las mismas métricas muestran el antes y el después. |
| `CambioDeMembresia` en el **Dominio** | Qué se cambia y qué evento produce son conocimiento del negocio, no técnico. |
| `RegistradorDeCambios` en **Aplicación** | La transacción y la traducción de fallas técnicas son orquestación, no reglas del hospital. |
| `Desenlace` como `enum` | Cada caso de uso traduce el mismo desenlace a **su** respuesta: `SIN_EFECTO` es 500 al asignar y 404 al revocar. |
| Políticas fail-closed por dentro | `PoliticaAutorizacion` ya lo hacía; ahora las tres se comportan igual ante una falla de lectura. |
| Mantener la autorización dentro del caso de uso | Defensa en profundidad (R-04). Quitarla habría cambiado el comportamiento, y la tarea dice «sin ampliar» (ni reducir). |
| Frontend solo diseñado | La tarea pide diseñar componentes sin ampliar el módulo. Los archivos compartidos (axios, router) se coordinan con su dueño. |

## 4. Preguntas probables y respuestas

**¿Qué es Ce y por qué importa?**
Es el acoplamiento eferente: de cuántas clases depende una clase. Cuanto mayor es, más motivos externos tiene para romperse o cambiar. `AsignarRol` dependía de 13; ahora de 8.

**¿Por qué no bajó la duplicación?**
Sí bajó la **lógica** duplicada: el bloque de transacción, evento y errores ya no existe dos veces. El detector es simple y cuenta líneas idénticas, y lo que queda son firmas de constructores, propiedades de los DTO y tres líneas de orquestación que dejé explícitas a propósito. Lo reporto tal cual en §10 del documento.

**¿Cómo sabes que no cambiaste el comportamiento?**
Por dos cosas:
- Las 67 pruebas previas siguen verdes.
- Agregué 3 pruebas de caracterización para lo que iba a mover (fallas en reglas y formato de eventos), y las corrí **sobre el código anterior**, extraído con `git archive HEAD`: también pasaban.

**¿Qué es una prueba de caracterización?**
Una prueba que registra lo que el sistema hace **hoy**, sea ideal o no, para detectar si un refactor lo cambia sin querer (Feathers, 2004).

**¿Por qué no usaste herencia (Template Method)?**
Cada caso de uso tiene comando y resultado distintos. Una clase base exigiría tipos genéricos que PHP no tiene y acoplaría por herencia. Con composición (servicio + objeto de cambio) cada pieza se prueba por separado.

**¿Qué ganas si mañana piden «suspender un rol»?**
Una clase `SuspensionDeRol implements CambioDeMembresia`, su evento y su caso de uso corto. El bloque de transacción no se copia: lo pone `RegistradorDeCambios`.

**¿Dónde está la frontera entre UI y backend?**
En el contrato OpenAPI. El frontend usa `rbacApi`, que solo conoce rutas y JSON; nunca clases PHP.

**¿El frontend puede saltarse la seguridad?**
No. `SiPermiso` y `permisoGuard` solo ocultan o redirigen para la experiencia de uso. Cada operación vuelve a pasar por `AutorizarOperacion` en el backend.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/micro-his-rbac
php bin/acoplamiento.php 6        # AsignarRol y RevocarRol: Ce 8, 3 preocupaciones
php tests/ejecutar.php            # 70: 68 pasan, 0 fallan (mostrar REF-01..03)
# Comparar en el editor:
#   ../semana-07/refactor/antes/AsignarRol.php   vs   src/Aplicacion/CasosDeUso/AsignarRol.php
```

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Agrega un campo a todos los eventos ROL_*» | Un solo lugar: `EventoAuditoria::rolAsignado()` / `rolRevocado()`. Actualizar REF-03. |
| «Agrega “suspender rol”» | Clase `SuspensionDeRol implements CambioDeMembresia` + caso de uso con el mismo esqueleto. `RegistradorDeCambios` no cambia. |
| «Que un fallo de la bitácora devuelva 503 en vez de 500» | En el `match` del caso de uso, separar `Desenlace::FALLA`. Si se quiere distinguir la causa, agregar un caso al `enum`. |
| «Muestra el acoplamiento de otra clase» | `php bin/acoplamiento.php 40` y buscarla. |
