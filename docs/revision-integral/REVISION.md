# Revisión integral de 90 entregas

Mercedes Azucena Lopez Perez · 29-10-2026 · seis módulos, semanas 1–15.

## Resultado

26 suites PHP y 12 recorridos/contratos/cambios PHP correctos; 19 scripts de navegador/OpenAPI correctos según su código de salida, más contrato YAML de usuarios válido. 137 archivos PHP sin errores de sintaxis, 135 SVG XML válidos, 28 archivos JS/CJS sin errores de sintaxis. 758 enlaces locales comprobados, ninguno roto. Las inspecciones de accesibilidad producen hallazgos incluso cuando su proceso termina en 0: no significan conformidad.

Se reprodujeron diez defectos históricos abiertos en las bases de semana 15; sus pruebas --defectos PASS significan que el fallo existe. No se modificaron esas bases durante esta revisión.

Corrección nueva: Recepción semana 11 mostraba aviso vacío y tarjeta seleccionable anterior tras búsqueda inválida; corregido en d041e10 con regresión a 1366/320/375/430 px.

## Pendientes importantes

- Usuarios: sesión con campos blancos y token emitido de otro tenant aceptados por la base de pruebas.
- Ingreso: actor no autorizado no verificado, pérdida de precisión al canonizar y excepción de persistencia sin resultado explícito.
- Recepción: orden no verificada externamente, ID solo blanco y motivo solo blanco aceptados por modelo de base.
- NativePHP: excepción gateway/notificador escapa del flujo de semana 2 (semana 3 tiene manejo distinto; no asumir propagación de arreglos entre ramas).
- Wireframes de ingreso/validación: mediciones de semana 9 reproducen contraste bajo y textos fuera del lienzo en fuentes de semana 8. Son hallazgos abiertos de diseño, no controles funcionales.

## Cobertura por entrega

| Módulo | Semana | Rama | Revisión |
|---|---:|---|---|
| Usuarios, tenants y autenticación | 1 | semana-1-gestion-usuarios-tenants-autenticacion-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 2 | semana-2-gestion-usuarios-tenants-srp-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 3 | semana-3-gestion-usuarios-tenants-arquitectura-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 4 | semana-4-gestion-usuarios-tenants-mvc-repositorio-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 5 | semana-5-gestion-usuarios-tenants-api-cliente-servidor-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 6 | semana-6-usuarios-tenants-evaluacion-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 7 | semana-7-gestion-usuarios-tenants-componentes-refactorizacion-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 8 | semana-8-usuarios-tenants-ux-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 9 | semana-9-usuarios-tenants-accesibilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 10 | semana-10-usuarios-tenants-movilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 11 | semana-11-usuarios-tenants-prototipo-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 12 | semana-12-usuarios-tenants-evaluacion-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Usuarios, tenants y autenticación | 13 | semana-13-usuarios-tenants-revision-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 14 | semana-14-usuarios-tenants-sqa-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Usuarios, tenants y autenticación | 15 | semana-15-usuarios-tenants-pruebas-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. Defectos abiertos reproducidos: ver informe integral. |
| Ingreso de resultados de laboratorio | 1 | semana-1-ingreso-resultados-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Ingreso de resultados de laboratorio | 2 | semana-2-ingreso-resultados-lsp-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 3 | semana-3-ingreso-resultados-laboratorio-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 4 | semana-4-ingreso-resultados-mvc-mercedes-lopez | 2 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 5 | semana-5-ingreso-resultados-api-microservicio-mercedes-lopez | 2 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 6 | semana-6-ingreso-resultados-evaluacion-parcial-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 7 | semana-7-ingreso-resultados-componentes-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 8 | semana-8-ingreso-resultados-ux-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Ingreso de resultados de laboratorio | 9 | semana-9-ingreso-resultados-accesibilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 10 | semana-10-ingreso-resultados-movilidad-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Ingreso de resultados de laboratorio | 11 | semana-11-ingreso-resultados-prototipo-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 12 | semana-12-ingreso-resultados-evaluacion-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Ingreso de resultados de laboratorio | 13 | semana-13-ingreso-resultados-revision-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Ingreso de resultados de laboratorio | 14 | semana-14-ingreso-resultados-sqa-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Ingreso de resultados de laboratorio | 15 | semana-15-ingreso-resultados-pruebas-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. Defectos abiertos reproducidos: ver informe integral. |
| Investigación y prototipo NativePHP | 1 | semana-1-investigacion-prototipo-nativephp-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Investigación y prototipo NativePHP | 2 | semana-2-investigacion-prototipo-nativephp-isp-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 3 | semana-3-nativephp-arquitectura-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 4 | semana-4-nativephp-mvc-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 5 | semana-5-nativephp-api-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 6 | semana-6-nativephp-evaluacion-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 7 | semana-7-nativephp-componentes-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Investigación y prototipo NativePHP | 8 | semana-8-nativephp-ux-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Investigación y prototipo NativePHP | 9 | semana-9-nativephp-accesibilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 10 | semana-10-nativephp-movilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 11 | semana-11-nativephp-prototipo-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 12 | semana-12-nativephp-evaluacion-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Investigación y prototipo NativePHP | 13 | semana-13-nativephp-revision-tecnica-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Investigación y prototipo NativePHP | 14 | semana-14-nativephp-sqa-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Investigación y prototipo NativePHP | 15 | semana-15-nativephp-pruebas-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. Defectos abiertos reproducidos: ver informe integral. |
| Órdenes de laboratorio desde EMR | 1 | semana-1-ordenes-laboratorio-emr-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Órdenes de laboratorio desde EMR | 2 | semana-2-ordenes-laboratorio-emr-srp-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 3 | semana-3-ordenes-emr-arquitectura-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 4 | semana-4-ordenes-emr-mvc-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 5 | semana-5-ordenes-emr-api-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 6 | semana-6-ordenes-emr-evaluacion-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 7 | semana-7-ordenes-emr-componentes-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Órdenes de laboratorio desde EMR | 8 | semana-8-ordenes-laboratorio-emr-ux-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Órdenes de laboratorio desde EMR | 9 | semana-9-ordenes-emr-accesibilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 10 | semana-10-ordenes-emr-movilidad-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Órdenes de laboratorio desde EMR | 11 | semana-11-ordenes-emr-prototipo-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 12 | semana-12-ordenes-emr-evaluacion-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Órdenes de laboratorio desde EMR | 13 | semana-13-ordenes-laboratorio-emr-revision-tecnica-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Órdenes de laboratorio desde EMR | 14 | semana-14-ordenes-laboratorio-emr-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Órdenes de laboratorio desde EMR | 15 | semana-15-ordenes-laboratorio-emr-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 1 | semana-1-recepcion-muestras-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 2 | semana-2-recepcion-muestras-lsp-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 3 | semana-3-recepcion-muestras-arquitectura-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 4 | semana-4-recepcion-muestras-mvc-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 5 | semana-5-recepcion-muestras-api-microservicio-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 6 | semana-6-recepcion-muestras-defensa-arquitectura-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 7 | semana-7-recepcion-muestras-componentes-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 8 | semana-8-recepcion-muestras-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 9 | semana-9-recepcion-muestras-accesibilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 10 | semana-10-recepcion-muestras-movilidad-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 11 | semana-11-recepcion-muestras-prototipo-navegable-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 12 | semana-12-recepcion-muestras-evaluacion-parcial-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Recepción de muestras y códigos identificadores | 13 | semana-13-recepcion-muestras-revision-tecnica-formal-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 14 | semana-14-recepcion-muestras-sqa-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Recepción de muestras y códigos identificadores | 15 | semana-15-recepcion-muestras-pruebas-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. Defectos abiertos reproducidos: ver informe integral. |
| Validación de resultados por bioquímico | 1 | semana-1-validacion-resultados-bioquimico-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Validación de resultados por bioquímico | 2 | semana-2-validacion-resultados-dip-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 3 | semana-3-validacion-resultados-arquitectura-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 4 | semana-4-validacion-resultados-mvc-repositorio-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 5 | semana-5-validacion-resultados-api-microservicio-mercedes-lopez | 2 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 6 | semana-6-validacion-resultados-evaluacion-parcial-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 7 | semana-7-validacion-resultados-componentes-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 8 | semana-8-validacion-resultados-bioquimico-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Validación de resultados por bioquímico | 9 | semana-9-validacion-resultados-accesibilidad-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 10 | semana-10-validacion-resultados-movilidad-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Validación de resultados por bioquímico | 11 | semana-11-validacion-resultados-prototipo-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 12 | semana-12-validacion-resultados-evaluacion-parcial-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |
| Validación de resultados por bioquímico | 13 | semana-13-validacion-resultados-revision-tecnica-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Validación de resultados por bioquímico | 14 | semana-14-validacion-resultados-bioquimico-mercedes-lopez | Entrega de diseño/documentación: estructura y enlaces revisados; no equivale a integración funcional. |
| Validación de resultados por bioquímico | 15 | semana-15-validacion-resultados-bioquimico-mercedes-lopez | 1 verificaciones ejecutables correctas. Consultar alcance y límites en la entrega. |

## Límites

Revisión local de cada rama, no un sistema clínico integrado. Demos simulan servidor/roles/estado/red, pruebas PHP usan bases temporales/dobles. No se prueba servidor productivo, dispositivos nativos, firma, correo ni autorización real de extremo a extremo. Semanas documentales se revisaron por estructura, enlaces y correspondencia de archivos; no se transformaron en aplicaciones inexistentes.

Los worktrees son instantáneas separadas: las semanas posteriores pueden no incluir correcciones de ramas anteriores. Un PR abierto no implica merge. Se conservó el archivo del usuario semana-week-report.json y cambios ajenos de otros módulos detectados en worktrees.

## Vista local

Desde portal ejecutar node server.cjs y abrir http://127.0.0.1:8765. El servidor solo lee archivos de entregas y escucha en loopback. Documentos Markdown se muestran como texto y las demos como páginas. No ejecuta PHP ni expone credenciales.
