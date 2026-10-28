-- Esquema mínimo del Micro-HIS (SQLite). Los nombres siguen la base del grupo (tomas_signos_vitales, mediciones_signo, ...).
CREATE TABLE pacientes (id TEXT PRIMARY KEY, fecha_nacimiento TEXT NOT NULL);
CREATE TABLE episodios (id TEXT PRIMARY KEY, paciente_id TEXT NOT NULL REFERENCES pacientes(id), hospital_id TEXT NOT NULL, estado TEXT NOT NULL CHECK (estado IN ('activo', 'cerrado')));
CREATE TABLE rangos_signos_vitales (tipo TEXT NOT NULL, edad_min INTEGER NOT NULL, edad_max INTEGER NOT NULL,
                                    normal_min REAL NOT NULL, normal_max REAL NOT NULL, critico_bajo REAL NOT NULL, critico_alto REAL NOT NULL,
                                    PRIMARY KEY (tipo, edad_min));
CREATE TABLE tomas_signos_vitales (id TEXT PRIMARY KEY, paciente_id TEXT NOT NULL, episodio_id TEXT NOT NULL REFERENCES episodios(id),
                                   autor_id TEXT NOT NULL, tomada_en TEXT NOT NULL);
CREATE TABLE mediciones_signo (toma_id TEXT NOT NULL REFERENCES tomas_signos_vitales(id), tipo TEXT NOT NULL, valor REAL NOT NULL,
                               clasificacion TEXT NOT NULL, PRIMARY KEY (toma_id, tipo));
CREATE TABLE alertas_signos_vitales (id INTEGER PRIMARY KEY AUTOINCREMENT, toma_id TEXT NOT NULL REFERENCES tomas_signos_vitales(id),
                                     tipo TEXT NOT NULL, nivel TEXT NOT NULL CHECK (nivel IN ('anormal', 'critica')), valor REAL NOT NULL,
                                     estado_envio TEXT NOT NULL CHECK (estado_envio IN ('no_aplica', 'pendiente_envio', 'enviada')));

-- Datos de demostración: rangos de adulto (18 a 120 años) y dos pacientes ficticios.
INSERT INTO rangos_signos_vitales VALUES ('pas', 18, 120, 90, 139, 80, 179), ('pad', 18, 120, 60, 89, 40, 119),
  ('fc', 18, 120, 60, 100, 40, 130), ('fr', 18, 120, 12, 20, 8, 30), ('temp', 18, 120, 36.0, 37.5, 35.0, 39.9), ('spo2', 18, 120, 95, 100, 90, 100);
INSERT INTO pacientes VALUES ('pac-adulto', '1980-05-10'), ('pac-nino', '2021-02-01');
INSERT INTO episodios VALUES ('epi-1', 'pac-adulto', 'hosp-1', 'activo'), ('epi-2', 'pac-nino', 'hosp-1', 'activo'), ('epi-cerrado', 'pac-adulto', 'hosp-1', 'cerrado');
