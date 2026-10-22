#!/usr/bin/env bash
#
# Deploy the latest code from GitHub on the production server.
# Follows "13. Deploying updates" in docs/server-setup-guide.docx.
#
# Usage (as root, on the server):
#   bash /var/www/water-billing/deploy.sh            # normal deploy
#   bash /var/www/water-billing/deploy.sh --seed     # also re-seed roles & permissions
#
# Settings can be overridden with environment variables:
#   APP_DIR=/var/www/water-billing  BRANCH=main  APP_USER=www-data
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/water-billing}"
BRANCH="${BRANCH:-main}"
APP_USER="${APP_USER:-www-data}"
SERVICES=(water-billing-queue water-billing-reverb)

SEED_PERMISSIONS=false
for arg in "$@"; do
    case "$arg" in
        --seed) SEED_PERMISSIONS=true ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg (try --help)" >&2; exit 1 ;;
    esac
done

if [[ $EUID -ne 0 ]]; then
    echo "Run as root (it restarts the queue and Reverb services): sudo bash $0" >&2
    exit 1
fi

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
as_app() { sudo -u "$APP_USER" -H "$@"; }

cd "$APP_DIR"

# If anything fails after the site goes down, bring it back up so visitors
# are not left on the maintenance page. The error is still reported.
SITE_DOWN=false
on_exit() {
    local status=$?
    if [[ $SITE_DOWN == true ]]; then
        echo "Deploy failed (exit $status) — taking the site out of maintenance mode." >&2
        as_app php artisan up || true
    fi
    exit "$status"
}
trap on_exit EXIT

step "Fetching $BRANCH"
as_app git fetch origin "$BRANCH"
if [[ -n "$(as_app git status --porcelain --untracked-files=no)" ]]; then
    echo "The server copy has local changes to tracked files; refusing to deploy over them:" >&2
    as_app git status --short --untracked-files=no >&2
    exit 1
fi
OLD_REV="$(as_app git rev-parse --short HEAD)"

step "Maintenance mode on"
as_app php artisan down --retry=30
SITE_DOWN=true

step "Updating code"
as_app git merge --ff-only "origin/$BRANCH"
NEW_REV="$(as_app git rev-parse --short HEAD)"

step "Installing PHP dependencies"
as_app composer install --no-dev --optimize-autoloader --no-interaction

step "Building front end"
as_app npm ci --no-audit --no-fund
as_app npm run build

step "Running migrations"
as_app php artisan migrate --force

if [[ $SEED_PERMISSIONS == true ]]; then
    step "Seeding roles & permissions"
    as_app php artisan db:seed --class=RolesAndPermissionsSeeder --force
fi

step "Caching config, routes and views"
as_app php artisan optimize

step "Restarting ${SERVICES[*]}"
systemctl restart "${SERVICES[@]}"

step "Maintenance mode off"
as_app php artisan up
SITE_DOWN=false

step "Deployed $OLD_REV → $NEW_REV"
as_app git log --oneline "$OLD_REV..$NEW_REV" || true
