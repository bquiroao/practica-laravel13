# Guía de defensa oral - Semana 5

## Resumen en 60 segundos

La Semana 3 separó el micro-HIS en casos de uso, dominio y adaptadores. Esta entrega define cómo un cliente HIS remoto usa ese núcleo mediante una API REST versionada para invitar a una persona dentro de un tenant, permitirle establecer sus credenciales, autenticarse y revocar su propia sesión. Mantengo el límite de módulo, no despliego un microservicio: todavía no hay evidencia de carga, ownership operativo ni necesidad de independencia de despliegue.

## Demostración sugerida

1. Abrir `api/openapi.yaml` y explicar autorización tenant-bound para la invitación.
2. Mostrar que el administrador nunca envía una contraseña; la persona invitada establece su credencial con token de un solo uso.
3. Seguir `POST /auth/sessions`: la sesión se persiste, se devuelve el token una única vez y se guarda únicamente su hash.
4. Mostrar `GET /auth/me` y la comprobación de tenant y revocación en cada solicitud protegida.
5. Cerrar con `DELETE /auth/sessions/current`, la decisión modular y el plan de issues.

## Preguntas probables

1. **¿Por qué no separar ya un microservicio?**
   La API define un contrato, pero las invariantes necesitan transacciones entre membresía, credencial y sesión. Separarlas ahora agregaría consistencia eventual y fallos de red sin necesidad medida.

2. **¿Cómo se evita usar una cuenta de otro hospital?**
   El tenant se resuelve en servidor. Login necesita ese contexto; los tokens se ligan a él; el middleware verifica tenant y membresía activa en cada ruta.

3. **¿Qué pasa si el almacén de revocación falla?**
   La API falla cerrada con error temporal; no acepta un token sin comprobar la sesión.

4. **¿Por qué no se pasa la contraseña al crear el usuario?**
   El administrador autoriza la membresía; la persona invitada establece su secreto usando un token de un solo uso. Así se evita que personal administrativo conozca contraseñas.

5. **¿El correo ya está integrado?**
   No. La API solo define estado encolado; un proveedor y un outbox real deben implementarse y probarse en una tarea posterior.

6. **¿El OpenAPI comprueba que la implementación funciona?**
   No. Es el contrato de diseño para implementación y pruebas futuras; esta entrega no afirma que exista servidor HTTP ejecutable.
