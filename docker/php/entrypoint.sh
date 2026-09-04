#!/bin/bash
set -e

cd /var/www

# Le service "app" (BOOTSTRAP_OWNER=1) construit le projet. "queue" attend juste
# que ce soit fait avant de démarrer, pour éviter deux composer/artisan en parallèle
# sur le même volume.
wait_for_bootstrap() {
    until [ -f artisan ] && [ -f .laravel-ready ]; do
        echo "En attente de la construction du projet par le service 'app'..."
        sleep 2
    done
}

if [ "$BOOTSTRAP_OWNER" = "1" ]; then
    if [ ! -f artisan ]; then
        echo "==> Aucun projet Laravel détecté : construction à partir du template..."

        mkdir -p /tmp/template
        cp -r app routes stubs docker /tmp/template/

        composer create-project laravel/laravel /tmp/laravel-new --prefer-dist --no-interaction
        cp -a /tmp/laravel-new/. .
        rm -rf /tmp/laravel-new

        echo "==> Application du template par-dessus le squelette Laravel..."
        cp -rf /tmp/template/app/. app/
        cp -rf /tmp/template/routes/. routes/
        cp -rf /tmp/template/stubs/. stubs/
        rm -rf /tmp/template

        echo "==> Installation de Sanctum..."
        composer require laravel/sanctum --no-interaction
        php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --force

        echo "==> Installation de Scramble (documentation API auto-générée)..."
        composer require dedoc/scramble --no-interaction

        echo "==> Configuration de bootstrap/app.php et des migrations de base..."
        cp docker/laravel/bootstrap-app.php bootstrap/app.php
        cp docker/laravel/migrations/*.php database/migrations/

        [ -f .env ] || cp .env.example .env
        php artisan key:generate --force
    fi

    if [ ! -f vendor/autoload.php ]; then
        echo "==> Installation des dépendances Composer..."
        composer install --no-interaction --prefer-dist
    fi

    echo "==> Attente de la base de données..."
    tries=0
    until php artisan db:show >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "La base de données ne répond pas après 30 tentatives." >&2
            exit 1
        fi
        sleep 2
    done

    echo "==> Migrations..."
    php artisan migrate --force

    touch .laravel-ready
else
    wait_for_bootstrap

    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist
    fi
fi

exec "$@"
