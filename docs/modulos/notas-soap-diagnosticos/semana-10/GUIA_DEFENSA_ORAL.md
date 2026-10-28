# Guía de defensa oral — Semana 10

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-10-movil-notas-soap.md](semana-10-movil-notas-soap.md)

## 1. Explicación en 3 minutos

1. **Qué hice (30 s).** «Adapté al teléfono el diseño de la semana 8 y apliqué el backlog de la semana 9. Son 5 pantallas en un prototipo HTML que se abre en el navegador, de 320 a 1024 px.»
2. **Qué nunca se recorta (45 s).** Mostrar M2 a 320 px. «Aunque falte espacio, siempre se ven el hospital y el paciente. Al firmar, la hoja dice qué versión y de qué paciente. Eso corrige los dos hallazgos más graves de la semana 9.»
3. **Breakpoints medidos (45 s).** «Las reglas medibles las comprueba un script en 33 vistas: sin desplazamiento horizontal, botones de 44 px, letra de 16 px en los campos, hospital y paciente visibles. Resultado: 0 fallas.»
4. **Red lenta (1 min).** Mostrar el escenario 2 y la salida de `reintento.php`. «No supuse que reintentar era seguro: lo medí. Registrar sin `Idempotency-Key` duplica la nota; con la clave, no. Por eso la app siempre manda la clave, y la firma y la corrección nunca se repiten solas.»

## 2. Demostración

```powershell
cd docs\modulos\notas-soap-diagnosticos\semana-10
php herramientas\reintento.php
```

Debe mostrar 4 casos y «No seguros: 1». Luego abrir `prototipo\m2-nueva-nota.html?estado=incierto` con F12 en modo de dispositivo.

## 3. Preguntas probables

**¿Por qué no guardar la nota en el teléfono para enviarla cuando vuelva la red?**
Porque es texto clínico en un equipo que se pierde o se comparte (D-5). Además, enviada más tarde, el episodio pudo cerrarse.

**¿Por qué el reintento de la firma da 409 y no es un error?**
Porque el 409 `nota_firmada` dice que la primera firma sí llegó. Si la app sabe que es su propio reintento, muestra la nota firmada (R-M6).

**¿Por qué 44 px si WCAG pide 24 px?**
24 px es el mínimo AA. El médico usa el teléfono de pie, con una mano y a veces con guantes; 44 px es lo que recomiendan las guías de iOS y Android.

**¿Por qué 16 px en los campos?**
Con menos de 16 px, iOS amplía la página al enfocar el campo y el médico pierde de vista el resto del formulario.

**¿Qué es B-6?**
La cabecera `Idempotency-Key` hoy es opcional. Un cliente que la olvide duplica notas; propongo exigirla en la próxima versión de la API.
