<?php
/**
 * Bootstrap da suíte de testes automatizados (PHPUnit & Runner CLI)
 */

date_default_timezone_set('America/Sao_Paulo');

// Autoload universal para App\\ e Tests\\ (compatível com ou sem vendor/autoload.php)
spl_autoload_register(function (string $class) {
    if (strncmp('App\\', $class, 4) === 0) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    if (strncmp('Tests\\', $class, 6) === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

if (!getenv('APP_TEST_URL')) {
    $folder = basename(dirname(__DIR__));
    putenv("APP_TEST_URL=http://localhost/{$folder}");
}


// Configura conexão PDO para as fixtures de teste
function getTestPdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host   = getenv('DB_HOST') ?: '127.0.0.1';
    $port   = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'sistema_adrenalina';
    $user   = getenv('DB_USER') ?: 'root';
    $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    // Conecta primeiro sem dbname para garantir que o banco exista no CI
    $pdoInit = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Executa o schema.sql (CREATE TABLE IF NOT EXISTS) para garantir todas as tabelas
    $schemaSql = file_get_contents(__DIR__ . '/fixtures/schema.sql');
    $pdo->exec($schemaSql);

    return $pdo;
}
