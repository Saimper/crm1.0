#!/usr/bin/env bash
#
# Trinquete de fugas multi-mandante.
#
# Ejecuta el grupo `fuga-pendiente` —los tests que documentan agujeros de
# aislamiento entre mandantes y que `phpunit.xml` excluye del run normal— y
# compara el número de fallos con el que hay anotado en
# tests/fugas-pendientes.baseline.
#
# Rompe SÓLO si el número sube. Así el CI ve las fugas sin bloquear el trabajo
# del día, y una regresión que abra un agujero nuevo se para en el acto.
#
# Uso:  bin/verificar-fugas.sh
#
set -uo pipefail

cd "$(dirname "$0")/.."

BASELINE_FILE="tests/fugas-pendientes.baseline"

if [[ ! -f "$BASELINE_FILE" ]]; then
    echo "::error::No existe $BASELINE_FILE. Sin baseline no hay trinquete."
    exit 1
fi

# El baseline es la primera línea que sea sólo dígitos; el resto es explicación.
ESPERADOS=$(grep -m1 -E '^[0-9]+$' "$BASELINE_FILE" || true)

if [[ -z "$ESPERADOS" ]]; then
    echo "::error::$BASELINE_FILE no contiene un número. Debe tener una línea con sólo dígitos."
    exit 1
fi

echo "Ejecutando el grupo fuga-pendiente…"
SALIDA=$(php artisan test --group=fuga-pendiente 2>&1)
echo "$SALIDA"

# La línea de resumen de PHPUnit: "Tests:  23 failed, 2 passed (51 assertions)".
# Si no aparece, algo se rompió antes de correr y no se puede afirmar nada.
RESUMEN=$(echo "$SALIDA" | grep -E '^\s*Tests:' | tail -1)

if [[ -z "$RESUMEN" ]]; then
    echo "::error::El grupo no llegó a ejecutarse (sin línea de resumen). Revisa la salida de arriba."
    exit 1
fi

ACTUALES=$(echo "$RESUMEN" | grep -oE '[0-9]+ failed' | grep -oE '[0-9]+' || echo "0")

echo
echo "──────────────────────────────────────────────"
echo "  Fugas conocidas (baseline) : $ESPERADOS"
echo "  Fugas ahora mismo          : $ACTUALES"
echo "──────────────────────────────────────────────"

# Resumen en la pestaña Actions, cuando corre en GitHub.
if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
    {
        echo "### Fugas multi-mandante"
        echo
        echo "| | |"
        echo "|---|---|"
        echo "| Conocidas (baseline) | **$ESPERADOS** |"
        echo "| Ahora mismo | **$ACTUALES** |"
        echo
        echo '```'
        echo "$SALIDA" | grep -E '^\s*(✓|⨯)' | sed 's/^[[:space:]]*//' || true
        echo '```'
    } >> "$GITHUB_STEP_SUMMARY"
fi

if (( ACTUALES > ESPERADOS )); then
    echo "::error::Se han abierto $((ACTUALES - ESPERADOS)) fuga(s) nueva(s) entre mandantes."
    echo "Este cambio hace que datos de un cliente sean alcanzables desde otro."
    echo "Arréglalo, o —si de verdad es intencionado— sube el número de $BASELINE_FILE y explica por qué en el PR."
    exit 1
fi

if (( ACTUALES < ESPERADOS )); then
    echo "✅ Se han cerrado $((ESPERADOS - ACTUALES)) fuga(s). Baja el número de $BASELINE_FILE a $ACTUALES para que no puedan reabrirse."
    exit 0
fi

echo "✅ Sin fugas nuevas. Siguen abiertas las $ESPERADOS conocidas."
exit 0
