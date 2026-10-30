-- Datos de demostración (ficticios). Los casos imitan la regresión manual que el HIS ya hace sobre el esquema federado
-- (openspec/changes/postgresql-federated-schema-rebuild/apply-progress.md): una suite por bloque C1–C4 (central) y H1–H4 (hospital).
SET search_path TO qa;

INSERT INTO casos_prueba (tenant, caso_id, version, autor, titulo, precondiciones, pasos, prioridad, estado, aprobador) VALUES
('hosp-norte', 'TC-101', 1, 'ana-qa', 'C1 identidad de red: correo nulo permitido y único sin mayúsculas', 'Base central de prueba migrada',
 '[{"accion":"Insertar dos usuarios con correo NULL","esperado":"Ambos se insertan"},{"accion":"Insertar Ana@x.gt y ana@x.gt","esperado":"El segundo se rechaza (23505)"}]', 'ALTA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-102', 1, 'ana-qa', 'C2 MPI: un merge exige candidatos coherentes', 'Base central de prueba migrada',
 '[{"accion":"Registrar un merge con candidato de otro paciente","esperado":"Se rechaza por la restricción de coherencia"}]', 'ALTA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-103', 1, 'ana-qa', 'C3 consentimiento: break-glass exige revisión encadenada', 'Base central de prueba migrada',
 '[{"accion":"Agregar una revisión a un acceso normal","esperado":"Se rechaza"},{"accion":"Actualizar una revisión","esperado":"Se rechaza: solo agregar"}]', 'ALTA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-104', 1, 'ana-qa', 'C4 sincronización: fecha terminal posterior a la recepción', 'Base central de prueba migrada',
 '[{"accion":"Marcar procesado un mensaje antes de available_at","esperado":"Se rechaza"}]', 'MEDIA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-105', 1, 'ana-qa', 'H1 identidad hospitalaria: licencia única por profesional', 'Base hospitalaria de prueba migrada',
 '[{"accion":"Registrar dos licencias activas iguales","esperado":"La segunda se rechaza"}]', 'MEDIA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-106', 1, 'ana-qa', 'H3 agenda y camas: sin dos estancias activas en la misma cama', 'Base hospitalaria de prueba migrada',
 '[{"accion":"Asignar una cama ocupada en un rango que se solapa","esperado":"Se rechaza por exclusión"}]', 'ALTA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-107', 1, 'ana-qa', 'H4 expediente: una nota firmada no se edita', 'Base hospitalaria con una nota firmada',
 '[{"accion":"Actualizar el texto de la nota firmada","esperado":"Se rechaza: se agrega una corrección"}]', 'ALTA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-108', 1, 'ana-qa', 'H4 alergias: una alergia activa equivalente no se duplica', 'Paciente con alergia activa a penicilina',
 '[{"accion":"Registrar otra alergia activa a penicilina","esperado":"Se rechaza por equivalencia"}]', 'ALTA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-109', 1, 'ana-qa', 'H4 signos vitales: rango fuera de lo posible', 'Episodio abierto',
 '[{"accion":"Registrar temperatura de 60 °C","esperado":"Se rechaza por rango"}]', 'BAJA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-110', 1, 'ana-qa', 'Reversión H4: rollback de un paso deja H1–H3', 'Base hospitalaria migrada completa',
 '[{"accion":"migrate:rollback --step=1","esperado":"Solo desaparecen las tablas de H4"}]', 'MEDIA', 'APROBADO', 'lider-qa'),
('hosp-norte', 'TC-111', 1, 'ana-qa', 'H2 atención: alcance JSONB válido (borrador)', 'Base hospitalaria de prueba migrada',
 '[{"accion":"Asignar atención con scope vacío","esperado":"Se rechaza"}]', 'MEDIA', 'BORRADOR', NULL),
('hosp-sur', 'TC-101', 1, 'qa-sur', 'C1 identidad de red (hospital sur)', 'Base central de prueba migrada',
 '[{"accion":"Insertar dos usuarios con correo NULL","esperado":"Ambos se insertan"}]', 'ALTA', 'APROBADO', 'lider-sur');

INSERT INTO caso_requisitos (tenant, caso_id, version, requisito) VALUES
('hosp-norte', 'TC-101', 1, 'RF-01'), ('hosp-norte', 'TC-102', 1, 'RF-02'), ('hosp-norte', 'TC-103', 1, 'RF-03'),
('hosp-norte', 'TC-104', 1, 'RF-04'), ('hosp-norte', 'TC-105', 1, 'RF-05'), ('hosp-norte', 'TC-106', 1, 'RF-06'),
('hosp-norte', 'TC-107', 1, 'RF-07'), ('hosp-norte', 'TC-108', 1, 'RF-07'), ('hosp-norte', 'TC-108', 1, 'RF-08'),
('hosp-norte', 'TC-109', 1, 'RF-07'), ('hosp-norte', 'TC-110', 1, 'RF-09'), ('hosp-norte', 'TC-111', 1, 'RF-06'),
('hosp-sur', 'TC-101', 1, 'RF-01');

-- Qué casos corren cuando cambia cada funcionalidad. Los críticos no pueden faltar en el alcance (R-03).
INSERT INTO matriz_regresion (tenant, funcionalidad, caso_id, critico) VALUES
('hosp-norte', 'identidad-central', 'TC-101', true),
('hosp-norte', 'mpi', 'TC-102', true),
('hosp-norte', 'consentimiento', 'TC-103', true),
('hosp-norte', 'sincronizacion', 'TC-104', false),
('hosp-norte', 'identidad-hospital', 'TC-105', false),
('hosp-norte', 'agenda-camas', 'TC-106', true),
('hosp-norte', 'expediente', 'TC-107', true),
('hosp-norte', 'expediente', 'TC-108', true),
('hosp-norte', 'expediente', 'TC-109', false),
('hosp-norte', 'expediente', 'TC-110', false),
('hosp-sur', 'identidad-central', 'TC-101', true);
