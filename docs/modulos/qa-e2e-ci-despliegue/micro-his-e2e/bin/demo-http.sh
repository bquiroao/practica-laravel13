#!/usr/bin/env bash
# Demostración por HTTP real: levanta el front controller con php -S sobre PostgreSQL y recorre el flujo con curl.
#   E2E_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" bash bin/demo-http.sh
set -euo pipefail
cd "$(dirname "$0")/.."
: "${E2E_DB_DSN:?Defina E2E_DB_DSN (la base se recrea: use una de prueba)}"
export E2E_PERSISTENCIA=pdo E2E_EVIDENCIAS_DIR="${TMPDIR:-/tmp}/micro-his-e2e-demo"
php bin/instalar-base.php
php -S 127.0.0.1:8091 -t public public/index.php >/dev/null 2>&1 &
SERVIDOR=$!
trap 'kill $SERVIDOR' EXIT
sleep 1
API=http://127.0.0.1:8091/api/v1/e2e
DIGEST="sha256:$(printf 'his-2.4.0+c56f169' | sha256sum | cut -d' ' -f1)"
pedir() { # método ruta token [cuerpo] [cabecera extra]
  echo "\$ $1 $2  ($3)"
  curl -s -X "$1" "$API$2" -H "Authorization: Bearer $3" -H 'X-Tenant-ID: hosp-norte' -H 'Content-Type: application/json' ${5:+-H "$5"} ${4:+-d "$4"} -w '  -> HTTP %{http_code}\n'
  echo
}
pedir POST /versiones token-pipeline "{\"numero\":\"2.4.0\",\"commit\":\"c56f169\",\"digest\":\"$DIGEST\",\"modulos\":[\"admision\"]}"
pedir POST /versiones/2.4.0/plan token-ing-qa '{"escenarios":["E2E-101","E2E-103"]}'
pedir POST /versiones/2.4.0/plan token-ing-qa '{"escenarios":["E2E-101","E2E-102","E2E-103","E2E-104","E2E-105","E2E-106"]}'
pedir POST /versiones/2.4.0/ejecuciones token-pipeline "{\"digest\":\"$DIGEST\",\"entorno\":\"staging\"}"
TRAZA=$(printf 'traza playwright · E2E-102 · paciente sintético SINT-0042 · intento 1 falla por tiempo de espera' | base64 -w0)
pedir POST /ejecuciones/1/resultados token-pipeline "{\"escenario\":\"E2E-102\",\"resultado\":\"PASA\",\"intentos\":2,\"evidencias\":[{\"tipo\":\"traza\",\"contenido_base64\":\"$TRAZA\"}]}" 'Idempotency-Key: demo-e2e-102'
for E in E2E-101 E2E-103 E2E-104 E2E-105 E2E-106; do
  curl -s -o /dev/null -X POST "$API/ejecuciones/1/resultados" -H 'Authorization: Bearer token-pipeline' -H 'X-Tenant-ID: hosp-norte' \
    -H "Idempotency-Key: demo-$E" -d "{\"escenario\":\"$E\",\"resultado\":\"PASA\",\"intentos\":1}"
done
echo '(E2E-101, 103, 104, 105 y 106 reportados con PASA)'
echo
pedir POST /ejecuciones/1/cierre token-pipeline ''
pedir POST /versiones/2.4.0/gate token-pipeline '{"ejecucion":1}'
pedir POST /versiones/2.4.0/gate token-release '{"ejecucion":1}'
