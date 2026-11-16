<?php
/**
 * Verificador de Sintaxe (PHP Lint) para todos os arquivos PHP do projeto.
 * Executado via `composer lint` ou diretamente no pipeline de CI.
 */

$rootDir = dirname(__DIR__, 2);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$erros = [];
$total = 0;

foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (
        strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false ||
        strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false
    ) {
        continue;
    }

    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    // Ignora arquivos temporários de backup (* copy.php)
    if (strpos($file->getFilename(), ' copy.php') !== false) {
        continue;
    }

    $total++;
    $output = [];
    $returnVar = 0;
    exec( escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $returnVar);

    if ($returnVar !== 0) {
        $erros[$path] = implode("\n", $output);
        echo "❌ ERRO DE SINTAXE: {$path}\n" . implode("\n", $output) . "\n";
    }
}

echo "✅ PHP Lint concluído: {$total} arquivos verificados.\n";

if (!empty($erros)) {
    exit(1);
}
exit(0);
