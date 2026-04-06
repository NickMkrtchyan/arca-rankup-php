# ArCa Gateway — PHP Edition

> Shopify ↔ ArCa (ipay.arca.am) payment middleware.  
> PHP 8.1 · Apache · Guzzle 7 · Alpine.js Dashboard · Docker

---

## Quick Start

### 🖥 Локальная разработка

```bash
git clone https://github.com/YOUR_ORG/arca-rankup-php.git
cd arca-rankup-php
bash start.sh --local
```

Поднимает: **App** (`localhost:3001`) + **MySQL** + **phpMyAdmin** (`localhost:8080`)  
Домен, SSL и реальные credentials ArCa не нужны.

---

### 🚀 Продакшн (VPS / Hostinger Docker)

```bash
git clone https://github.com/YOUR_ORG/arca-rankup-php.git
cd arca-rankup-php
bash start.sh
```

Спросит все настройки (домен, Shopify, ArCa, DB), создаст `.env`, запустит Docker.

---

## Требования

| Компонент | Версия |
|-----------|--------|
| PHP | 8.1+ с `pdo_mysql`, `curl`, `mbstring` |
| Docker | 24+ |
| docker compose | v2 |
| MySQL | 8.0 (в контейнере) |
| Composer | 2 (только для локальной разработки без Docker) |

---

## Команды `start.sh`

| Команда | Описание |
|---------|----------|
| `bash start.sh` | Продакшн: первый запуск / перезапуск |
| `bash start.sh --local` | Локалка: без домена, phpMyAdmin, тестовый ArCa |
| `bash start.sh --update` | `git pull` + пересборка образа |
| `bash start.sh --stop` | Остановить все контейнеры |
| `bash start.sh --logs` | Live логи app-контейнера |
| `bash start.sh --status` | Статус контейнеров |

---

## Переменные окружения

Скопируйте нужный файл и заполните:

```bash
# Для продакшна:
cp .env.example .env

# Для локальной разработки:
cp .env.local.example .env.local
```

Ключевые переменные:

| Переменная | Описание |
|-----------|----------|
| `APP_URL` | Публичный URL (`https://checkout.your-domain.com`) |
| `APP_PORT` | Порт контейнера (`3001` для PHP, `3000` для Node.js) |
| `ARCA_AUTH_MODE` | `1` = PreAuth + capture; `0` = Sale (списание сразу) |
| `ARCA_BASE_URL` | Тест: `https://ipaytest.arca.am:8445/payment/rest` |
| `SHOPIFY_ACCESS_TOKEN` | `shpat_...` из Shopify Admin → Custom App |

---

## Структура проекта

```
arca-rankup-php/
├── public/index.php         ← Front controller (Nginx root)
├── src/
│   ├── Config.php           ← .env аксессоры
│   ├── DB.php               ← PDO singleton
│   ├── Logger.php           ← файловый логгер
│   ├── Routes/              ← Payment · Result · Webhook · Api
│   ├── Services/            ← ArCa.php · Shopify.php
│   └── Workers/             ← Autopurge · Autocapture
├── cron/                    ← CLI entrypoints для cron
├── templates/               ← dashboard.php (Alpine.js) · result.php
├── db/schema.sql
├── Dockerfile               ← php:8.1-apache, 2-stage build
├── docker-compose.yml       ← продакшн
├── docker-compose.local.yml ← локалка (phpMyAdmin, source mount)
├── start.sh                 ← умный стартер
├── .env.example
└── .env.local.example
```

---

## Маршруты

| URL | Метод | Описание |
|-----|-------|----------|
| `/pay?orderid=ID` | GET | Инициализация платежа → редирект на ArCa |
| `/result?orderId=UUID` | GET | Коллбэк от ArCa → обновление Shopify |
| `/webhook/cancel` | POST | `orders/cancelled` / `refunds/create` → reverse/refund |
| `/webhook/capture` | POST | `orders/paid` → `deposit.do` |
| `/` | GET | Alpine.js Dashboard |
| `/api/stats` | GET | JSON статистика |
| `/api/config` | GET | JSON конфигурация |
| `/health` | GET | `{"status":"ok"}` |

---

## Shopify Webhooks

В **Shopify Admin → Settings → Notifications → Webhooks** добавить:

| Событие | URL |
|---------|-----|
| `orders/cancelled` | `https://your-domain.com/webhook/cancel` |
| `refunds/create` | `https://your-domain.com/webhook/cancel` |
| `orders/paid` | `https://your-domain.com/webhook/capture` |

---

## Cron (продакшн, на хосте VPS)

```bash
crontab -e
```

```
*/5 * * * * docker exec arca-php-app php /var/www/html/cron/autopurge.php  >> /var/log/arca-cron.log 2>&1
*/2 * * * * docker exec arca-php-app php /var/www/html/cron/autocapture.php >> /var/log/arca-cron.log 2>&1
```

---

## Локальная разработка — детали

```
localhost:3001  → App (Apache + PHP)
localhost:8080  → phpMyAdmin
localhost:3307  → MySQL (прямое подключение через TablePlus / DBeaver)
```

Исходный код (`src/`, `templates/`, `public/`) монтируется в контейнер — **изменения отражаются без rebuild**.

```bash
# Тестирование воркеров вручную:
docker exec arca-php-app php /var/www/html/cron/autopurge.php
docker exec arca-php-app php /var/www/html/cron/autocapture.php

# MySQL shell:
docker compose exec db mysql -u arca -p arca_gateway_local
```

---

## Документация

Полная документация: [`doc/index.html`](doc/index.html)  
Откройте файл в браузере или разверните через любой статический сервер.

---

## Лицензия

MIT — свободное использование в коммерческих проектах.
