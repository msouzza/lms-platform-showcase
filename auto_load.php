<?php
// ARQUIVO: auto_load.php
// OBJETIVO: Preparar variáveis para o HTML (Sem iniciar sessão novamente)

// ⚠️ ATENÇÃO: Este arquivo assume que libs/config.php JÁ FOI CARREGADO antes!

// 1. MAPEAMENTO DE VARIÁVEIS (Compatibilidade com seu HTML antigo)
// O HTML usa $id_aluno, $nome_aluno, etc. Vamos criar essas variáveis aqui.

$id_aluno = $_SESSION['aluno_id'] ?? null;
$nome_aluno = $_SESSION['aluno_nome'] ?? 'Aluno';
$email_aluno = $_SESSION['aluno_email'] ?? '';
$status_pagamento = $_SESSION['status_pagamento'] ?? 'pending';

// 2. HIDRATAÇÃO (Atualizar Status do Banco)
// Útil para verificar se o aluno foi bloqueado recentemente sem precisar relogar
if ($id_aluno && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT nome, status, escola_origem FROM alunos WHERE id = ? LIMIT 1");
        $stmt->execute([$id_aluno]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dados) {
            // Atualiza a sessão com dados frescos do banco
            $_SESSION['status_pagamento'] = trim(strtolower($dados['status']));
            $_SESSION['aluno_nome'] = $dados['nome']; // Garante nome atualizado
            
            // Atualiza as variáveis locais também
            $status_pagamento = $_SESSION['status_pagamento'];
            $nome_aluno = $dados['nome'];
        }
    } catch (Exception $e) {
        // Falha silenciosa para não travar o site
    }
}

// 3. VARIÁVEIS GLOBAIS EXTRAS (Se seu sistema usar)
$escola_atual = defined('NOME_ESCOLA') ? NOME_ESCOLA : 'Adrenalina';
?>