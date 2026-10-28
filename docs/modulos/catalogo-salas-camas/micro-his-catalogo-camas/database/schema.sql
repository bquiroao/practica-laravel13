-- Versión SQLite mínima de wards y beds (database/migrations/hospital/..._000300_create_scheduling_and_bed_tables.php) más el historial (R-09).
CREATE TABLE IF NOT EXISTS wards (
  ward_id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')));
CREATE TABLE IF NOT EXISTS beds (
  bed_id INTEGER PRIMARY KEY, ward_id INTEGER NOT NULL REFERENCES wards(ward_id), code TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'occupied', 'maintenance', 'inactive')),
  UNIQUE (ward_id, code));
CREATE TABLE IF NOT EXISTS bed_status_history (
  id INTEGER PRIMARY KEY, ward_code TEXT NOT NULL, bed_code TEXT NOT NULL, from_status TEXT NOT NULL,
  to_status TEXT NOT NULL, reason TEXT NOT NULL CHECK (trim(reason) <> ''), changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
