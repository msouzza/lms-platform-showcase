<?php
// ARQUIVO: processa_reset.php
require_once '../libs/config.php';
require_once '../database/conexao.php';

ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$token = $_POST['token'] ?? '';
$nova_senha = $_POST['nova_senha'] ?? '';
$confirma_senha = $_POST['confirma_senha'] ?? '';

if (empty($token)) {
    header("Location: index.php");
    exit;
}

if ($nova_senha !== $confirma_senha) {
    header("Location: reset.php?token=" . urlencode($token) . "&erro=senhas_diferentes");
    exit;
}

if (strlen($nova_senha) < 6) {
    header("Location: reset.php?token=" . urlencode($token) . "&erro=senha_curta");
    exit;
}

try {
    // 1. Validar se o token é válido e recupera o ID do aluno (usando o mesmo fuso horário do PHP)
    $stmt = $pdo->prepare("SELECT id FROM alunos WHERE reset_token = ? AND reset_token_expiracao >= ? LIMIT 1");
    $stmt->execute([$token, date('Y-m-d H:i:s')]);
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se o token já venceu ou alguém tentou mandar post forjado
    if (!$aluno) {
        // Redireciona de volta para forçar mostrar a tela de Expiração
        header("Location: reset.php?token=" . urlencode($token));
        exit;
    }

    // 2. Criptografa a nova senha com bcrypt (O Padrão Seguro que já era usado)
    $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);

    // 3. Atualiza a senha e 'Queima' o token para ele não poder ser reutilizado
    $stmtUpd = $pdo->prepare("UPDATE alunos SET senha = ?, reset_token = NULL, reset_token_expiracao = NULL WHERE id = ?");
    $stmtUpd->execute([$senhaHash, $aluno['id']]);

    // 4. Redireciona com flag de sucesso para a tela de Login
    header("Location: index.php?senha_atualizada=true");
    exit;

} catch (Exception $e) {
    header("Location: reset.php?token=" . urlencode($token) . "&erro=sistema");
}
