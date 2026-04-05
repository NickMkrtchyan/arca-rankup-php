#!/usr/bin/env bash
# =============================================================================
#  ArCa Gateway PHP — Docker Start Script
#  Запускает app (Apache+PHP) + MySQL через docker compose.
#
#  Usage:
#    bash start.sh           # первый запуск / перезапуск
#    bash start.sh --update  # git pull + пересборка образа
#    bash start.sh --stop    # остановить все контейнеры
#    bash start.sh --logs    # следить за логами app
#    bash start.sh --status  # статус контейнеров
# =============================================================================

set -euo pipefail

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
die()     { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# ─── Команды ─────────────────────────────────────────────────────────────────
case "${1:-}" in

  --stop)
    info "Stopping containers..."
    docker compose down
    success "Stopped."
    exit 0
    ;;

  --logs)
    docker compose logs -f app
    exit 0
    ;;

  --status)
    docker compose ps
    exit 0
    ;;

  --update)
    info "Pulling latest code..."
    git fetch origin
    git reset --hard origin/main
    info "Rebuilding image..."
    docker compose build app
    docker compose up -d --no-deps app
    sleep 4
    if docker compose exec -T app curl -sf http://localhost/health | grep -q "ok"; then
      success "Update complete and healthy."
    else
      die "Health check failed after update. Run: bash start.sh --logs"
    fi
    exit 0
    ;;

esac

# ─── Первый запуск ────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}  ArCa Gateway PHP — Docker Setup${NC}"
echo -e "  ─────────────────────────────────"
echo ""

# Проверка Docker
command -v docker &>/dev/null || die "Docker not found. Install Docker first."
docker compose version &>/dev/null || die "docker compose not found."

# Проверка .env
if [[ ! -f .env ]]; then
  warn ".env not found — creating from .env.example..."
  [[ -f .env.example ]] || die ".env.example not found either. Check repository."
  cp .env.example .env

  # Обязательные поля
  echo ""
  echo -e "  ${BOLD}Fill in required settings:${NC}"
  echo ""

  read_val() {
    local var="$1" prompt="$2" default="${3:-}"
    local hint=""; [[ -n "$default" ]] && hint=" [${default}]"
    read -rp "$(echo -e "  ${BOLD}${prompt}${hint}: ${NC}")" val
    [[ -z "$val" && -n "$default" ]] && val="$default"
    sed -i "s|^${var}=.*|${var}=${val}|" .env
  }
  read_secret() {
    local var="$1" prompt="$2"
    read -rsp "$(echo -e "  ${BOLD}${prompt}: ${NC}")" val; echo ""
    sed -i "s|^${var}=.*|${var}=${val}|" .env
  }

  read_val    APP_URL            "Public URL (e.g. https://checkout.your-domain.com)" ""
  read_val    APP_PORT           "Local port for this container"                      "3001"
  read_val    DB_NAME            "Database name"                                      "arca_gateway_php"
  read_val    DB_USER            "MySQL user"                                         "arca"
  read_secret DB_PASSWORD        "MySQL password"
  read_secret DB_ROOT_PASSWORD   "MySQL ROOT password"
  read_val    SHOPIFY_STORE      "Shopify store (e.g. your-store.myshopify.com)"      ""
  read_secret SHOPIFY_ACCESS_TOKEN  "Shopify Access Token (shpat_...)"
  read_secret SHOPIFY_WEBHOOK_SECRET "Shopify Webhook Secret"
  read_val    ARCA_BASE_URL      "ArCa base URL" "https://ipay.arca.am/payment/rest"
  read_val    ARCA_USERNAME      "ArCa username" ""
  read_secret ARCA_PASSWORD      "ArCa password"

  echo ""
  echo -e "  Payment mode:"
  echo -e "  ${GREEN}[1]${NC} PreAuth (recommended)"
  echo -e "  ${YELLOW}[2]${NC} Sale (charge immediately)"
  read -rp "$(echo -e "  ${BOLD}Mode [1/2]: ${NC}")" pm
  [[ "$pm" == "2" ]] \
    && sed -i 's/^ARCA_AUTH_MODE=.*/ARCA_AUTH_MODE=0/' .env \
    || sed -i 's/^ARCA_AUTH_MODE=.*/ARCA_AUTH_MODE=1/' .env

  chmod 600 .env
  success ".env configured."
fi

# Папка логов
mkdir -p logs

# ─── Запуск ───────────────────────────────────────────────────────────────────
info "Building and starting containers..."
docker compose up -d --build

# ─── Health check ─────────────────────────────────────────────────────────────
info "Waiting for application to be ready..."
PORT=$(grep -E '^APP_PORT=' .env | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "3001")
attempts=0
until curl -sf "http://localhost:${PORT}/health" 2>/dev/null | grep -q "ok"; do
  attempts=$((attempts + 1))
  [[ $attempts -ge 24 ]] && {
    warn "Health check timed out. Check logs:"
    docker compose logs --tail=30 app
    die "Startup failed."
  }
  sleep 5
  printf "."
done
echo ""

# ─── Summary ─────────────────────────────────────────────────────────────────
APP_URL=$(grep -E '^APP_URL=' .env | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "")

success "ArCa Gateway PHP is running!"
echo ""
echo -e "  ${BOLD}Local:${NC}      http://localhost:${PORT}"
[[ -n "$APP_URL" ]] && echo -e "  ${BOLD}Public:${NC}     ${APP_URL}"
echo -e "  ${BOLD}Dashboard:${NC}  http://localhost:${PORT}/"
echo -e "  ${BOLD}Health:${NC}     http://localhost:${PORT}/health"
echo -e "  ${BOLD}Logs dir:${NC}   ${SCRIPT_DIR}/logs/"
echo ""
echo -e "  ${BOLD}Commands:${NC}"
echo -e "  ${CYAN}bash start.sh --logs${NC}    — live logs"
echo -e "  ${CYAN}bash start.sh --status${NC}  — container status"
echo -e "  ${CYAN}bash start.sh --update${NC}  — git pull + rebuild"
echo -e "  ${CYAN}bash start.sh --stop${NC}    — stop everything"
echo -e "  ${CYAN}docker compose exec db mysql -u root -p${NC}  — MySQL shell"
echo ""

# ─── Cron reminder ───────────────────────────────────────────────────────────
echo -e "  ${YELLOW}[CRON]${NC} Don't forget to add cron jobs on the host:"
echo -e "  ${CYAN}*/5 * * * * docker exec arca-php-app php /var/www/html/cron/autopurge.php >> /var/log/arca-cron.log 2>&1${NC}"
echo -e "  ${CYAN}*/2 * * * * docker exec arca-php-app php /var/www/html/cron/autocapture.php >> /var/log/arca-cron.log 2>&1${NC}"
echo ""
