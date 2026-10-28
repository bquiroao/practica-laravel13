# Presentación breve — Semana 12

## 1 · Alcance (40 s)
Alta por tenant, login y revocación. Identidad global no equivale a membresía hospitalaria. Demo ficticia, sin JWT real ni datos clínicos.

## 2 · Componentes (50 s)
Vistas/formularios; casos de uso separados; dominio de membresía/contexto/expiración; adaptadores para credenciales, token y revocación. No mezclar selector de demo con autorización confiable de servidor.

## 3 · UX y movilidad (50 s)
Resumen de tenant/rol antes de alta, errores genéricos de login, contexto de sesión visible y cierre solo confirmado. Desktop lateral y móvil columna. Foco, salto, etiquetas y estados por texto; lector de pantalla pendiente.

## 4 · Recorrido (100 s)
Alta H1, denegar H2. Login con error y luego éxito. Comprobar acceso H2 denegado. Fallar revocación sin fingir cierre; confirmar cierre y rechazar acceso posterior. Rol sin permiso de alta bloqueado.

## 5 · Cambio y riesgos (60 s)
CH-01 es ejemplo: revocación fallida no anuncia cierre. Persistencia real y JWT firmado siguen pendientes. El prototipo no remedia defectos del backend ni certifica aislamiento productivo.