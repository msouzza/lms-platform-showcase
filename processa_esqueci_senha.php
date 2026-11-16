<?php
// ARQUIVO: processa_esqueci_senha.php
require_once '../libs/config.php';
require_once '../database/conexao.php';
require_once '../libs/email.php';

ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: esqueci_senha.php");
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

if (empty($email)) {
    header("Location: esqueci_senha.php?erro=nao_encontrado");
    exit;
}

try {
    // 1. Busca aluno pelo e-mail
    $stmt = $pdo->prepare("SELECT id, nome, escola_origem FROM alunos WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aluno) {
        // Redireciona para erro se não achar
        header("Location: esqueci_senha.php?erro=nao_encontrado");
        exit;
    }

    // 2. Busca configuração da escola do aluno para enviar o e-mail corretamente (SMTP e Nome)
    // Se a escola do aluno for deletada ou não encontrada, usa a escola '1' (adrenalina) como fallback
    $stmtEscola = $pdo->prepare("SELECT * FROM config_escolas WHERE id = ?");
    $stmtEscola->execute([$aluno['escola_origem']]);
    $config_escola = $stmtEscola->fetch(PDO::FETCH_ASSOC);

    if (!$config_escola) {
        $stmtEscolaFallback = $pdo->prepare("SELECT * FROM config_escolas WHERE id = 1");
        $stmtEscolaFallback->execute();
        $config_escola = $stmtEscolaFallback->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Gera Token Temporário Segurto (1 hora de validade)
    $token = bin2hex(random_bytes(32)); // 64 caracteres alfa numéricos
    $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // 4. Salva o Token e a data de expiração no banco
    $stmtUpd = $pdo->prepare("UPDATE alunos SET reset_token = ?, reset_token_expiracao = ? WHERE id = ?");
    $stmtUpd->execute([$token, $expiracao, $aluno['id']]);

    // 5. Monta o link de reset validando o Host contra Host Header Injection
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    $hostConfiavel = preg_match('/^(?:[a-z0-9-]+\.)?(contextopolicial\.com|adrenalinapolicial\.com)(?:\.br)?$/i', $host)
        || $host === 'localhost'
        || $host === '127.0.0.1';

    if ($hostConfiavel) {
        $baseReset = $protocolo . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    } else {
        $baseReset = rtrim($config_escola['url_vitrine'] ?? 'https://bm.contextopolicial.com', '/');
    }
    $linkReset = $baseReset . '/reset.php?token=' . urlencode($token);

    // 6. Tenta disparar o E-mail usando a função que criamos em libs/email.php
    $enviado = enviarEmailRecuperacaoSenha($aluno['nome'], $email, $linkReset, $config_escola);

    if ($enviado) {
        header("Location: esqueci_senha.php?sucesso=enviado");
    } else {
        // O e-mail falhou em enviar (Erro SMTP, credenciais inválidas)
        header("Location: esqueci_senha.php?erro=sistema");
    }

} catch (Exception $e) {
    // Erro de conectividade com Banco
    header("Location: esqueci_senha.php?erro=sistema");
}
