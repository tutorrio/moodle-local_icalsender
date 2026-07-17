#!/usr/bin/env bash

# Run the same static checks and unit tests used by the GitHub Actions workflow.

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOODLE_DIR="${MOODLE_DIR:-$ROOT_DIR/../moodle}"
WITH_BEHAT=false

PHP_SCAN_DIR="$(php --ini | sed -n 's/^Scan for additional .ini files in: //p')"
if [[ -n "$PHP_SCAN_DIR" && "$PHP_SCAN_DIR" != '(none)' ]]; then
    export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-$PHP_SCAN_DIR:$ROOT_DIR/scripts}"
fi

usage() {
    cat <<'EOF'
Usage: scripts/run-tests.sh [--with-behat]

Runs Moodle plugin validation, linting, documentation checks, upgrade
savepoint checks, JavaScript/template checks, and PHPUnit tests.

Options:
  --with-behat  Also run the browser-based Behat acceptance tests.
  -h, --help    Show this help message.

The script expects moodle-plugin-ci to have already been installed and its
Moodle test environment initialized. Set MOODLE_PLUGIN_CI_BIN to use a
specific executable, or MOODLE_DIR to use a different Moodle checkout.
EOF
}

while (($#)); do
    case "$1" in
        --with-behat)
            WITH_BEHAT=true
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            printf 'Unknown option: %s\n\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
    shift
done

find_moodle_plugin_ci() {
    if [[ -n "${MOODLE_PLUGIN_CI_BIN:-}" ]]; then
        printf '%s\n' "$MOODLE_PLUGIN_CI_BIN"
        return
    fi

    if command -v moodle-plugin-ci >/dev/null 2>&1; then
        command -v moodle-plugin-ci
        return
    fi

    local candidate
    for candidate in \
        "$ROOT_DIR/scripts/ci/bin/moodle-plugin-ci" \
        "$ROOT_DIR/scripts/ci/vendor/bin/moodle-plugin-ci" \
        "$ROOT_DIR/ci/bin/moodle-plugin-ci" \
        "$ROOT_DIR/ci/vendor/bin/moodle-plugin-ci" \
        "$ROOT_DIR/../ci/bin/moodle-plugin-ci" \
        "$ROOT_DIR/../ci/vendor/bin/moodle-plugin-ci"; do
        if [[ -x "$candidate" ]]; then
            printf '%s\n' "$candidate"
            return
        fi
    done

    return 1
}

if ! MPCI_BIN="$(find_moodle_plugin_ci)"; then
    cat >&2 <<'EOF'
Unable to find moodle-plugin-ci.

Install it first, for example:
  composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ci

Then initialize its Moodle environment as documented by moodle-plugin-ci,
or set MOODLE_PLUGIN_CI_BIN to the executable path.
EOF
    exit 2
fi

if [[ ! -x "$MPCI_BIN" ]]; then
    printf 'moodle-plugin-ci is not executable: %s\n' "$MPCI_BIN" >&2
    exit 2
fi

if [[ ! -f "$MOODLE_DIR/config.php" ]]; then
    cat >&2 <<EOF
Moodle's test environment is not initialized at:
  $MOODLE_DIR

Complete moodle-plugin-ci installation first, or set MOODLE_DIR to an
initialized Moodle checkout containing config.php.
EOF
    exit 2
fi

cd "$ROOT_DIR"

if [[ -d "$MOODLE_DIR/public/local" ]]; then
    MOODLE_LOCAL_DIR="$MOODLE_DIR/public/local"
else
    MOODLE_LOCAL_DIR="$MOODLE_DIR/local"
fi
MOODLE_PLUGIN_DIR="$MOODLE_LOCAL_DIR/icalsender"

if [[ "$(realpath -m "$MOODLE_PLUGIN_DIR")" != "$ROOT_DIR" ]]; then
    if ! command -v rsync >/dev/null 2>&1; then
        printf 'rsync is required to synchronize the plugin into Moodle.\n' >&2
        exit 2
    fi
    printf 'Synchronizing plugin into %s\n' "$MOODLE_PLUGIN_DIR"
    mkdir -p "$MOODLE_PLUGIN_DIR"
    rsync -a --delete \
        --exclude='.git/' \
        --exclude='moodle/' \
        --exclude='moodledata/' \
        --exclude='node_modules/' \
        --exclude='scripts/ci/' \
        --exclude='vendor/' \
        "$ROOT_DIR/" "$MOODLE_PLUGIN_DIR/"
fi

failures=()
advisories=()

run_required() {
    local label="$1"
    shift

    printf '\n==> %s\n' "$label"
    if "$MPCI_BIN" "$@" "$ROOT_DIR"; then
        printf 'PASS: %s\n' "$label"
    else
        printf 'FAIL: %s\n' "$label" >&2
        failures+=("$label")
    fi
}

run_advisory() {
    local label="$1"
    shift

    printf '\n==> %s (advisory)\n' "$label"
    if "$MPCI_BIN" "$@" "$ROOT_DIR"; then
        printf 'PASS: %s\n' "$label"
    else
        printf 'WARN: %s\n' "$label" >&2
        advisories+=("$label")
    fi
}

run_required 'PHP syntax' phplint
run_advisory 'PHP Mess Detector' phpmd --moodle "$MOODLE_DIR"
run_required 'Moodle coding style' phpcs --max-warnings 0
run_required 'PHPDoc' phpdoc --moodle "$MOODLE_DIR" --max-warnings 0
run_required 'Plugin validation' validate --moodle "$MOODLE_DIR"
run_required 'Upgrade savepoints' savepoints
run_required 'Mustache lint' mustache --moodle "$MOODLE_DIR"
if find "$ROOT_DIR" \
    \( -path "$ROOT_DIR/.git" -o -path "$ROOT_DIR/node_modules" -o -path "$ROOT_DIR/vendor" \) -prune -o \
    -type f \( -name '*.js' -o -name '*.css' -o -name '*.scss' \) -print -quit | grep -q .; then
    run_required 'Grunt lint' grunt --moodle "$MOODLE_DIR" --max-lint-warnings 0
else
    printf '\n==> Grunt lint\nSKIP: no JavaScript or CSS/SCSS source files found.\n'
fi
run_required 'PHPUnit' phpunit --moodle "$MOODLE_DIR" --fail-on-warning

if [[ "$WITH_BEHAT" == true ]]; then
    run_required 'Behat' behat --moodle "$MOODLE_DIR" --profile chrome --scss-deprecations
fi

printf '\n==> Test summary\n'
if ((${#advisories[@]})); then
    printf 'Advisory warnings: %s\n' "${advisories[*]}"
fi

if ((${#failures[@]})); then
    printf 'Failed checks: %s\n' "${failures[*]}" >&2
    exit 1
fi

printf 'All required checks passed.\n'
