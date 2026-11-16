<?php
/**
 * Script de preparação para o GitHub Actions (CI)
 * Como o projeto referencia `../libs/config.php`, `../libs/email.php` e `../database/conexao.php`
 * (que ficam um nível acima da raiz do repositório), este script cria esses arquivos
 * automaticamente no diretório pai do runner de CI caso ainda não existam.
 */

$projectRoot = dirname(__DIR__, 2);
$parentDir   = dirname($projectRoot);

$libsDir = $parentDir . DIRECTORY_SEPARATOR . 'libs';
$dbDir   = $parentDir . DIRECTORY_SEPARATOR . 'database';

if (!is_dir($libsDir)) {
    mkdir($libsDir, 0755, true);
}
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$conexaoPath = $dbDir . DIRECTORY_SEPARATOR . 'conexao.php';
if (!file_exists($conexaoPath)) {
    $conexaoCode = <<<'PHP'
<?php
$host   = getenv('DB_HOST') ?: '127.0.0.1';
$port   = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'sistema_adrenalina';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados de teste.");
}
PHP;
    file_put_contents($conexaoPath, $conexaoCode);
    echo "[CI Setup] Criado: {$conexaoPath}\n";
}

$configPath = $libsDir . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($configPath)) {
    $configCode = <<<'PHP'
<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../database/conexao.php';

function extrairDominioBase($host)
{
    $hostLimpo = explode(':', $host)[0];
    $parts = explode('.', $hostLimpo);
    $count = count($parts);
    if ($count >= 4 && $parts[$count - 1] === 'br') {
        return $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
    } elseif ($count >= 3 && $parts[$count - 1] === 'br') {
        return $parts[$count - 3] . '.' . $parts[$count - 2] . '.' . $parts[$count - 1];
    } elseif ($count >= 2) {
        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }
    return $hostLimpo;
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = ($host === 'localhost' || strpos($host, '127.0.0.1') !== false);
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$cookieDomain = $isLocalhost ? '' : '.' . extrairDominioBase($host);
$caminhoSessao = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'adrenalina_sessions_ci';

if (!file_exists($caminhoSessao)) {
    @mkdir($caminhoSessao, 0700, true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_save_path($caminhoSessao);
    session_name('SESSAO_ADRENALINA_SSO');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['user_id']) && !isset($_SESSION['aluno_id'])) {
    $_SESSION['aluno_id'] = $_SESSION['user_id'];
}
if (isset($_SESSION['aluno_id']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['aluno_id'];
}

$slug_detectado = 'bm';
try {
    $stmt = $pdo->prepare("SELECT * FROM config_escolas WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug_detectado]);
    $escola = $stmt->fetch(PDO::FETCH_ASSOC);

    define('ID_ESCOLA_ATUAL', $escola['id'] ?? 1);
    define('SLUG_ESCOLA', $escola['slug'] ?? 'bm');
    define('NOME_ESCOLA', $escola['nome_display'] ?? 'Bombeiro Militar');
    define('FAMILIA_ESCOLA_ATUAL', $escola['familia_sessao'] ?? 'contexto');
} catch (Exception $e) {
    define('ID_ESCOLA_ATUAL', 1);
    define('SLUG_ESCOLA', 'bm');
    define('NOME_ESCOLA', 'Bombeiro Militar');
    define('FAMILIA_ESCOLA_ATUAL', 'contexto');
}

define('URL_HUB', '/public_html/meus_cursos.php');
define('URL_LOGIN', '/public_html/login-hub.php');
PHP;
    file_put_contents($configPath, $configCode);
    echo "[CI Setup] Criado: {$configPath}\n";
}

$emailPath = $libsDir . DIRECTORY_SEPARATOR . 'email.php';
if (!file_exists($emailPath)) {
    $emailCode = <<<'PHP'
<?php
function enviarEmailBoasVindas($nomeAluno, $emailAluno, $senhaTemporaria = null, $configEscola = null) {
    return true;
}
function enviarEmailRenovacao($nomeAluno, $emailAluno, $novaDataExpiracao, $configEscola = null) {
    return true;
}
function enviarEmailRecuperacaoSenha($nomeAluno, $emailAluno, $linkReset, $configEscola = null) {
    return true;
}
PHP;
    file_put_contents($emailPath, $emailCode);
    echo "[CI Setup] Criado: {$emailPath}\n";
}

echo "[CI Setup] Ambiente pai (../libs e ../database) pronto.\n";
