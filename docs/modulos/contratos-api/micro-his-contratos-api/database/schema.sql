-- Esquema SQLite del Micro-HIS: el esquema real aún no tiene tablas de documentos (supuesto de la semana 1).
CREATE TABLE IF NOT EXISTS doc_versions (
  id INTEGER PRIMARY KEY, slug TEXT NOT NULL, doc_type TEXT NOT NULL CHECK (doc_type IN ('openapi', 'postman', 'manual')), version TEXT NOT NULL,
  author TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'in_review' CHECK (status IN ('in_review', 'published', 'rejected', 'obsolete')),
  reviewed_by TEXT, reason TEXT);
-- R-02: documento-versión único, salvo las rechazadas, que pueden volver a enviarse. R-06: audiencia con los role_code de hospital_memberships.
CREATE UNIQUE INDEX IF NOT EXISTS doc_versions_slug_version ON doc_versions (slug, version) WHERE status <> 'rejected';
CREATE TABLE IF NOT EXISTS doc_audiences (version_id INTEGER NOT NULL REFERENCES doc_versions(id), role_code TEXT NOT NULL, PRIMARY KEY (version_id, role_code));
