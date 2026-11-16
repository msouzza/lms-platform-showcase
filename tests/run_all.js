/**
 * Cross-Platform Test Runner para Plataforma LMS
 * Executa todos os testes unificados com um único comando `npm test`.
 * No Windows: executa PowerShell com ambiente XAMPP e MySQL gerenciado.
 * No Linux/CI: executa Vitest + PHP Lint + Testes PHP sem falhar por dependência de shell.
 */
const { spawnSync } = require('child_process');
const path = require('path');
const os = require('os');

const isWin = os.platform() === 'win32';
const suite = process.argv[2] || 'all';

console.log('\n============================================================');
console.log('>>> EXECUTANDO TESTES UNIFICADOS DA PLATAFORMA LMS');
console.log('============================================================\n');

if (isWin) {
    // Windows: Executa o runner PowerShell que sobe MySQL se necessário e roda 100% dos testes
    const psScript = path.resolve(__dirname, 'run_local.ps1');
    const result = spawnSync('powershell', ['-ExecutionPolicy', 'Bypass', '-File', `"${psScript}"`, suite], {
        stdio: 'inherit',
        shell: true
    });
    process.exit(result.status !== null ? result.status : 0);
} else {
    // Linux / GitHub Actions / macOS: Runner Multiplataforma
    let hasError = false;

    // 1. Frontend (Vitest)
    if (suite === 'all' || suite === 'frontend') {
        console.log('--- [1/3] Executando Testes de Frontend (Vitest)...');
        const vitest = spawnSync('npx', ['vitest', 'run'], { stdio: 'inherit', shell: true });
        if (vitest.status !== 0) {
            hasError = true;
            if (suite === 'frontend') process.exit(vitest.status || 1);
        }
    }

    // 2. PHP Lint
    if (suite === 'all' || suite === 'lint') {
        console.log('\n--- [2/3] Verificando Sintaxe PHP (Lint)...');
        const lintScript = path.resolve(__dirname, 'ci/lint.php');
        const lint = spawnSync('php', [lintScript], { stdio: 'inherit', shell: true });
        if (lint.status !== 0) {
            hasError = true;
        }
    }

    // 3. Backend PHP (Unit + Integration + Security)
    if (suite !== 'frontend' && suite !== 'lint') {
        console.log('\n--- [3/3] Executando Testes de Backend (Unit + Integração + AppSec)...');
        const runTests = path.resolve(__dirname, 'run_tests.php');
        const php = spawnSync('php', [runTests, suite], { stdio: 'inherit', shell: true });
        if (php.status !== 0) {
            hasError = true;
        }
    }

    process.exit(hasError ? 1 : 0);
}
