#!/bin/bash
# =============================================================================
# prepare-node.sh — Instala Node.js LTS + npm en el servidor Mi Server.
#
# Uso (como root):
#   bash /home/miserver/panel/install/prepare-node.sh
#
# Tambien refresca /usr/local/sbin/miserver-ctl para que el despliegue de
# proyectos Vue/Node este disponible.
# =============================================================================
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Debes ejecutar este script como root." >&2; exit 1; }

PANEL_DIR="/home/miserver/panel"
WRAPPER_SRC="${PANEL_DIR}/install/miserver-ctl"
WRAPPER_DST="/usr/local/sbin/miserver-ctl"

log(){ echo "==> $*"; }

# -----------------------------------------------------------------------------
log "Instalando Node.js LTS y npm..."
# -----------------------------------------------------------------------------
if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  # NodeSource setup_20.x es compatible con Ubuntu 20.04/22.04/24.04.
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y --no-install-recommends nodejs
else
  echo "   -> Node ya instalado: $(node -v) / npm $(npm -v)"
fi

# Asegura que los comandos esten disponibles.
if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: node o npm no se instalaron correctamente." >&2
  exit 1
fi

echo "   -> Node $(node -v) / npm $(npm -v) instalados"

# -----------------------------------------------------------------------------
log "Refrescando wrapper miserver-ctl..."
# -----------------------------------------------------------------------------
if [ -f "$WRAPPER_SRC" ]; then
  install -m 0755 "$WRAPPER_SRC" "$WRAPPER_DST"
  echo "   -> wrapper actualizado: $WRAPPER_DST"
else
  echo "   ATENCION: no se encontro $WRAPPER_SRC (verifica la ruta del panel)" >&2
fi

# -----------------------------------------------------------------------------
log "Verificando deteccion de proyectos Vue..."
# -----------------------------------------------------------------------------
if grep -q 'type=vue' "$WRAPPER_DST" 2>/dev/null; then
  echo "   -> soporte Vue detectado en el wrapper"
else
  echo "   ATENCION: el wrapper no parece tener soporte Vue todavia" >&2
fi

log "Listo. Ya puedes desplegar aplicaciones Vue/Node desde el panel."
