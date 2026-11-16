<?php
// ARQUIVO: area-do-aluno/processa_perfil.php
require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php';

$idAluno = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
if (!$idAluno) {
    header("Location: index.php");
    exit;
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'alterar_senha') {
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmarSenha = $_POST['confirmar_senha'];

    // 1. Validações Básicas
    if (empty($senhaAtual) || empty($novaSenha)) {
        header("Location: perfil.php?erro=campos_vazios");
        exit;
    }

    if ($novaSenha !== $confirmarSenha) {
        header("Location: perfil.php?erro=senhas_nao_batem");
        exit;
    }

    if (strlen($novaSenha) < 6) {
        header("Location: perfil.php?erro=senha_curta");
        exit;
    }

    try {
        // 2. Busca a senha atual no banco para conferir
        $stmt = $pdo->prepare("SELECT senha FROM alunos WHERE id = ?");
        $stmt->execute([$idAluno]);
        $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($aluno && password_verify($senhaAtual, $aluno['senha'])) {
            // 3. Senha Atual Confere! Vamos trocar.
            $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            $stmtUpdate = $pdo->prepare("UPDATE alunos SET senha = ? WHERE id = ?");
            $stmtUpdate->execute([$novoHash, $idAluno]);
            
            header("Location: perfil.php?sucesso=senha_alterada");
        } else {
            header("Location: perfil.php?erro=senha_atual_incorreta");
        }

    } catch (Exception $e) {
        header("Location: perfil.php?erro=sistema");
    }
} else {
    header("Location: perfil.php");
}
?>