# Defensa breve — Semana 12

## 1 · Qué se evalúa (40 segundos)
Consultar una orden ficticia y notificar su estado. HIS conserva fuente de verdad. No capturamos resultados ni ampliamos a módulos clínicos. La demo es web local; runtime NativePHP no demostrado.

## 2 · Componentes (50 segundos)
UI/Presenter presenta estado y acciones. Aplicación coordina consulta/notificación. Dominio define frescura y viabilidad. Adaptadores aíslan HIS, SO y evidencia. La separación reduce dependencia de plataforma; el JS demuestra interacción y el contrato PHP previo demuestra segregación.

## 3 · UX, accesibilidad y movilidad (50 segundos)
Una acción principal, etiqueta persistente, mensajes por texto, foco visible, salto de contenido. Escritorio lateral; móvil una columna. Se conserva hora del snapshot, sin notificarlo como actual. Falta validación con lector de pantalla y dispositivo real.

## 4 · Demostración (100 segundos)
DEMO-1 con permiso: actual/notificación simulada. Denegar permiso: consulta válida, no enviada. Caída HIS: obsoleto y no intentada. Otro ID: sin dato. Rol sin autorización: bloqueado. Abrir viabilidad pendiente.

## 5 · Cambio y límites (60 segundos)
CH-01 es un cambio de ejemplo: separar consulta y notificación. Su prueba confirma que denegar permiso no oculta el dato actual. Faltan runtime, permisos reales, autorización por hospital y transporte seguro. No se reclama firma nativa ni compatibilidad multiplataforma por usar una demo web.