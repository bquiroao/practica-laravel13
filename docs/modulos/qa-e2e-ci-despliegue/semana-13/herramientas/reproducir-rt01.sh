#!/usr/bin/env bash
# RT-01 (revisión técnica, semana 13): reintentar el registro de un despliegue lo duplica.
#   E2E_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" bash reproducir-rt01.sh   (recrea la base)
set -euo pipefail
cd "$(dirname "$0")/../../micro-his-e2e"
export E2E_PERSISTENCIA=pdo E2E_EVIDENCIAS_DIR="${TMPDIR:-/tmp}/e2e-rt01"
php bin/instalar-base.php >/dev/null
php -S 127.0.0.1:8093 -t public public/index.php >/dev/null 2>&1 &
SRV=$!; trap 'kill $SRV' EXIT; sleep 1
A=http://127.0.0.1:8093/api/v1/e2e; D="sha256:$(printf 'his-3.0.0' | sha256sum | cut -d' ' -f1)"
p() { curl -s -X POST "$A$1" -H "Authorization: Bearer $2" -H 'X-Tenant-ID: hosp-norte' -H 'Content-Type: application/json' ${4:+-H "$4"} -d "$3" -o /dev/null -w '%{http_code}'; }
p /versiones token-pipeline "{\"numero\":\"3.0.0\",\"commit\":\"a1b2c3d\",\"digest\":\"$D\",\"modulos\":[\"admision\"]}" >/dev/null
p /versiones/3.0.0/plan token-ing-qa '{"escenarios":["E2E-101","E2E-102"]}' >/dev/null
p /versiones/3.0.0/ejecuciones token-pipeline "{\"digest\":\"$D\",\"entorno\":\"staging\"}" >/dev/null
for E in E2E-101 E2E-102; do p /ejecuciones/1/resultados token-pipeline "{\"escenario\":\"$E\",\"resultado\":\"PASA\",\"intentos\":1}" "Idempotency-Key: rt01-$E" >/dev/null; done
p /ejecuciones/1/cierre token-pipeline '{}' >/dev/null; p /versiones/3.0.0/gate token-release '{"ejecucion":1}' >/dev/null
CUERPO="{\"digest\":\"$D\",\"entorno\":\"produccion\",\"guia\":\"guia-2026.3\",\"smoke\":\"PASA\"}"
echo "1.er registro del despliegue: HTTP $(p /versiones/3.0.0/despliegues token-ops "$CUERPO")"
echo "reintento idéntico (la respuesta se perdió): HTTP $(p /versiones/3.0.0/despliegues token-ops "$CUERPO")"
curl -s "$A/versiones/3.0.0/trazabilidad" -H 'Authorization: Bearer token-ops' -H 'X-Tenant-ID: hosp-norte' \
  | php -r '$t=json_decode(stream_get_contents(STDIN),true); echo "despliegues en la trazabilidad: ".count($t["despliegues"])." (esperado: 1)".PHP_EOL;'
