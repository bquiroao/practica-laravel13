#!/usr/bin/env bash
# Recorrido HTTP real: servidor embebido de PHP + PostgreSQL. Desde micro-his-prescripciones/:
#   PRESCRIPCIONES_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_prescripciones" bash bin/demo-http.sh
set -u
PUERTO="${PUERTO:-8091}"
BASE="http://127.0.0.1:$PUERTO/api/v1/prescripciones"
php bin/instalar-base.php
php -S "127.0.0.1:$PUERTO" -t public >/dev/null 2>&1 &
SERVIDOR=$!
trap 'kill $SERVIDOR 2>/dev/null' EXIT
sleep 1

pedir() { # pedir <título> <método> <url> <token> [cuerpo] [idempotency-key]
  echo; echo "### $1"; echo "> $2 ${3#http://127.0.0.1:$PUERTO}"
  local args=(-s -i -X "$2" "$3" -H "Authorization: Bearer $4" -H "X-Tenant-ID: hosp-norte" -H "Content-Type: application/json")
  [ -n "${5:-}" ] && args+=(-d "$5")
  [ -n "${6:-}" ] && args+=(-H "Idempotency-Key: $6")
  curl "${args[@]}" | grep -E "^HTTP/|^Location:|^\{" ; echo
}

pedir "1. Verificación previa (P-002, alérgico a penicilina): advertencia, no guarda nada" POST "$BASE/verificaciones" token-dr-lopez \
  '{"paciente":"P-002","items":[{"medicamento":"CEFA500","dosis":500,"unidad":"mg","frecuencia_horas":6,"via":"oral","dias":7}]}'
pedir "2. Emitir sin justificación: 409" POST "$BASE" token-dr-lopez \
  '{"paciente":"P-002","items":[{"medicamento":"CEFA500","dosis":500,"unidad":"mg","frecuencia_horas":6,"via":"oral","dias":7}]}' demo-1
pedir "3. Emitir con justificación y override: 201 + Location" POST "$BASE" token-dr-lopez \
  '{"paciente":"P-002","items":[{"medicamento":"CEFA500","dosis":500,"unidad":"mg","frecuencia_horas":6,"via":"oral","dias":7}],"justificacion":"Sin alternativa: cultivo sensible solo a cefalosporinas"}' demo-2
pedir "4. Reintento con la misma Idempotency-Key: 200, el mismo id" POST "$BASE" token-dr-lopez \
  '{"paciente":"P-002","items":[{"medicamento":"CEFA500","dosis":500,"unidad":"mg","frecuencia_horas":6,"via":"oral","dias":7}],"justificacion":"Sin alternativa: cultivo sensible solo a cefalosporinas"}' demo-2
pedir "5. Amoxicilina a P-001 (alergia directa): 422 bloqueo" POST "$BASE" token-dr-lopez \
  '{"paciente":"P-001","items":[{"medicamento":"AMOX500","dosis":500,"unidad":"mg","frecuencia_horas":8,"via":"oral","dias":5}]}' demo-3
pedir "6. Paracetamol 1000 mg cada 4 h: 422 dosis" POST "$BASE" token-dr-lopez \
  '{"paciente":"P-005","items":[{"medicamento":"PARA500","dosis":1000,"unidad":"mg","frecuencia_horas":4,"via":"oral","dias":3}]}' demo-4
pedir "7. Farmacia consulta la prescripción 1 con su verificación" GET "$BASE/1" token-farmacia-norte
pedir "8. Sin token válido: 401" GET "$BASE/1" token-falso
