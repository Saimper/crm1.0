#!/usr/bin/env bash
# Inventario de estilos en línea por vista.
#
# Sin argumentos, imprime el recuento actual. Con --actualizar, lo escribe en
# tests/estilos-en-linea.baseline, que es lo que vigila EstilosEnLineaTest: el
# número de cada vista puede bajar, nunca subir.
#
# Se actualiza a mano y a propósito: cada bajada es una vista migrada al
# sistema de diseño, y verla en el diff es la mitad del valor.
set -uo pipefail
cd "$(dirname "$0")/.."

inventario() {
    # Ocurrencias, no líneas: una línea puede llevar tres `style`, y el test
    # cuenta ocurrencias. Si los dos no cuentan lo mismo, el trinquete miente.
    find resources/views -name '*.blade.php' -print0 \
        | xargs -0 grep -o 'style="' \
        | awk -F: '{ n[$1]++ } END { for (v in n) print n[v], v }' \
        | sort -k2
}

if [[ "${1:-}" == "--actualizar" ]]; then
    inventario > tests/estilos-en-linea.baseline
    echo "Baseline actualizado: $(grep -c '' tests/estilos-en-linea.baseline) vistas, $(awk '{s+=$1} END {print s}' tests/estilos-en-linea.baseline) estilos en línea."
else
    inventario
fi
