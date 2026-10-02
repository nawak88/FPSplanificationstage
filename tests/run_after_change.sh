#!/bin/sh
set -u

MODULE_ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
SKELETOR_ROOT="$(CDPATH= cd -- "$MODULE_ROOT/../.." && pwd)"
DOCKER_DIR="$SKELETOR_ROOT/docker"

PASS=0
FAIL=0

header() {
    echo
    echo "============================================================"
    echo " $1"
    echo "============================================================"
}

ok() {
    PASS=$((PASS + 1))
    echo "OK : $1"
}

ko() {
    FAIL=$((FAIL + 1))
    echo "ECHEC : $1"
}

run_step() {
    LABEL="$1"
    shift

    header "$LABEL"

    if "$@"; then
        ok "$LABEL"
    else
        ko "$LABEL"
    fi

    # Toujours continuer afin d'obtenir le bilan complet des tests.
    return 0
}

if [ ! -f "$MODULE_ROOT/module.json" ]; then
    echo "ERREUR : module.json introuvable."
    exit 1
fi

if [ ! -d "$DOCKER_DIR" ]; then
    echo "ERREUR : dossier Docker introuvable."
    exit 1
fi

echo
echo "FPSplanificationstage - tests après modification"
echo "Module : $MODULE_ROOT"
echo

run_step \
    "1/8 Syntaxe PHP du module" \
    sh -c '
        cd "'"$DOCKER_DIR"'"
        docker compose exec -T php sh -lc '"'"'
            set -e
            find /app/Modules/FPSplanificationstage \
                -path "*/.git" -prune -o \
                -type f -name "*.php" -print0 \
                | xargs -0 -n1 php -l >/tmp/fps_module_lint.log
            tail -n 20 /tmp/fps_module_lint.log
        '"'"'
    '

run_step \
    "2/8 Autoload Composer / PSR-4" \
    sh -c '
        cd "'"$DOCKER_DIR"'"
        LOG="$(mktemp)"
        if ! docker compose exec -T php \
            composer dump-autoload -o --no-scripts \
            >"$LOG" 2>&1
        then
            cat "$LOG"
            rm -f "$LOG"
            exit 1
        fi

        cat "$LOG"

        if grep -E \
            "does not comply with psr-4|Ambiguous class resolution|PlanificationStages_backup_" \
            "$LOG"
        then
            rm -f "$LOG"
            exit 1
        fi

        rm -f "$LOG"
    '

run_step \
    "3/8 Tests unitaires métier sans BDD" \
    sh -c '
        cd "'"$DOCKER_DIR"'"
        docker compose exec -T php \
            vendor/bin/pest \
            Modules/FPSplanificationstage/tests/Unit \
            --do-not-record-test-run-history
    '

run_step \
    "4/8 Tests Feature du module" \
    sh -c '
        cd "'"$DOCKER_DIR"'"
        docker compose exec -T php \
            vendor/bin/pest \
            Modules/FPSplanificationstage/tests/Feature \
            --do-not-record-test-run-history
    '

run_step \
    "5/8 Boot du module et du panel Filament" \
    sh -c '
        cd "'"$DOCKER_DIR"'"
        docker compose exec -T php php -r '"'"'
            require "/app/vendor/autoload.php";

            $app = require "/app/bootstrap/app.php";

            $kernel = $app->make(
                Illuminate\Contracts\Console\Kernel::class
            );

            $kernel->bootstrap();

            $provider = new ReflectionClass(
                Modules\FPSplanificationstage\Providers\FPSplanificationstageServiceProvider::class
            );

            if (
                ! str_contains(
                    $provider->getFileName(),
                    "/Modules/FPSplanificationstage/"
                )
            ) {
                fwrite(
                    STDERR,
                    "Provider chargé depuis un mauvais chemin.\n"
                );
                exit(21);
            }

            $panel = Filament\Facades\Filament::getPanel(
                "fpsplanificationstage"
            );

            if (
                $panel->getId()
                !== "fpsplanificationstage"
            ) {
                fwrite(
                    STDERR,
                    "ID panel incorrect.\n"
                );
                exit(22);
            }

            if (
                ! str_ends_with(
                    $panel->getPath(),
                    "/fpsplanificationstage"
                )
                && $panel->getPath()
                    !== "apps/fpsplanificationstage"
            ) {
                fwrite(
                    STDERR,
                    "Chemin panel incorrect : "
                    . $panel->getPath()
                    . "\n"
                );
                exit(23);
            }

            echo "Panel : "
                . $panel->getId()
                . PHP_EOL;

            echo "Chemin : "
                . $panel->getPath()
                . PHP_EOL;

            echo "Pages : "
                . count($panel->getPages())
                . PHP_EOL;

            echo "Ressources : "
                . count($panel->getResources())
                . PHP_EOL;
        '"'"'
    '

run_step \
    "6/8 Routes du module" \
    sh -c '
        cd "'"$DOCKER_DIR"'"

        docker compose exec -T php php -r '"'"'
            require "/app/vendor/autoload.php";

            $app = require "/app/bootstrap/app.php";

            $kernel = $app->make(
                Illuminate\Contracts\Console\Kernel::class
            );

            $kernel->bootstrap();

            $resource = Modules\FPSplanificationstage\Filament\Resources\PortailFormations\PortailFormationResource::class;
            $base = $resource::getRouteBaseName(
                Filament\Facades\Filament::getPanel("fpsplanificationstage")
            );
            $required = [
                $base . ".inscription",
                $base . ".besoin",
                $base . ".recherche-besoin",
                $base . ".pdf",
            ];

            $routes = $app
                ->make("router")
                ->getRoutes();

            foreach ($required as $name) {
                $route = $routes->getByName($name);

                if ($route === null) {
                    fwrite(
                        STDERR,
                        "Route manquante : "
                        . $name
                        . PHP_EOL
                    );

                    exit(31);
                }

                echo $name
                    . " -> "
                    . $route->uri()
                    . PHP_EOL;
            }
        '"'"'
    '

run_step \
    "7/8 Identifiants historiques actifs" \
    sh -c '
        cd "'"$MODULE_ROOT"'"

        FOUND="$(
            grep -RInI \
                --exclude-dir=.git \
                --exclude-dir=database/migrations \
                --exclude="*.sh" \
                --exclude="*:Zone.Identifier" \
                -E \
                "Modules\\\\PlanificationStages|PlanificationStagesServiceProvider|planificationstages::|build-planificationstages|planificationstages_" \
                app config routes resources database/seeders \
                2>/dev/null \
                || true
        )"

        if [ -n "$FOUND" ]; then
            echo "$FOUND"
            exit 1
        fi

        echo "Aucune ancienne dépendance active détectée."
    '

run_step \
    "8/8 Propreté du diff Git" \
    sh -c '
        cd "'"$MODULE_ROOT"'"

        git diff --check

        if ! git diff --cached --check; then
            exit 1
        fi

        echo
        echo "Etat Git :"
        git status --short
    '

header "RESULTAT"

echo "Tests réussis : $PASS"
echo "Tests échoués : $FAIL"

if [ "$FAIL" -ne 0 ]; then
    echo
    echo "FPSplanificationstage : ECHEC DES TESTS"
    exit 1
fi

echo
echo "FPSplanificationstage : TOUS LES TESTS SONT OK"
exit 0
