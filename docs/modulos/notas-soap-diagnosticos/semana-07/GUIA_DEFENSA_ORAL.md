# Guía de defensa oral — Semana 7

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

## 1. Explicación en 3 minutos

1. **Qué diseñé (45 s).** Abrir el diagrama de componentes. «A la izquierda está el frontend en Vue con Pinia, que es lo que ya usa el repositorio del grupo; a la derecha, el backend PHP que llevo desde la semana 3. Cada lado tiene sus capas. Se hablan solo por el contrato OpenAPI. El frontend no tiene dominio: no decide nada clínico, solo muestra lo que responde el servidor.»
2. **Cómo encontré el punto (45 s).** Mostrar `acoplamiento-1-antes.txt`. «No lo elegí a ojo. Hice un script que cuenta, por clase, cuántas clases usa y cuántas de las 6 preocupaciones del módulo mezcla. `CorregirNota` y `RegistrarNotaConDiagnostico` mezclaban las 6, y junto con `FirmarNota` repetían la misma transacción, el mismo formato de evento y las mismas reglas de acceso.»
3. **Qué cambié (1 min).** Mostrar el diagrama «después». «Le di un dueño a cada cosa: las reglas R-01 a R-03 están en `ReglasDeEscritura`; el nombre y los campos de los eventos, en `EntradaBitacora`; la transacción con su evento, en `RegistradorClinico`. Qué se guarda en cada caso lo dice un `CambioClinico`. Los casos de uso quedaron solo orquestando.»
4. **Cómo sé que no rompí nada (30 s).** «Antes de mover nada escribí 4 pruebas de caracterización y las corrí sobre el código viejo. Pasaron. Después del refactor siguen pasando. No hay rutas nuevas y el contrato sigue en 1.1.0.»

## 2. Demostración (en la carpeta `micro-his-notas-soap`)

```powershell
php tests/ejecutar.php            # 118 pruebas: 117 pasan, 1 omitida sin PostgreSQL
php bin/acoplamiento.php          # comparar con semana-07/evidencia/acoplamiento-1-antes.txt
```

Para mostrar que las REF de verdad protegen el comportamiento: en `src/Dominio/Eventos/EntradaBitacora.php`, cambiar `'autor_id'` por `'autor'` en `notaFirmada` y volver a correr las pruebas. **REF-01 falla.** Deshacer el cambio.

## 3. Preguntas probables

**¿Por qué esa clase y no el `Kernel`, que tiene el mismo Ce?**
El `Kernel` usa muchas clases, pero solo mezcla 3 preocupaciones y todas son de su trabajo: convertir excepciones en códigos HTTP. Es acoplamiento ancho pero cohesivo. Los casos de uso, en cambio, decidían acceso, revisaban el expediente, abrían la transacción y armaban el evento.

**Si el Ce total subió de 225 a 244, ¿no empeoró?**
El Ce total cuenta flechas, no si apuntan bien. Agregué 6 clases pequeñas y cada una importa sus tipos. Lo que importa es cuántos lugares hay que tocar para un cambio: antes, cambiar el formato de la bitácora eran 3 archivos; ahora es 1. Y no metí las clases nuevas en el script para que el número me favoreciera.

**¿Por qué siguen 4 clases con 4 o más preocupaciones?**
Salió `FirmarNota` y entró `RegistradorClinico`. Ese debe mezclar nota, diagnóstico, bitácora y persistencia, porque su único trabajo es guardar todo junto. Registrar y Corregir conservan una cosa propia cada uno: la idempotencia y la carrera del `UNIQUE`. Moverlas a un lugar común escondería diferencias reales.

**¿Qué es una prueba de caracterización?**
Una prueba que no dice cómo *debería* funcionar el sistema, sino cómo funciona hoy. Se escribe antes de refactorizar para darse cuenta si algo cambia sin querer. Es la técnica de Feathers (2004). Las mías fijan el formato de los eventos (REF-01), el orden de las verificaciones (REF-02) y qué pasa cuando algo falla (REF-03 y REF-04).

**¿Por qué importa el orden de las verificaciones?**
Porque el cliente recibe un código distinto. Si alguien sin permiso intenta escribir en un expediente cerrado, debe recibir 403, no 409: si recibiera 409, sabría que el expediente existe y está cerrado, aunque no tenga permiso de verlo.

**Tu prueba ARQ-12 decía que los casos de uso solo reciben interfaces. ¿No la rompiste?**
La ajusté a propósito y está explicado. Los casos de uso ahora reciben dos servicios del propio módulo, que no son adaptadores. Para que la regla siga valiendo, agregué ARQ-15: esos servicios solo reciben interfaces. Así nunca se llega a un adaptador concreto. Crear una interfaz para cada servicio habría sido una abstracción con una sola implementación y sin ningún doble de prueba.

**¿Qué principio SOLID se ve aquí?**
SRP: cada razón de cambio tiene su clase. OCP: un cambio clínico nuevo, como anular una nota, se agrega como otro `CambioClinico` sin tocar `RegistradorClinico`. DIP: los servicios dependen de puertos.

**¿Por qué `ReglasDeEscritura` está en el Dominio y `RegistradorClinico` en Aplicación?**
Las reglas R-01 a R-03 son reglas del negocio clínico de la semana 1 y solo usan puertos del Dominio. La transacción es una decisión de cómo se ejecuta el caso de uso, y `Transaccion` es un puerto de Aplicación.

**¿Por qué el frontend no valida el CIE-10?**
Puede ayudar al médico mostrando el formato, pero la regla final es del servidor. Si la validara el frontend, cualquiera que llame a la API sin la pantalla se la saltaría.

**¿Por qué la corrección no se reintenta sola en el frontend?**
Porque no lleva `Idempotency-Key`. Si la primera llegó y se perdió la respuesta, el reintento recibiría 409 `nota_ya_corregida` y confundiría al médico. Es mejor volver a consultar la nota. Lo mismo pasa con la firma, que ya era así desde la semana 5.

## 4. Si piden un cambio en vivo

| Pedido | Dónde |
|---|---|
| «Agregue un campo al evento de la firma» | Solo `EntradaBitacora::notaFirmada` y la expectativa de REF-01. Antes eran 1 de 3 casos de uso, sin prueba que lo cuidara. |
| «Que el jefe de servicio también pueda escribir» | Solo `ReglasDeEscritura::exigirPuedeEscribir` (o el `VerificadorAcceso`). Antes eran dos copias. |
| «Anular una nota en borrador» | Un `CambioClinico` nuevo (`AnulacionDeNota`), su evento en `EntradaBitacora`, un caso de uso, la ruta, el permiso y el contrato. `RegistradorClinico` no cambia. |
| «Que `ConsultarNota` use también `ReglasDeEscritura`» | Cambiar la verificación de hospital por `exigirMismoHospital`. Está anotado como siguiente paso en §10 del documento. |
