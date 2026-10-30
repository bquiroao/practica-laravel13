# Corrección verificada — revisión integral

29-10-2026: búsqueda inválida mostraba aviso vacío y conservaba tarjeta previa seleccionable. Se añade mensaje accionable, se oculta resultado previo y se restablece al reiniciar demo. La referencia debe coincidir exactamente con las dos referencias de prueba conocidas.

Regresión nueva en verificacion/run.cjs: 1366/320/375/430 px, mensaje no vacío, tarjeta anterior oculta, camino feliz, código duplicado y ausencia de errores JS/desbordamiento. Datos ficticios, sin API real.

Ejecutar desde raíz con PLAYWRIGHT_MODULE apuntando a Playwright instalado y Edge disponible:

node docs/modulos/recepcion-muestras/semana-11/verificacion/run.cjs
