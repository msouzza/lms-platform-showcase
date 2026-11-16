<?php
/**
 * Router para o Servidor Embutido do PHP (php -S)
 * Simula as regras do .htaccess (Apache) em ambientes de CI/CD onde o Apache não está instalado.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . '/../../' . ltrim($uri, '/');

// 1. Bloqueia acesso direto aos HTMLs dentro de components/ (simula RewriteRule ^components/.*\.(html|htm)$ - [F])
if (preg_match('#^/components/.*?\.(html|htm)$#i', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('403 Forbidden: Acesso direto ao conteúdo restrito.');
}

// 2. Bloqueia arquivos de debug, backup e configuração sensível
if (preg_match('#(copy\.php|integrar-anotacoes\.js|validar_ambiente\.php|teste-acesso-admin\.html|teste\.html|composer\.(json|lock)|phpunit\.xml)$#i', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('403 Forbidden: Arquivo protegido.');
}

// 3. Se for um arquivo estático existente (CSS, JS, imagens), entrega diretamente
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Deixa o PHP embutido servir o arquivo
}

// 4. Se for raiz, serve index.php
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/../../index.php';
    exit;
}

// 5. Para scripts PHP normais
if (file_exists($filePath)) {
    require $filePath;
    exit;
}

http_response_code(404);
exit('404 Not Found');
