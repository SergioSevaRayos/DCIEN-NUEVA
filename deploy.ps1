# ============================================================
# DCIEN - Script de Deploy
# Uso: .\deploy.ps1 [-all] [-backend] [-frontend] [-extras]
# ============================================================

param(
    [switch]$all,
    [switch]$backend,
    [switch]$frontend,
    [switch]$extras,
    [switch]$build
)

$SERVER     = "u755459505@147.79.103.7"
$PORT       = "65002"
$REMOTE     = "/home/u755459505/domains/d-cien.es"
$LOCAL      = "C:\Users\Trending Pc\Documents\DCIEN\DCIEN-nueva"

function Write-Step($msg) {
    Write-Host "`n>> $msg" -ForegroundColor Cyan
}

function Write-OK($msg) {
    Write-Host "OK $msg" -ForegroundColor Green
}

function Write-Err($msg) {
    Write-Host "ERROR: $msg" -ForegroundColor Red
}

# Si no se pasa ningun parametro mostrar ayuda
if (-not ($all -or $backend -or $frontend -or $extras -or $build)) {
    Write-Host @"

DCIEN Deploy Script
-------------------
Uso:
  .\deploy.ps1 -all          Subir todo (build + backend + extras)
  .\deploy.ps1 -build        Solo hacer npm run build
  .\deploy.ps1 -frontend     Subir solo el build de Astro (dist/)
  .\deploy.ps1 -backend      Subir solo el backend PHP
  .\deploy.ps1 -extras       Subir solo los extras (carrito, api, admin, etc)
  .\deploy.ps1 -all -build   Build + subir todo

"@ -ForegroundColor Yellow
    exit
}

# STEP 1: Build de Astro si se pide
if ($build -or $all) {
    Write-Step "Ejecutando npm run build..."
    Set-Location $LOCAL
    npm run build
    if ($LASTEXITCODE -ne 0) {
        Write-Err "El build de Astro fallo. Abortando."
        exit 1
    }
    Write-OK "Build completado"
}

# STEP 2: Subir frontend (dist/)
if ($frontend -or $all) {
    Write-Step "Subiendo frontend (dist/) al servidor..."
    scp -P $PORT -r "$LOCAL\dist\*" "${SERVER}:${REMOTE}/public_html/"
    if ($LASTEXITCODE -eq 0) {
        Write-OK "Frontend subido"
    } else {
        Write-Err "Fallo al subir frontend"
    }

    # Las imagenes de public/ son grandes y a veces no llegan via dist/
    # Se suben explicitamente para garantizar que esten en el servidor
    Write-Step "Subiendo imagenes estaticas (public/images/)..."
    scp -P $PORT -r "$LOCAL\public\images" "${SERVER}:${REMOTE}/public_html/"
    if ($LASTEXITCODE -eq 0) {
        Write-OK "Imagenes subidas"
    } else {
        Write-Err "Fallo al subir imagenes (no critico si ya estaban)"
    }
}

# STEP 3: Subir backend (NUNCA sube .env ni vendor al servidor)
if ($backend -or $all) {
    Write-Step "Subiendo backend PHP al servidor..."

    # Staging temporal: copia backend sin .env ni vendor (que viven solo en el servidor)
    $staging = "$env:TEMP\dcien-backend-deploy"
    if (Test-Path $staging) { Remove-Item -Recurse -Force $staging }

    robocopy "$LOCAL\dcien-backend" $staging /E /XF ".env" ".env.*" "*.log" /XD "vendor" /NFL /NDL /NJH /NJS | Out-Null

    scp -P $PORT -r "$staging\*" "${SERVER}:${REMOTE}/dcien-backend/"
    $scpOk = $LASTEXITCODE -eq 0

    Remove-Item -Recurse -Force $staging

    if ($scpOk) {
        Write-OK "Backend subido (.env y vendor del servidor intactos)"
    } else {
        Write-Err "Fallo al subir backend"
    }
}

# STEP 4: Subir server-extras y admin-descargas
if ($extras -or $all) {
    Write-Step "Subiendo server-extras (carrito, api, admin, js, bono)..."
    scp -P $PORT -r "$LOCAL\server-extras\*" "${SERVER}:${REMOTE}/public_html/"
    $scpExtrasOk = $LASTEXITCODE -eq 0

    Write-Step "Subiendo admin-descargas (desde dcien-backend) a public_html..."
    scp -P $PORT -r "$LOCAL\dcien-backend\admin-descargas" "${SERVER}:${REMOTE}/public_html/"
    $scpAdminOk = $LASTEXITCODE -eq 0

    if ($scpExtrasOk -and $scpAdminOk) {
        Write-OK "Extras y admin-descargas subidos"
    } else {
        Write-Err "Fallo al subir extras"
    }
}

# STEP FINAL: Corregir permisos en public_html (SCP sube con 700/600 por defecto)
# Esto evita el 403 Forbidden recurrente tras cada deploy
if ($frontend -or $extras -or $all) {
    Write-Step "Corrigiendo permisos en public_html (chmod 755/644)..."
    $sshCmd = "find ${REMOTE}/public_html/ -type d -exec chmod 755 {} \; ; find ${REMOTE}/public_html/ -type f -exec chmod 644 {} \;"
    ssh -p $PORT $SERVER $sshCmd
    if ($LASTEXITCODE -eq 0) {
        Write-OK "Permisos corregidos (dirs 755, files 644)"
    } else {
        Write-Err "No se pudieron corregir permisos. Ejecuta manualmente en SSH."
    }
}

Write-Host "`n Deploy completado. Verifica en https://d-cien.es`n" -ForegroundColor Green
