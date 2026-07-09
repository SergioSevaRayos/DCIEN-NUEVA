#!/usr/bin/env bash
# DCIEN - Script de Deploy para Linux
# Uso: ./deploy.sh [-all] [-backend] [-frontend] [-extras] [-build]

SERVER="u755459505@147.79.103.7"
PORT="65002"
REMOTE="/home/u755459505/domains/d-cien.es"
LOCAL="$(cd "$(dirname "$0")" && pwd)"

GREEN='\033[0;32m'; CYAN='\033[0;36m'; RED='\033[0;31m'; NC='\033[0m'
step()  { echo -e "\n${CYAN}>> $1${NC}"; }
ok()    { echo -e "${GREEN}OK $1${NC}"; }
err()   { echo -e "${RED}ERROR: $1${NC}"; }

DO_ALL=0; DO_BACKEND=0; DO_FRONTEND=0; DO_EXTRAS=0; DO_BUILD=0

for arg in "$@"; do
  case "$arg" in
    -all)      DO_ALL=1 ;;
    -backend)  DO_BACKEND=1 ;;
    -frontend) DO_FRONTEND=1 ;;
    -extras)   DO_EXTRAS=1 ;;
    -build)    DO_BUILD=1 ;;
  esac
done

if [[ $DO_ALL -eq 0 && $DO_BACKEND -eq 0 && $DO_FRONTEND -eq 0 && $DO_EXTRAS -eq 0 && $DO_BUILD -eq 0 ]]; then
  echo -e "\nDCIEN Deploy Script"
  echo "-------------------"
  echo "Uso:"
  echo "  ./deploy.sh -all          Subir todo (build + backend + extras)"
  echo "  ./deploy.sh -build        Solo hacer npm run build"
  echo "  ./deploy.sh -frontend     Subir solo el build de Astro (dist/)"
  echo "  ./deploy.sh -backend      Subir solo el backend PHP"
  echo "  ./deploy.sh -extras       Subir solo los extras (carrito, api, admin, js, bono)"
  exit 0
fi

# STEP 1: Build de Astro
if [[ $DO_BUILD -eq 1 || $DO_ALL -eq 1 ]]; then
  step "Ejecutando npm run build..."
  cd "$LOCAL" && npm run build
  if [[ $? -ne 0 ]]; then
    err "El build de Astro falló. Abortando."
    exit 1
  fi
  ok "Build completado"
fi

# STEP 2: Subir frontend (dist/)
if [[ $DO_FRONTEND -eq 1 || $DO_ALL -eq 1 ]]; then
  step "Subiendo frontend (dist/) al servidor..."
  scp -P "$PORT" -r "$LOCAL/dist/." "${SERVER}:${REMOTE}/public_html/"
  [[ $? -eq 0 ]] && ok "Frontend subido" || err "Falló al subir frontend"

  step "Subiendo imágenes estáticas (public/images/)..."
  scp -P "$PORT" -r "$LOCAL/public/images" "${SERVER}:${REMOTE}/public_html/"
  [[ $? -eq 0 ]] && ok "Imágenes subidas" || err "Falló al subir imágenes (no crítico si ya estaban)"
fi

# STEP 3: Subir backend (NUNCA sube .env ni vendor)
if [[ $DO_BACKEND -eq 1 || $DO_ALL -eq 1 ]]; then
  step "Subiendo backend PHP al servidor..."
  rsync -avz \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='vendor/' \
    --exclude='*.log' \
    -e "ssh -p $PORT" \
    "$LOCAL/dcien-backend/" \
    "${SERVER}:${REMOTE}/dcien-backend/"
  [[ $? -eq 0 ]] && ok "Backend subido (.env y vendor del servidor intactos)" || err "Falló al subir backend"
fi

# STEP 4: Subir server-extras y admin-descargas
if [[ $DO_EXTRAS -eq 1 || $DO_ALL -eq 1 ]]; then
  step "Subiendo server-extras (carrito, api, admin, js, bono)..."
  scp -P "$PORT" -r "$LOCAL/server-extras/." "${SERVER}:${REMOTE}/public_html/"
  [[ $? -eq 0 ]] && ok "server-extras subido" || err "Falló al subir server-extras"

  step "Subiendo admin-descargas a public_html..."
  scp -P "$PORT" -r "$LOCAL/dcien-backend/admin-descargas" "${SERVER}:${REMOTE}/public_html/"
  [[ $? -eq 0 ]] && ok "admin-descargas subido" || err "Falló al subir admin-descargas"
fi

# STEP FINAL: Corregir permisos
if [[ $DO_FRONTEND -eq 1 || $DO_EXTRAS -eq 1 || $DO_ALL -eq 1 ]]; then
  step "Corrigiendo permisos en public_html (chmod 755/644)..."
  ssh -p "$PORT" "$SERVER" \
    "find ${REMOTE}/public_html/ -type d -exec chmod 755 {} \; ; find ${REMOTE}/public_html/ -type f -exec chmod 644 {} \;"
  [[ $? -eq 0 ]] && ok "Permisos corregidos (dirs 755, files 644)" || err "No se pudieron corregir permisos"
fi

echo -e "\n${GREEN}Deploy completado. Verifica en https://d-cien.es${NC}\n"
