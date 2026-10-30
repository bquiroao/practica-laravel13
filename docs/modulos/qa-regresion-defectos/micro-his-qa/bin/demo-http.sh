#!/usr/bin/env bash
# Recorrido HTTP real: servidor embebido de PHP + PostgreSQL. Desde micro-his-qa/:
#   QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" bash bin/demo-http.sh
set -u
PUERTO="${PUERTO:-8093}"
BASE="http://127.0.0.1:$PUERTO/api/v1/qa"
export QA_EVIDENCIAS_DIR="${QA_EVIDENCIAS_DIR:-$(mktemp -d)}"
php bin/instalar-base.php
php -S "127.0.0.1:$PUERTO" -t public >/dev/null 2>&1 &
SERVIDOR=$!
trap 'kill $SERVIDOR 2>/dev/null' EXIT
sleep 1

pedir() { # pedir <título> <método> <ruta> <token> [cuerpo] [idempotency-key]
  echo; echo "### $1"; echo "> $2 /api/v1/qa$3"
  local args=(-s -i -X "$2" "$BASE$3" -H "Authorization: Bearer $4" -H "X-Tenant-ID: hosp-norte" -H "Content-Type: application/json")
  [ -n "${6:-}" ] && args+=(-H "Idempotency-Key: $6")
  # El cuerpo va por stdin: como argumento, en Windows los acentos llegarían en la página de códigos ANSI (no UTF-8).
  if [ -n "${5:-}" ]; then printf '%s' "$5" | curl "${args[@]}" --data-binary @-; else curl "${args[@]}"; fi | tr -d '\r' | grep -E "^HTTP/|^Location:|^\{" ; echo
}
PNG=$(printf '\x89PNG\r\n\x1a\nnota-firmada-editable' | base64 -w0)

pedir "1. El Analista QA diseña TC-200 (queda BORRADOR)" POST /casos token-ana-qa \
  '{"id":"TC-200","titulo":"H4 diagnóstico: código CIE-10 válido","pasos":[{"accion":"Registrar X99.9","esperado":"Se rechaza"}],"prioridad":"ALTA","requisitos":["RF-07"]}'
pedir "2. El Analista QA no puede aprobar (no tiene qa.aprobar): 403" POST /casos/TC-200/aprobacion token-ana-qa
pedir "3. El Líder QA lo aprueba" POST /casos/TC-200/aprobacion token-lider-qa
pedir "4. Campaña sin los críticos de la matriz: se planifica..." POST /campanas token-lider-qa \
  '{"nombre":"Regresión H4 incompleta","funcionalidades":["expediente"],"casos":["TC-109"]}'
pedir "5. ...pero no inicia: faltan TC-107 y TC-108 (422)" POST /campanas/1/inicio token-lider-qa '{"build":"1.4.0+abc1234"}'
pedir "6. Campaña completa: planificar (201 + Location)" POST /campanas token-lider-qa \
  '{"nombre":"Regresión H4","funcionalidades":["expediente"],"casos":["TC-107","TC-108","TC-200"]}'
pedir "7. Iniciar sobre el build 1.4.0" POST /campanas/2/inicio token-lider-qa '{"build":"1.4.0+abc1234"}'
pedir "8. FALLA sin evidencia: 422" POST /campanas/2/resultados token-tito-tester \
  '{"caso":"TC-107","resultado":"FALLA","defecto":{"titulo":"Nota firmada editable","pasos":["Abrir","Editar"],"severidad":"CRITICA"}}' demo-00000001
pedir "9. FALLA con captura y defecto: 201" POST /campanas/2/resultados token-tito-tester \
  "{\"caso\":\"TC-107\",\"resultado\":\"FALLA\",\"evidencias\":[{\"tipo\":\"captura\",\"contenido_base64\":\"$PNG\"}],\"defecto\":{\"titulo\":\"Nota firmada editable\",\"pasos\":[\"Abrir\",\"Editar\"],\"severidad\":\"CRITICA\"}}" demo-00000002
pedir "10. Reintento con la misma Idempotency-Key: 200, el mismo intento" POST /campanas/2/resultados token-tito-tester \
  "{\"caso\":\"TC-107\",\"resultado\":\"FALLA\",\"evidencias\":[{\"tipo\":\"captura\",\"contenido_base64\":\"$PNG\"}],\"defecto\":{\"titulo\":\"Nota firmada editable\",\"pasos\":[\"Abrir\",\"Editar\"],\"severidad\":\"CRITICA\"}}" demo-00000002
pedir "11. TC-108 y TC-200 pasan" POST /campanas/2/resultados token-tito-tester '{"caso":"TC-108","resultado":"PASA"}' demo-00000003
pedir "11b." POST /campanas/2/resultados token-tito-tester '{"caso":"TC-200","resultado":"PASA"}' demo-00000004
pedir "12. El Líder QA confirma la severidad CRÍTICA" POST /defectos/1/clasificacion token-lider-qa '{"severidad":"CRITICA","prioridad":"ALTA"}'
pedir "13. Cerrar ahora: NO_GO (1 crítico abierto, tasa 66,7 %)" POST /campanas/2/cierre token-lider-qa
pedir "14. El desarrollador no puede registrar resultados (403)" POST /campanas/2/resultados token-dev-luis '{"caso":"TC-107","resultado":"PASA"}' demo-00000005
pedir "15. El desarrollador marca el defecto CORREGIDO en 1.4.1" POST /defectos/1/correccion token-dev-luis '{"build":"1.4.1+def5678"}'
pedir "16. Nueva campaña sobre 1.4.1" POST /campanas token-lider-qa \
  '{"nombre":"Regresión H4 (1.4.1)","funcionalidades":["expediente"],"casos":["TC-107","TC-108"]}'
pedir "16b." POST /campanas/3/inicio token-lider-qa '{"build":"1.4.1+def5678"}'
pedir "17. Re-ejecución de TC-107 que verifica el defecto 1: PASA lo CIERRA" POST /campanas/3/resultados token-tito-tester \
  '{"caso":"TC-107","resultado":"PASA","verifica_defecto":1}' demo-00000006
pedir "17b." POST /campanas/3/resultados token-tito-tester '{"caso":"TC-108","resultado":"PASA"}' demo-00000007
pedir "18. Cerrar: GO" POST /campanas/3/cierre token-lider-qa
pedir "19. Trazabilidad de la campaña 2 (Responsable de producto)" GET /campanas/2/trazabilidad token-producto
pedir "20. Sin token válido: 401" GET /campanas/2/trazabilidad token-falso
