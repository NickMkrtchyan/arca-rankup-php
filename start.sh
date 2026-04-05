#!/usr/bin/env bash
# =============================================================================
#  ArCa Gateway PHP — Docker Start Script
#  Запускает app (Apache+PHP) + MySQL через docker compose.
#
#  Usage:
#    bash start.sh           # продакшн: первый запуск / перезапуск
#    bash start.sh --local   # локалка: .env.local + phpMyAdmin, без домена
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

# ─── Режим (local / production) ──────────────────────────────────────────────
LOCAL=false
[[ "${1:-}" == "--local" ]] && LOCAL=true

DC_FILES="-f docker-compose.yml"
ENV_FILE=".env"
ENV_EXAMPLE=".env.example"
if $LOCAL; then
  DC_FILES="-f docker-compose.yml -f docker-compose.local.yml"
  ENV_FILE=".env.local"
  ENV_EXAMPLE=".env.local.example"
fi

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

# ─── Локальный режим ─────────────────────────────────────────────────────────
if $LOCAL; then
  echo ""
  echo -e "${BOLD}${CYAN}  ArCa Gateway PHP — Local Dev${NC}"
  echo -e "  ${YELLOW}Режим: localhost · phpMyAdmin · тестовый ArCa · без домена${NC}"
  echo -e "  ─────────────────────────────────────────────────────────"
  echo ""
else
  echo ""
  echo -e "${BOLD}${CYAN}  ArCa Gateway PHP — Docker Setup${NC}"
  echo -e "  ─────────────────────────────────"
  echo ""
fi

# Проверка Docker
command -v docker &>/dev/null || die "Docker not found. Install Docker first."
docker compose version &>/dev/null || die "docker compose not found."

# Helpers для ввода
read_val() {
  local var="$1" prompt="$2" default="${3:-}" target_file="${4:-.env}"
  local hint=""; [[ -n "$default" ]] && hint=" [${default}]"
  read -rp "$(echo -e "  ${BOLD}${prompt}${hint}: ${NC}")" val
  [[ -z "$val" && -n "$default" ]] && val="$default"
  sed -i "s|^${var}=.*|${var}=${val}|" "$target_file"
}
read_secret() {
  local var="$1" prompt="$2" target_file="${3:-.env}"
  read -rsp "$(echo -e "  ${BOLD}${prompt}: ${NC}")" val; echo ""
  sed -i "s|^${var}=.*|${var}=${val}|" "$target_file"
}

# ─── Создать .env / .env.local если не существует ────────────────────────────
if [[ ! -f "$ENV_FILE" ]]; then
  warn "${ENV_FILE} not found — creating from ${ENV_EXAMPLE}..."
  [[ -f "$ENV_EXAMPLE" ]] || die "${ENV_EXAMPLE} not found. Check repository."
  cp "$ENV_EXAMPLE" "$ENV_FILE"

  echo ""
  echo -e "  ${BOLD}Fill in required settings:${NC}"
  echo ""

  if $LOCAL; then
    # ── Локальный режим: минимум вопросов ──────────────────────────────────
    info "Local mode: APP_URL = http://localhost:3001, ArCa = TEST endpoint"
    read_val APP_PORT "Container port" "3001" "$ENV_FILE"
    sed -i "s|^APP_URL=.*|APP_URL=http://localhost:3001|" "$ENV_FILE"
    sed -i "s|^APP_ENV=.*|APP_ENV=development|"           "$ENV_FILE"
    sed -i "s|^ARCA_BASE_URL=.*|ARCA_BASE_URL=https://ipaytest.arca.am:8445/payment/rest|" "$ENV_FILE"

    echo ""
    info "Shopify & ArCa (опционально — можно оставить placeholder для UI-тестирования):"
    read_val    SHOPIFY_STORE          "Shopify store [Enter для пропуска]"  "your-store.myshopify.com" "$ENV_FILE"
    read_secret SHOPIFY_ACCESS_TOKEN   "Shopify Access Token"                "$ENV_FILE"
    read_secret SHOPIFY_WEBHOOK_SECRET "Shopify Webhook Secret"              "$ENV_FILE"
    read_val    ARCA_USERNAME          "ArCa test username [Enter для пропуска]" "test_user" "$ENV_FILE"
    read_secret ARCA_PASSWORD          "ArCa test password"                  "$ENV_FILE"
  else
    # ── Продакшн: все обязательные поля ────────────────────────────────────
    read_val    APP_URL                "Public URL (https://checkout.your-domain.com)" "" "$ENV_FILE"
    read_val    APP_PORT               "Local port for this container"                 "3001" "$ENV_FILE"
    read_val    DB_NAME                "Database name"                                 "arca_gateway_php" "$ENV_FILE"
    read_val    DB_USER                "MySQL user"                                    "arca" "$ENV_FILE"
    read_secret DB_PASSWORD            "MySQL password"                                "$ENV_FILE"
    read_secret DB_ROOT_PASSWORD       "MySQL ROOT password"                           "$ENV_FILE"
    read_val    SHOPIFY_STORE          "Shopify store (e.g. your-store.myshopify.com)" "" "$ENV_FILE"
    read_secret SHOPIFY_ACCESS_TOKEN   "Shopify Access Token (shpat_...)"              "$ENV_FILE"
    read_secret SHOPIFY_WEBHOOK_SECRET "Shopify Webhook Secret"                        "$ENV_FILE"
    read_val    ARCA_BASE_URL          "ArCa base URL" "https://ipay.arca.am/payment/rest" "$ENV_FILE"
    read_val    ARCA_USERNAME          "ArCa username" "" "$ENV_FILE"
    read_secret ARCA_PASSWORD          "ArCa password" "$ENV_FILE"

    echo ""
    echo -e "  Payment mode:"
    echo -e "  ${GREEN}[1]${NC} PreAuth (recommended)"
    echo -e "  ${YELLOW}[2]${NC} Sale (charge immediately)"
    read -rp "$(echo -e "  ${BOLD}Mode [1/2]: ${NC}")" pm
    [[ "$pm" == "2" ]] \
      && sed -i 's/^ARCA_AUTH_MODE=.*/ARCA_AUTH_MODE=0/' "$ENV_FILE" \
      || sed -i 's/^ARCA_AUTH_MODE=.*/ARCA_AUTH_MODE=1/' "$ENV_FILE"
  fi

  chmod 600 "$ENV_FILE"
  success "${ENV_FILE} configured."
fi

# Папка логов
mkdir -p logs

# ─── Запуск ───────────────────────────────────────────────────────────────────
if $LOCAL; then
  info "Starting LOCAL environment (app + db + phpMyAdmin)..."
else
  info "Building and starting containers..."
fi
docker compose $DC_FILES up -d --build

# ─── Health check ─────────────────────────────────────────────────────────────
info "Waiting for application to be ready..."
PORT=$(grep -E '^APP_PORT=' "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "3001")
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
APP_URL_VAL=$(grep -E '^APP_URL=' "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "")

if $LOCAL; then
  success "ArCa Gateway PHP — Local Dev is running!"
  echo ""
  echo -e "  ${BOLD}App:${NC}          http://localhost:${PORT}"
  echo -e "  ${BOLD}Dashboard:${NC}    http://localhost:${PORT}/"
  echo -e "  ${BOLD}Health:${NC}       http://localhost:${PORT}/health"
  echo -e "  ${BOLD}phpMyAdmin:${NC}   http://localhost:8080"
  echo -e "  ${BOLD}MySQL direct:${NC} localhost:3307  (user: arca / root)"
  echo -e "  ${BOLD}Logs dir:${NC}     ${SCRIPT_DIR}/logs/"
else
  success "ArCa Gateway PHP is running!"
  echo ""
  echo -e "  ${BOLD}Local:${NC}      http://localhost:${PORT}"
  [[ -n "$APP_URL_VAL" ]] && echo -e "  ${BOLD}Public:${NC}     ${APP_URL_VAL}"
  echo -e "  ${BOLD}Dashboard:${NC}  http://localhost:${PORT}/"
  echo -e "  ${BOLD}Health:${NC}     http://localhost:${PORT}/health"
  echo -e "  ${BOLD}Logs dir:${NC}   ${SCRIPT_DIR}/logs/"
fi
echo ""
echo -e "  ${BOLD}Commands:${NC}"
echo -e "  ${CYAN}bash start.sh --logs${NC}    — live logs"
echo -e "  ${CYAN}bash start.sh --status${NC}  — container status"
if ! $LOCAL; then
  echo -e "  ${CYAN}bash start.sh --update${NC}  — git pull + rebuild"
fi
echo -e "  ${CYAN}bash start.sh --stop${NC}    — stop everything"
echo -e "  ${CYAN}docker compose exec db mysql -u root -p${NC}  — MySQL shell"
echo ""

# ─── Cron reminder (только в продакшн) ────────────────────────────────────────
if ! $LOCAL; then
  echo -e "  ${YELLOW}[CRON]${NC} Don't forget to add cron jobs on the host:"
  echo -e "  ${CYAN}*/5 * * * * docker exec arca-php-app php /var/www/html/cron/autopurge.php >> /var/log/arca-cron.log 2>&1${NC}"
  echo -e "  ${CYAN}*/2 * * * * docker exec arca-php-app php /var/www/html/cron/autocapture.php >> /var/log/arca-cron.log 2>&1${NC}"
  echo ""
fi
