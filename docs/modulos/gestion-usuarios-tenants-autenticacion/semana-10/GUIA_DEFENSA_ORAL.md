# Guía de defensa oral — Semana 10

## Resumen en 60 segundos

«Adapté el flujo de alta, ingreso y revocación de sesión a móvil de 320 a 430 px. El tenant siempre se ve en la cabecera, cada pantalla pide una decisión principal y las tablas pasan a tarjetas. Son cuatro pantallas: ingreso, usuarios, alta en pasos y sesiones con revocación. Hay tres rangos de breakpoint: 320–359, 360–429 y el máximo de 430. Presento dos escenarios: un alta con red lenta, donde el reintento no duplica la membresía, y una revocación sin conexión, que queda como pendiente y nunca se muestra como revocada hasta que el servidor confirma.»

## Demostración

1. Abrir `diagramas/semana-10-wireframes-moviles.svg` y recorrer P1 a P4.
2. Explicar la jerarquía de datos (sección 2).
3. Contar el escenario A y el escenario B.

## Preguntas probables

**¿Por qué el error de ingreso es genérico?** Para no revelar si el correo existe (RF-06, RNF-03).

**¿Por qué el alta no pide contraseña?** En Semana 2 el flujo de alta no recibe secretos; el usuario la define al activar la cuenta.

**¿Cómo evitas una membresía duplicada si el usuario reintenta?** Con una clave de operación; es una decisión de diseño que requiere un contrato de API aún inexistente.

**¿Qué pasa si falla la red al revocar?** El estado es «pendiente de confirmar» y no se presenta como revocada; el reintento es seguro.

**¿Probaste con usuarios?** No. Son wireframes estáticos; la sección 8 es una lista de verificación para la implementación.

**¿Qué pasa en 600 px o más?** Queda fuera de este alcance.

## Evidencia

- [Documento](semana-10-diseno-movil-usuarios-tenants.md)
- [Wireframes](diagramas/semana-10-wireframes-moviles.svg)
