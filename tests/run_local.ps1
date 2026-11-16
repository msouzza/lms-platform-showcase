param(
    [string]$Suite = "all"
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path $PSScriptRoot -Parent
$folderName = Split-Path $projectRoot -Leaf
$exitCode = 0

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ">>> BATERIA COMPLETA DE TESTES DA PLATAFORMA LMS" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

# 1. TESTES DE FRONTEND (Vitest - DOM & Destaques)
if ($Suite -eq "all" -or $Suite -eq "frontend") {
    Write-Host ""
    Write-Host "--- [1/3] Executando Testes de Frontend (Vitest)..." -ForegroundColor Yellow
    & npx vitest run
    if ($LASTEXITCODE -ne 0) {
        $exitCode = $LASTEXITCODE
        Write-Host "[-] Falha nos testes de frontend!" -ForegroundColor Red
        if ($Suite -eq "frontend") { exit $exitCode }
    }
}

# 2. LINT DE SINTAXE PHP
if ($Suite -eq "all" -or $Suite -eq "lint") {
    Write-Host ""
    Write-Host "--- [2/3] Verificando Sintaxe PHP (Lint)..." -ForegroundColor Yellow
    $lintScript = Join-Path (Join-Path $PSScriptRoot "ci") "lint.php"
    & "C:\xampp\php\php.exe" $lintScript
    if ($LASTEXITCODE -ne 0) {
        $exitCode = $LASTEXITCODE
        Write-Host "[-] Erro de sintaxe PHP detectado!" -ForegroundColor Red
    }
}

# 3. TESTES DE BACKEND PHP (Unit + Integration + SecurityAudit)
if ($Suite -ne "frontend" -and $Suite -ne "lint") {
    Write-Host ""
    Write-Host "--- [3/3] Executando Testes de Backend (Unit + Integracao + AppSec)..." -ForegroundColor Yellow

    $testDataDir = Join-Path $env:TEMP "adrenalina_mariadb_test_data"
    $portActive = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
    $startedTempMysql = $false
    $proc = $null

    if (-not $portActive) {
        Write-Host "INFO: MySQL do XAMPP nao esta ativo na porta 3306. Subindo MariaDB temporario..." -ForegroundColor Cyan
        if (-not (Test-Path (Join-Path $testDataDir "mysql"))) {
            New-Item -ItemType Directory -Force -Path $testDataDir | Out-Null
            & "C:\xampp\mysql\bin\mysql_install_db.exe" --datadir="$testDataDir" | Out-Null
        }
        $proc = Start-Process -FilePath "C:\xampp\mysql\bin\mysqld.exe" `
            -ArgumentList "--no-defaults --basedir=C:/xampp/mysql --datadir=$testDataDir --port=3306" `
            -PassThru -WindowStyle Hidden
        $startedTempMysql = $true
        Start-Sleep -Seconds 3
    }

    $apacheActive = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
    $startedTempApache = $false
    $apacheProc = $null

    if (-not $apacheActive -and (Test-Path "C:\xampp\apache\bin\httpd.exe")) {
        Write-Host "INFO: Apache do XAMPP nao esta ativo na porta 80. Subindo Apache temporario..." -ForegroundColor Cyan
        $apacheProc = Start-Process -FilePath "C:\xampp\apache\bin\httpd.exe" -PassThru -WindowStyle Hidden
        $startedTempApache = $true
        Start-Sleep -Seconds 2
    }

    try {
        $env:APP_TEST_URL = "http://localhost/$folderName"
        $runTestsScript = Join-Path $PSScriptRoot "run_tests.php"
        & "C:\xampp\php\php.exe" $runTestsScript $Suite
        if ($LASTEXITCODE -ne 0) {
            $exitCode = $LASTEXITCODE
        }
    } finally {
        if ($startedTempMysql -and $proc) {
            Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
        }
        if ($startedTempApache -and $apacheProc) {
            Stop-Process -Id $apacheProc.Id -Force -ErrorAction SilentlyContinue
        }
    }
}

exit $exitCode
