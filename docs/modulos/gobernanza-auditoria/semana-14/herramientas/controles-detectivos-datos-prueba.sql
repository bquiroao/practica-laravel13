-- Datos de PRUEBA para controles-detectivos.sql: cada control debe devolver exactamente la fila que provoca su caso.
-- Se cargan en una base temporal con database/schema.sql (nunca en la base real).
INSERT INTO audit_logs (emisor, event_id, hospital_id, usuario_id, modulo, accion, ocurrido_en, resultado, recurso, secuencia, sello) VALUES
 -- Oriente: secuencias 1, 2 y 4 → C-D2 debe informar el hueco después de la 2
 ('svc-pacientes', 'o-1', 'hosp-oriente', 'u-ana', 'pacientes', 'PACIENTE_CONSULTADO', now() - interval '3 hours', 'exito', 'paciente:P-000001', 1, repeat('a', 64)),
 ('svc-pacientes', 'o-2', 'hosp-oriente', 'u-ana', 'pacientes', 'PACIENTE_CONSULTADO', now() - interval '2 hours', 'exito', 'P-000001', 2, repeat('b', 64)),
 ('svc-pacientes', 'o-4', 'hosp-oriente', 'u-luis', 'pacientes', 'PACIENTE_CONSULTADO', now() - interval '1 hours', 'exito', 'paciente:P-000002', 4, repeat('c', 64)),
 -- Norte: 3 lecturas y 1 movimiento de emisor hoy → C-D5 debe marcar a Norte
 ('svc-pacientes', 'n-1', 'hosp-norte', 'u-rosa', 'pacientes', 'PACIENTE_CONSULTADO', now() - interval '50 minutes', 'exito', 'paciente:P-000900', 1, repeat('d', 64)),
 ('auditoria', 's-1', 'hosp-norte', 'auditor-norte', 'auditoria', 'AUDITORIA_CONSULTADA', now() - interval '40 minutes', 'exito', NULL, 2, repeat('e', 64)),
 ('auditoria', 's-2', 'hosp-norte', 'auditor-norte', 'auditoria', 'AUDITORIA_CONSULTADA', now() - interval '30 minutes', 'exito', NULL, 3, repeat('f', 64)),
 ('auditoria', 's-3', 'hosp-norte', 'auditor-norte', 'auditoria', 'AUDITORIA_CONSULTADA', now() - interval '20 minutes', 'exito', NULL, 4, repeat('0', 64));
-- svc-rbac: 1 corrección sobre 2 movimientos (50 % > 1 %) → C-D4 debe marcar a svc-rbac
INSERT INTO audit_logs (emisor, event_id, hospital_id, usuario_id, modulo, accion, ocurrido_en, resultado, recurso, secuencia, sello)
 VALUES ('svc-rbac', 'r-1', NULL, 'u-admin', 'rbac', 'ROL_ASIGNADO', now() - interval '10 minutes', 'exito', 'usuario:U-0042', 1, repeat('1', 64));
INSERT INTO audit_logs (emisor, event_id, hospital_id, usuario_id, modulo, accion, ocurrido_en, resultado, recurso, secuencia, sello, corrige_a, motivo_correccion)
 SELECT 'svc-rbac', 'r-2', NULL, 'u-admin', 'rbac', 'ROL_ASIGNADO', now() - interval '5 minutes', 'exito', 'usuario:U-0042', 2, repeat('2', 64), id,
        'El rol se asignó al usuario equivocado' FROM audit_logs WHERE event_id = 'r-1';
