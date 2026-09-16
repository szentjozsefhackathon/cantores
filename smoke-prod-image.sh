#!/bin/bash
#
# Smoke-test the production image on this machine. Builds it, runs it against a
# throwaway Postgres and Redis, and asserts the things only a running container
# can tell you. It never contacts the deploy server and never pushes anything —
# build-prod-image.sh uploads, this does not.
#
# Why it exists: Pest tests the application, not the image. It cannot see a
# Dockerfile ARG declared in the wrong scope, an entrypoint that leaves a shell
# as PID 1 so SIGTERM is never forwarded, a server that cannot bind its port as
# an unprivileged user, or a security header lost with the Apache vhost. Each of
# those ships silently and surfaces on a deploy. Two of them did.
#
# Usage:
#   ./smoke-prod-image.sh              build the image, then test it
#   ./smoke-prod-image.sh --no-build   test the image already built
#   SMOKE_PORT=19000 ./smoke-prod-image.sh
#
# Exit status is 0 only if every check passed.

set -uo pipefail

cd "$(dirname "$0")"

COMPOSE_FILE=docker/smoke/docker-compose.smoke.yml
SMOKE_PORT=${SMOKE_PORT:-18001}
BASE="http://127.0.0.1:${SMOKE_PORT}"
EXPECTED_WORKERS=4

export SMOKE_PORT
export SMOKE_APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"

DC=(docker compose -f "$COMPOSE_FILE")

BUILD=1
for arg in "$@"; do
    case "$arg" in
        --no-build) BUILD=0 ;;
        -h|--help) sed -n '2,20p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

PASSED=0
FAILED=0

pass() { printf '   \033[32m✓\033[0m %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '   \033[31m✗\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
info() { printf '     %s\n' "$1"; }

# $1 description, $2 expected, $3 actual
expect() {
    if [ "$2" = "$3" ]; then
        pass "$1"
    else
        fail "$1 (expected '$2', got '$3')"
    fi
}

cleanup() {
    echo
    echo "Cleaning up..."
    "${DC[@]}" down -v --remove-orphans >/dev/null 2>&1
}
trap cleanup EXIT

echo "Smoke-testing the production image"
echo "  compose: $COMPOSE_FILE"
echo "  port:    $SMOKE_PORT"
echo

# 1. Build ------------------------------------------------------------------
if [ "$BUILD" -eq 1 ]; then
    echo "1. Building the production image..."
    # GITHUB_TOKEN, if .env.deploy carries one, lets Composer fetch signed
    # zipballs rather than the rate-limited anonymous redirect.
    if [ -f .env.deploy ]; then
        set -a; . ./.env.deploy; set +a
    fi
    if APP_DOMAIN=cantores.hu \
       GIT_COMMIT_HASH="$(git rev-parse HEAD 2>/dev/null || echo unknown)" \
       GITHUB_TOKEN="${GITHUB_TOKEN:-}" \
       docker compose -f docker-compose.prod.yml build app; then
        pass "image builds"
    else
        fail "image builds"
        echo
        echo "Build failed — nothing else can be tested."
        exit 1
    fi
else
    echo "1. Skipping build (--no-build)"
    if docker image inspect creshu-app-prod >/dev/null 2>&1; then
        info "using the creshu-app-prod already on this machine"
    else
        fail "creshu-app-prod image not found; run without --no-build"
        exit 1
    fi
fi
echo

# 2. Boot -------------------------------------------------------------------
# --wait blocks on the healthcheck, so reaching the next line means a worker
# booted Laravel and answered /up. It also means the migrator ran migrate, seed
# and optimize without failing.
echo "2. Starting the stack (migrations, seeders, optimize, then the workers)..."
"${DC[@]}" down -v --remove-orphans >/dev/null 2>&1
if "${DC[@]}" up -d --wait >/dev/null 2>&1; then
    pass "stack healthy: migrator completed and the app answers its healthcheck"
else
    fail "stack did not become healthy"
    echo
    echo "--- migrator ---"; "${DC[@]}" logs --no-log-prefix migrator 2>&1 | tail -30
    echo "--- app ---";      "${DC[@]}" logs --no-log-prefix app 2>&1 | tail -30
    exit 1
fi
echo

# 3. The container itself ---------------------------------------------------
echo "3. Container..."

# PID 1 must be php, not the shell that launched it. A shell does not forward
# signals, so if this is 'sh' then docker stop cannot reach Octane and every
# deploy ends in SIGKILL once the grace period expires.
PID1=$("${DC[@]}" exec -T app sh -c 'cat /proc/1/comm' 2>/dev/null | tr -d '\r\n')
expect "PID 1 is php, so SIGTERM reaches Octane" "php" "$PID1"

RUNTIME_UID=$("${DC[@]}" exec -T app sh -c 'id -u' 2>/dev/null | tr -d '\r\n')
expect "server runs unprivileged (uid 33, www-data)" "33" "$RUNTIME_UID"

# Worker mode is the entire point of the change. Without it FrankenPHP still
# serves, and still serves correctly — it just boots Laravel per request, which
# is the cost being removed. Caddy's admin API reports what is actually loaded.
WORKERS=$("${DC[@]}" exec -T app curl -sS http://localhost:2019/config/ 2>/dev/null |
    python3 -c 'import sys,json; print(json.load(sys.stdin)["apps"]["frankenphp"]["workers"][0]["num"])' 2>/dev/null)
expect "worker mode active with $EXPECTED_WORKERS workers" "$EXPECTED_WORKERS" "$WORKERS"
echo

# 4. Responses --------------------------------------------------------------
echo "4. Responses..."
for path in /up / /login; do
    CODE=$(curl -sS -o /dev/null -w '%{http_code}' -L --max-time 30 "$BASE$path" 2>/dev/null)
    expect "GET $path is 200" "200" "$CODE"
done

HEADERS=$(curl -sS -D- -o /dev/null --max-time 30 "$BASE/up" 2>/dev/null)

# frame-ancestors 'none' was a security control on the Apache vhost that went
# away with it; the Caddyfile carries it deliberately. A projection wall and its
# remote are a signed-in session driving a screen in a room and may not be framed.
if grep -qi "^content-security-policy:.*frame-ancestors 'none'" <<<"$HEADERS"; then
    pass "Content-Security-Policy: frame-ancestors 'none' is set"
else
    fail "Content-Security-Policy: frame-ancestors 'none' is missing"
fi

# The counterparts of ServerTokens Prod and Header unset X-Powered-By.
for header in Server X-Powered-By; do
    if grep -qi "^${header}:" <<<"$HEADERS"; then
        fail "$header header is exposed"
    else
        pass "$header header is not exposed"
    fi
done
echo

# 5. State between requests -------------------------------------------------
# The failure a warm worker makes possible is not an error, it is a correct
# looking page carrying somebody else's context. Two signed-in users and a guest
# are interleaved through one pool of workers; each response must belong to the
# session that asked for it.
echo "5. State does not leak between requests..."

RUN_ID=$(date +%s)
SETUP=$("${DC[@]}" exec -T -e SMOKE_RUN_ID="$RUN_ID" app php artisan tinker --execute '
$genres = App\Models\Genre::orderBy("id")->pluck("id")->values();
if ($genres->count() < 2) {
    echo "SETUP_ERROR need at least two genres to tell the two users apart\n";
    return;
}
// The production image has no fakerphp (it is a dev dependency), so the model
// factories cannot run here and the columns are filled directly. The cities and
// first names are created rather than taken from the seeders: a fresh seed
// leaves exactly one of each, and users is unique on (city_id, first_name_id),
// so two users need two distinct pairs.
$run = getenv("SMOKE_RUN_ID");
foreach ([0, 1] as $i) {
    $city = App\Models\City::firstOrCreate(["name" => "Smoke City {$run}-{$i}"]);
    $first = App\Models\FirstName::firstOrCreate(["name" => "Smoke Name {$run}-{$i}"]);
    $email = "smoke-{$i}-{$run}@smoke.test";
    (new App\Models\User)->forceFill([
        "name" => "Smoke User {$i}",
        "email" => $email,
        "password" => Hash::make("smoke-password-123"),
        "city_id" => $city->id,
        "first_name_id" => $first->id,
        "current_genre_id" => $genres[$i],
        "email_verified_at" => now(),
    ])->save();
    echo "SMOKE_USER {$email} {$genres[$i]}\n";
}
' 2>&1)

mapfile -t USER_LINES < <(grep '^SMOKE_USER ' <<<"$SETUP")

if [ "${#USER_LINES[@]}" -ne 2 ]; then
    fail "could not create two test users (seed data changed?)"
    info "$(tail -5 <<<"$SETUP")"
else
    EMAIL_A=$(awk '{print $2}' <<<"${USER_LINES[0]}")
    EMAIL_B=$(awk '{print $2}' <<<"${USER_LINES[1]}")
    GENRE_A=$(awk '{print $3}' <<<"${USER_LINES[0]}")
    GENRE_B=$(awk '{print $3}' <<<"${USER_LINES[1]}")

    JAR_DIR=$(mktemp -d)

    login() { # $1 jar, $2 email
        local jar=$1 email=$2 token
        rm -f "$jar"
        token=$(curl -sS -c "$jar" -b "$jar" --max-time 30 "$BASE/login" 2>/dev/null |
            grep -oP 'name="_token"\s+value="\K[^"]+' | head -1)
        curl -sS -c "$jar" -b "$jar" -o /dev/null --max-time 30 \
            -X POST "$BASE/login" \
            -d "_token=$token" -d "email=$email" -d "password=smoke-password-123" 2>/dev/null
    }

    login "$JAR_DIR/a" "$EMAIL_A"
    login "$JAR_DIR/b" "$EMAIL_B"

    leaks=0
    missing=0
    guest_leaks=0
    for _ in $(seq 1 15); do
        a=$(curl -sS -b "$JAR_DIR/a" -c "$JAR_DIR/a" -L --max-time 30 "$BASE/dashboard" 2>/dev/null)
        grep -q "$EMAIL_A" <<<"$a" || missing=$((missing + 1))
        grep -q "$EMAIL_B" <<<"$a" && leaks=$((leaks + 1))

        b=$(curl -sS -b "$JAR_DIR/b" -c "$JAR_DIR/b" -L --max-time 30 "$BASE/dashboard" 2>/dev/null)
        grep -q "$EMAIL_B" <<<"$b" || missing=$((missing + 1))
        grep -q "$EMAIL_A" <<<"$b" && leaks=$((leaks + 1))

        # A guest immediately behind two signed-in requests must still be a guest.
        g=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$BASE/dashboard" 2>/dev/null)
        [ "$g" = "302" ] || guest_leaks=$((guest_leaks + 1))
    done

    expect "30 interleaved signed-in requests, no cross-session leak" "0" "$leaks"
    expect "every request saw its own session" "0" "$missing"
    expect "15 guest requests stayed unauthenticated" "0" "$guest_leaks"

    # The narrower case the plan names: GenreContext is resolved per request, so
    # two users creating a music plan through one warm pool each get their own
    # genre rather than whichever one the worker saw first.
    create_plan() { # $1 jar
        local jar=$1 token
        local page
        page=$(curl -sS -b "$jar" -c "$jar" --max-time 30 "$BASE/dashboard" 2>/dev/null)
        token=$(grep -oP "csrfToken['\"]?\s*[:=]\s*['\"]\K[^'\"]+" <<<"$page" | head -1)
        [ -z "$token" ] && token=$(grep -oP 'name="csrf-token" content="\K[^"]+' <<<"$page" | head -1)
        [ -z "$token" ] && token=$(grep -oP 'name="_token"\s+value="\K[^"]+' <<<"$page" | head -1)
        curl -sS -b "$jar" -c "$jar" -o /dev/null --max-time 30 \
            -X POST "$BASE/music-plans" -d "_token=$token" 2>/dev/null
    }
    for _ in 1 2 3; do
        create_plan "$JAR_DIR/a"
        create_plan "$JAR_DIR/b"
    done

    WRONG=$("${DC[@]}" exec -T database psql -U cantor -d cantores -tAc "
        select count(*) from music_plans mp
        join users u on u.id = mp.user_id
        where u.email in ('$EMAIL_A','$EMAIL_B')
          and mp.genre_id is distinct from u.current_genre_id;" 2>/dev/null | tr -d '\r\n ')
    PLANS=$("${DC[@]}" exec -T database psql -U cantor -d cantores -tAc "
        select count(*) from music_plans mp
        join users u on u.id = mp.user_id
        where u.email in ('$EMAIL_A','$EMAIL_B');" 2>/dev/null | tr -d '\r\n ')

    expect "music plans stored with their own author's genre ($PLANS created, genres $GENRE_A/$GENRE_B)" "0" "$WRONG"

    rm -rf "$JAR_DIR"
fi
echo

# 6. Latency ----------------------------------------------------------------
# Reported, not asserted: the number depends on the machine. What it is here for
# is the gap between the first request and the rest, which is the framework boot
# that worker mode removes.
echo "6. Warm latency (reported, not asserted)..."
for _ in $(seq 1 40); do curl -sS -o /dev/null --max-time 30 "$BASE/up" 2>/dev/null; done
"${DC[@]}" logs --no-log-prefix app 2>/dev/null |
    grep '"logger":"http.log.access' | tail -40 |
    python3 -c '
import sys, json
d = sorted(json.loads(l)["duration"] * 1000 for l in sys.stdin if l.strip())
if d:
    print(f"     /up over {len(d)} warm requests: median {d[len(d)//2]:.2f} ms, p95 {d[int(len(d)*0.95)]:.2f} ms, max {d[-1]:.2f} ms")
' 2>/dev/null || info "could not parse the access log"
echo

# 7. The renderer image ------------------------------------------------------
# The renderer is a second image built from a second Dockerfile, and nothing
# else here touches it. Its MuseScore install is the part most likely to break
# silently: the AppImage is extracted in a build stage of its own so the 192 MB
# download survives the cache, and the Qt platform libraries it links against
# are installed separately in the final stage. A version bump, a renamed Debian
# package or a stale cache entry all end the same way — an image that boots, a
# queue that accepts jobs, and every render failing. Rendering a score is the
# only thing that tells them apart.
#
# The container is given what docker-compose.prod.yml gives the real one: uid
# 33, a tmpfs /tmp, and the same process and memory caps. Those caps are part of
# what is being tested — Qt under xvfb has to fit inside them.
echo "7. Renderer image (MuseScore)..."
if ! docker image inspect creshu-musescore-prod >/dev/null 2>&1; then
    if [ "$BUILD" -eq 1 ]; then
        info "building creshu-musescore-prod..."
        if ! docker build -q -f Dockerfile.musescore -t creshu-musescore-prod:latest . >/dev/null; then
            fail "renderer image builds"
        fi
    else
        info "creshu-musescore-prod not on this machine — skipping (--no-build)"
    fi
fi

if docker image inspect creshu-musescore-prod >/dev/null 2>&1; then
    RENDER_OUT=$(docker run --rm \
        --user 33:33 \
        --tmpfs /tmp:size=512m,mode=1777 \
        --pids-limit 256 \
        --memory 1g \
        --entrypoint /bin/sh \
        creshu-musescore-prod:latest -c '
            cat > /tmp/in.musicxml <<'"'"'XML'"'"'
<?xml version="1.0" encoding="UTF-8"?>
<score-partwise version="3.1">
  <part-list><score-part id="P1"><part-name>Smoke</part-name></score-part></part-list>
  <part id="P1"><measure number="1">
    <attributes><divisions>1</divisions><key><fifths>0</fifths></key>
      <time><beats>4</beats><beat-type>4</beat-type></time>
      <clef><sign>G</sign><line>2</line></clef></attributes>
    <note><pitch><step>C</step><octave>4</octave></pitch><duration>4</duration><type>whole</type></note>
  </measure></part>
</score-partwise>
XML
            mscore-render -o /tmp/out.pdf /tmp/in.musicxml >/dev/null 2>&1 || { echo "RENDER_FAILED"; exit 1; }
            pdftocairo -svg /tmp/out.pdf /tmp/out.svg >/dev/null 2>&1 || { echo "PDFTOCAIRO_FAILED"; exit 1; }
            pdftoppm -png -r 72 /tmp/out.pdf /tmp/page >/dev/null 2>&1 || { echo "PDFTOPPM_FAILED"; exit 1; }
            echo "PDF_BYTES $(stat -c%s /tmp/out.pdf)"
            echo "SVG_BYTES $(stat -c%s /tmp/out.svg)"
            echo "PNG_COUNT $(ls /tmp/page*.png 2>/dev/null | wc -l)"
            mscore-render --version 2>/dev/null | grep -o "MuseScore[0-9]* [0-9.]*" | tail -1
        ' 2>&1)

    PDF_BYTES=$(grep '^PDF_BYTES ' <<<"$RENDER_OUT" | awk '{print $2}')
    SVG_BYTES=$(grep '^SVG_BYTES ' <<<"$RENDER_OUT" | awk '{print $2}')
    PNG_COUNT=$(grep '^PNG_COUNT ' <<<"$RENDER_OUT" | awk '{print $2}')

    # Byte counts rather than exit status: MuseScore exits 0 having written
    # nothing when its command line is parsed the way it was not meant to be,
    # which is the failure the wrapper's comment describes.
    if [ "${PDF_BYTES:-0}" -gt 1000 ] 2>/dev/null; then
        pass "MuseScore renders MusicXML to PDF ($(numfmt --to=iec "$PDF_BYTES" 2>/dev/null || echo "$PDF_BYTES B"))"
        info "$(grep -o 'MuseScore[0-9]* [0-9.]*' <<<"$RENDER_OUT" | tail -1)"
    else
        fail "MuseScore renders MusicXML to PDF"
        info "$(tail -5 <<<"$RENDER_OUT")"
    fi

    if [ "${SVG_BYTES:-0}" -gt 500 ] 2>/dev/null; then
        pass "pdftocairo turns the PDF into an SVG engraving"
    else
        fail "pdftocairo turns the PDF into an SVG engraving"
    fi

    expect "pdftoppm turns the PDF into a page image" "1" "${PNG_COUNT:-0}"
fi
echo

# 8. Shutdown ---------------------------------------------------------------
# Last, because it stops the server. A clean exit means Octane received the
# SIGTERM and stopped its workers; 137 means the grace period expired and Docker
# killed the container with requests still in flight.
echo "8. Graceful shutdown..."
START=$(date +%s.%N)
"${DC[@]}" stop app >/dev/null 2>&1
ELAPSED=$(echo "$(date +%s.%N) - $START" | bc)
EXIT_CODE=$("${DC[@]}" ps -a --format json app 2>/dev/null |
    python3 -c '
import sys, json
d = sys.stdin.read().strip()
o = json.loads(d if d.startswith("[") else d.splitlines()[0])
print((o[0] if isinstance(o, list) else o)["ExitCode"])
' 2>/dev/null)

expect "app exits 0 on SIGTERM (137 would mean it was killed)" "0" "$EXIT_CODE"
if [ "$(echo "$ELAPSED < 15" | bc)" = "1" ]; then
    pass "$(printf 'stopped in %.1fs, inside the 30s grace period' "$ELAPSED")"
else
    fail "$(printf 'took %.1fs to stop; the grace period is being waited out' "$ELAPSED")"
fi
echo

# Result --------------------------------------------------------------------
echo "─────────────────────────────────────────────"
if [ "$FAILED" -eq 0 ]; then
    printf '\033[32m%s checks passed.\033[0m The image is fit to deploy.\n' "$PASSED"
    exit 0
else
    printf '\033[31m%s of %s checks failed.\033[0m Do not deploy this image.\n' "$FAILED" "$((PASSED + FAILED))"
    exit 1
fi
