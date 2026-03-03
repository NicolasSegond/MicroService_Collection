#!/bin/bash
# Traffic generator with chaos simulation for Grafana dashboards
# Usage: d=120 e=15 r=10 c=40 ./traffic.sh

# ── Colors ──
CYAN='\033[36m'
GREEN='\033[32m'
YELLOW='\033[33m'
RED='\033[31m'
BOLD='\033[1m'
RESET='\033[0m'

# ── Parameters ──
DURATION=${d:-60}
ERROR_PCT=${e:-10}
RPS=${r:-5}
CRASH_INTERVAL=${c:-80}
GW="http://localhost:8000"
SLEEP=$(awk "BEGIN {printf \"%.3f\", 1/$RPS}")
TOTAL=$((DURATION * RPS))

# ── Counters ──
COUNT_2XX=0
COUNT_4XX=0
COUNT_5XX=0
COUNT_OTHER=0
CRASH_COUNT=0

# ── Cleanup on exit ──
CHAOS_PID=""
cleanup() {
    [ -n "$CHAOS_PID" ] && kill "$CHAOS_PID" 2>/dev/null
    # Ensure article-service is back up
    kubectl scale deployment article-service -n marketplace --replicas=1 >/dev/null 2>&1 || true
    wait 2>/dev/null
}
trap cleanup EXIT INT TERM

# ── Chaos loop: periodically kill article-service pod ──
chaos_loop() {
    local start
    start=$(date +%s)
    sleep "$CRASH_INTERVAL"
    while [ $(( $(date +%s) - start )) -lt "$DURATION" ]; do
        kubectl delete pod -n marketplace -l app=article-service --wait=false >/dev/null 2>&1 || true
        echo -e "\n  ${RED}[CHAOS]${RESET} Pod article-service supprimé (auto-recovery en cours...)"
        sleep "$CRASH_INTERVAL"
    done
}

# ── Display ──
echo ""
echo -e "${BOLD}${CYAN}═══ TRAFFIC GENERATOR + CHAOS ═══${RESET}"
echo ""
echo -e "  Durée:           ${YELLOW}${DURATION} s${RESET}"
echo -e "  Débit:           ${YELLOW}${RPS} req/s${RESET}"
echo -e "  Erreurs 4xx:     ${YELLOW}${ERROR_PCT}%${RESET}"
echo -e "  Crash pod:       ${YELLOW}toutes les ${CRASH_INTERVAL}s${RESET}"
echo -e "  Total estimé:    ${YELLOW}~${TOTAL} requêtes${RESET}"
echo -e "  Gateway:         ${YELLOW}${GW}${RESET}"
echo ""
echo -e "  ${CYAN}Scénarios normaux :${RESET}"
echo "    GET  /                        (health)"
echo "    GET  /api/articles             (liste)"
echo "    GET  /api/articles?page=N      (pagination)"
echo "    GET  /health                   (liveness)"
echo -e "  ${CYAN}Scénarios erreur 4xx :${RESET}"
echo "    GET  /api/articles/99999       (404 inexistant)"
echo "    GET  /api/page-inexistante     (404 route)"
echo "    POST /api/articles sans auth   (401 protégé)"
echo "    POST /api/media sans auth      (401 protégé)"
echo -e "  ${CYAN}Scénarios erreur 5xx :${RESET}"
echo "    kubectl delete pod article-service  (502/503 pendant redémarrage)"
echo ""
echo -e "${YELLOW}Envoi en cours... (Ctrl+C pour arrêter)${RESET}"
echo ""

# ── Start chaos loop in background ──
chaos_loop &
CHAOS_PID=$!

# ── Main traffic loop ──
START=$(date +%s)
i=0
while [ $(( $(date +%s) - START )) -lt "$DURATION" ]; do
    i=$((i + 1))
    RAND=$((RANDOM % 100))

    if [ "$RAND" -lt "$ERROR_PCT" ]; then
        # Error scenario (4xx)
        ERR_PICK=$((RANDOM % 4))
        case $ERR_PICK in
            0) CODE=$(curl -s -o /dev/null -w "%{http_code}" "$GW/api/articles/99999") ;;
            1) CODE=$(curl -s -o /dev/null -w "%{http_code}" "$GW/api/page-inexistante-$RANDOM") ;;
            2) CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$GW/api/articles" -H "Content-Type: application/json" -d '{}') ;;
            3) CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$GW/api/media" -H "Content-Type: application/json" -d '{}') ;;
        esac
    else
        # Normal scenario (2xx)
        OK_PICK=$((RANDOM % 4))
        case $OK_PICK in
            0) CODE=$(curl -s -o /dev/null -w "%{http_code}" "$GW/") ;;
            1) CODE=$(curl -s -o /dev/null -w "%{http_code}" "$GW/api/articles") ;;
            2) PAGE=$((RANDOM % 5 + 1))
               CODE=$(curl -s -o /dev/null -w "%{http_code}" "$GW/api/articles?page=$PAGE") ;;
            3) CODE=$(curl -s -o /dev/null -w "%{http_code}" "$GW/health") ;;
        esac
    fi

    case $CODE in
        2*) COUNT_2XX=$((COUNT_2XX + 1)) ;;
        4*) COUNT_4XX=$((COUNT_4XX + 1)) ;;
        5*) COUNT_5XX=$((COUNT_5XX + 1)) ;;
        *)  COUNT_OTHER=$((COUNT_OTHER + 1)) ;;
    esac

    if [ $((i % (RPS * 2) )) -eq 0 ]; then
        ELAPSED=$(( $(date +%s) - START ))
        printf "\r  ${GREEN}2xx: $COUNT_2XX${RESET} | ${YELLOW}4xx: $COUNT_4XX${RESET} | ${RED}5xx: $COUNT_5XX${RESET} | Autre: $COUNT_OTHER  [$ELAPSED/$DURATION s]"
    fi

    sleep "$SLEEP"
done

# ── Summary ──
echo ""
echo ""
echo -e "${BOLD}${CYAN}═══ RÉSUMÉ ═══${RESET}"
echo ""
TOTAL_SENT=$((COUNT_2XX + COUNT_4XX + COUNT_5XX + COUNT_OTHER))
echo -e "  Total envoyé:   ${BOLD}${TOTAL_SENT}${RESET} requêtes"
echo -e "  ${GREEN}2xx (OK):        $COUNT_2XX${RESET}"
echo -e "  ${YELLOW}4xx (Client):    $COUNT_4XX${RESET}"
echo -e "  ${RED}5xx (Serveur):   $COUNT_5XX${RESET}"
echo -e "  Autre (000):    $COUNT_OTHER"
echo ""
if [ "$TOTAL_SENT" -gt 0 ]; then
    SLA=$(awk "BEGIN {printf \"%.1f\", (($TOTAL_SENT - $COUNT_5XX) / $TOTAL_SENT) * 100}")
    ERR=$(awk "BEGIN {printf \"%.1f\", ($COUNT_5XX / $TOTAL_SENT) * 100}")
    echo -e "  SLA (hors 5xx):  $SLA%"
    echo -e "  Taux 5xx:        $ERR%"
fi
echo ""
