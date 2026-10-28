#!/usr/bin/env bash
# Recorrido de punta a punta contra el servidor embebido y PostgreSQL real.
#   1) psql … -f database/schema.sql
#   2) AUDITORIA_DB_DSN=… php -S 127.0.0.1:8088 -t public
#   3) bash bin/demo-http.sh [http://127.0.0.1:8088]
set -u
BASE="${1:-http://127.0.0.1:8088}/api/v1/auditoria"
paso() { echo; echo "### $1"; }
pedir() { # método ruta token hospital [cuerpo]
    local args=(-s -w '\n<- HTTP %{http_code}\n' -X "$1" "$BASE$2" -H "X-Tenant-ID: $4" -H 'Content-Type: application/json')
    [ -n "$3" ] && args+=(-H "Authorization: Bearer $3")
    [ $# -ge 5 ] && args+=(--data "$5")
    echo "-> $1 $2  (token: ${3:-ninguno}, X-Tenant-ID: $4)"
    curl "${args[@]}"
}
MOV='{"event_id":"demo-1","hospital":"hosp-oriente","usuario":"u-ana","modulo":"pacientes","accion":"PACIENTE_REGISTRADO","ocurrido_en":"2026-10-21T09:15:00-06:00","resultado":"exito","recurso":"paciente:P-000001","detalle":{"codigo":"P-000001"}}'

paso "1. Pacientes registra un movimiento (201) y lo reintenta (200 YA_REGISTRADO)"
pedir POST /movimientos token-svc-pacientes-oriente hosp-oriente "$MOV"
pedir POST /movimientos token-svc-pacientes-oriente hosp-oriente "$MOV"
paso "2. Regla de dominio: Pacientes intenta escribir a nombre de RBAC (403) y con fecha futura (422)"
pedir POST /movimientos token-svc-pacientes-oriente hosp-oriente "${MOV/\"pacientes\",\"accion\":\"PACIENTE_REGISTRADO\"/\"rbac\",\"accion\":\"ROL_ASIGNADO\"}"
pedir POST /movimientos token-svc-pacientes-oriente hosp-oriente "${MOV/2026-10-21T09:15:00/2099-01-01T00:00:00}"
paso "3. El auditor consulta su hospital con filtros (200) y sin rango (422)"
pedir GET '/movimientos?desde=2026-10-21&hasta=2026-10-21&usuario=u-ana&modulo=pacientes' token-auditor-oriente hosp-oriente
pedir GET '/movimientos?usuario=u-ana' token-auditor-oriente hosp-oriente
paso "4. Tenant: el auditor pide la red (403)"
pedir GET '/movimientos?desde=2026-10-21&hasta=2026-10-21' token-auditor-oriente '*'
paso "5. El super administrador verifica la cadena de oriente (registro + 1 consulta auditada; la del 422 nunca consultó)"
pedir POST /verificaciones token-superadmin '*' '{"hospital":"hosp-oriente"}'
