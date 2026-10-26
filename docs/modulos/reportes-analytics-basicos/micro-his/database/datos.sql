-- Datos ficticios (los mismos de las semanas 1 y 2): octubre 2026 con 39 de 50 camas ocupadas = 78 %.
INSERT INTO ward (code, name) VALUES ('MED', 'Medicina'), ('CIR', 'Cirugía'), ('PED', 'Pediatría');

-- 52 camas: 50 habilitadas y 2 en mantenimiento (MED-23 y MED-24), que no cuentan.
INSERT INTO bed (ward_id, code, enabled)
SELECT w.id, w.code || '-' || lpad(n::text, 2, '0'), n <= c.habilitadas
FROM ward w
JOIN (VALUES ('MED', 22, 24), ('CIR', 18, 18), ('PED', 10, 10)) AS c (code, habilitadas, total) ON c.code = w.code
CROSS JOIN LATERAL generate_series(1, c.total) AS n;

INSERT INTO local_patient (code)
SELECT 'PAC-' || lpad((450 + n)::text, 5, '0') FROM generate_series(1, 41) AS n;   -- PAC-00451 a PAC-00491

-- 39 estancias en octubre: Medicina 18, Cirugía 14 y Pediatría 7.
WITH camas AS (
    SELECT b.id, row_number() OVER (ORDER BY b.code) AS n
    FROM bed b JOIN ward w ON w.id = b.ward_id
    WHERE split_part(b.code, '-', 2)::int <= CASE w.code WHEN 'MED' THEN 18 WHEN 'CIR' THEN 14 ELSE 7 END
), pacientes AS (
    SELECT id, row_number() OVER (ORDER BY code) AS n FROM local_patient
)
INSERT INTO bed_stay (bed_id, local_patient_id, started_at, ended_at)
SELECT c.id, p.id,
       timestamptz '2026-10-01 12:00+00' + (c.n % 20) * interval '1 day',
       CASE WHEN c.n % 3 = 0 THEN timestamptz '2026-10-01 12:00+00' + (c.n % 20 + 4) * interval '1 day' END
FROM camas c JOIN pacientes p ON p.n = c.n;

-- Casos que NO deben contar en octubre: una estancia de septiembre y una cama en mantenimiento.
INSERT INTO bed_stay (bed_id, local_patient_id, started_at, ended_at)
SELECT b.id, p.id, timestamptz '2026-09-05 12:00+00', timestamptz '2026-09-20 12:00+00'
FROM bed b, local_patient p WHERE b.code = 'MED-19' AND p.code = 'PAC-00490';
INSERT INTO bed_stay (bed_id, local_patient_id, started_at)
SELECT b.id, p.id, timestamptz '2026-10-03 12:00+00'
FROM bed b, local_patient p WHERE b.code = 'MED-23' AND p.code = 'PAC-00491';

-- Citas para el reporte de Citas (semanas siguientes).
INSERT INTO appointment (doctor_code, local_patient_id, scheduled_at, status)
SELECT d.doctor, p.id, timestamptz '2026-10-06 15:00+00' + d.dia * interval '1 day', d.estado
FROM (VALUES ('DOC-001', 0, 'attended', 'PAC-00451'), ('DOC-001', 1, 'no_show', 'PAC-00452'),
             ('DOC-002', 2, 'attended', 'PAC-00453'), ('DOC-002', 3, 'cancelled', 'PAC-00454'),
             ('DOC-001', 4, 'scheduled', 'PAC-00455')) AS d (doctor, dia, estado, paciente)
JOIN local_patient p ON p.code = d.paciente;
