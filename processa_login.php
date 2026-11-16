<?php
// ARQUIVO: processa_login.php
// VERSÃO: DEFINITIVA (Bloqueio na Porta da Rua + Expiração)

// 1. CARREGA O CÉREBRO (config.php)
require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php';

ini_set('display_errors', 0); // Oculta erros técnicos do usuário final
error_reporting(E_ALL);

// 2. PROTEÇÃO DE ACESSO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    header("Location: index.php?erro=campos_vazios");
    exit;
}

try {
    // 3. BUSCA ALUNO NO BANCO
    $stmt = $pdo->prepare("SELECT * FROM alunos WHERE email = ? LIMIT 1");
    $stmt->execute([$email]); 
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);

    // E-mail não achado ou Senha não bate
    if (!$aluno || !password_verify($senha, $aluno['senha'])) {
        header("Location: index.php?erro=dados_incorretos");
        exit;
    }

    // ====================================================================
    // 🛑 VALIDAÇÃO DE DATA DE EXPIRAÇÃO
    // ====================================================================
    if (!empty($aluno['data_expiracao'])) {
        $dataExpiracao = new DateTime($aluno['data_expiracao']);
        $agora = new DateTime(); 
        
        if ($agora > $dataExpiracao) {
            header("Location: index.php?erro=assinatura_vencida"); // Bloqueado por expiração
            exit;
        }
    }

    // Trava de Status de Pagamento (Bloqueia reembolsos/cancelados)
    if (isset($aluno['status']) && $aluno['status'] !== 'approved' && $aluno['status'] !== 'ativo') {
        header("Location: index.php?erro=conta_pendente");
        exit;
    }

    // ====================================================================
    // 🛑 A PORTA DE AÇO (MODO BLINDADO)
    // ====================================================================
    $stmt2 = $pdo->prepare("SELECT slug, familia_sessao FROM config_escolas WHERE id = ?");
    $stmt2->execute([$aluno['escola_origem']]);
    $escola_db = $stmt2->fetch(PDO::FETCH_ASSOC);

    $fam_aluno_clean = trim(strtolower($escola_db['familia_sessao'] ?? 'indefinida'));
    $fam_site_clean  = trim(strtolower(defined('FAMILIA_ESCOLA_ATUAL') ? FAMILIA_ESCOLA_ATUAL : ''));

    // REGRA DE FERRO: Se o site não existir (vazio) ou a família for diferente, ABORTA O LOGIN!
    if (empty($fam_site_clean) || $fam_site_clean !== $fam_aluno_clean) {
        header("Location: index.php?erro=acesso_negado_escola");
        exit;
    }
    // ====================================================================

    // 4. O PULO DO GATO: PREENCHENDO A "GAVETA" (Regenera ID contra Session Fixation)
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true; 
    
    $_SESSION['aluno_id']      = $aluno['id']; 
    $_SESSION['user_id']       = $aluno['id']; 
    
    $_SESSION['familia_aluno'] = $fam_aluno_clean; 
    $_SESSION['email']         = $aluno['email'];      
    $_SESSION['user_nome']     = $aluno['nome'];
    
    $_SESSION['escola_matricula'] = $aluno['escola_origem']; 
    $_SESSION['status_pagamento'] = $aluno['status'] ?? 'approved';
    
    // 5. REDIRECIONAMENTO PARA O SUCESSO
    header("Location: area_aluno.php");
    exit;

} catch (Exception $e) {
    header("Location: index.php?erro=sistema");
    exit;
}
?>