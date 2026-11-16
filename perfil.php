<?php
// ARQUIVO: area-do-aluno/perfil.php
require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php';

$idAluno = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
if (!$idAluno) {
    header("Location: index.php");
    exit;
}

// Busca dados atualizados do aluno
$stmt = $pdo->prepare("SELECT nome, email, escola_origem FROM alunos WHERE id = ?");
$stmt->execute([$idAluno]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Método Adrenalina</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #111; color: #fff; display: flex; justify-content: center; padding: 40px 20px; }
        .container { background: #1c1c1c; padding: 40px; border-radius: 10px; border: 1px solid #333; width: 100%; max-width: 500px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        h2 { color: #f86900; margin-top: 0; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        
        .info-group { margin-bottom: 25px; background: #222; padding: 15px; border-radius: 8px; }
        .label { font-size: 12px; color: #888; text-transform: uppercase; font-weight: bold; display: block; margin-bottom: 5px; }
        .value { font-size: 16px; color: #fff; font-weight: 500; }
        
        form { margin-top: 30px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #ddd; font-size: 14px; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; background: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 5px; box-sizing: border-box; }
        input:focus { border-color: #f86900; outline: none; }
        
        button { width: 100%; padding: 12px; background: #f86900; color: #000; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; transition: 0.3s; font-size: 16px; }
        button:hover { background: #e56000; }
        
        .btn-voltar { display: block; text-align: center; margin-top: 20px; color: #888; text-decoration: none; font-size: 14px; }
        .btn-voltar:hover { color: #fff; }

        .msg { padding: 10px; margin-bottom: 20px; border-radius: 5px; font-size: 14px; text-align: center; }
        .erro { background: #3f1a1a; color: #ff6b6b; border: 1px solid #5c2b2b; }
        .sucesso { background: #1a3f2a; color: #6bff96; border: 1px solid #2b5c3a; }
    </style>
</head>
<body>

<div class="container">
    <h2>👤 Meu Perfil</h2>

    <?php if(isset($_GET['sucesso'])): ?>
        <div class="msg sucesso">✅ Senha alterada com sucesso!</div>
    <?php endif; ?>
    
    <?php if(isset($_GET['erro'])): ?>
        <div class="msg erro">
            <?php 
                if($_GET['erro'] == 'senha_atual_incorreta') echo "❌ A senha atual não confere.";
                else if($_GET['erro'] == 'senhas_nao_batem') echo "❌ As novas senhas não coincidem.";
                else if($_GET['erro'] == 'senha_curta') echo "⚠️ A nova senha deve ter pelo menos 6 caracteres.";
                else echo "❌ Erro ao alterar senha.";
            ?>
        </div>
    <?php endif; ?>

    <div class="info-group">
        <span class="label">Nome Completo</span>
        <div class="value"><?php echo htmlspecialchars($aluno['nome']); ?></div>
    </div>
    
    <div class="info-group">
        <span class="label">E-mail de Acesso</span>
        <div class="value"><?php echo htmlspecialchars($aluno['email']); ?></div>
    </div>

    <form action="processa_perfil.php" method="POST">
        <h3 style="color:#f86900; font-size:18px; margin-bottom:15px;">🔒 Alterar Senha</h3>
        <input type="hidden" name="acao" value="alterar_senha">
        
        <label>Senha Atual</label>
        <input type="password" name="senha_atual" required placeholder="Digite sua senha atual">
        
        <label>Nova Senha</label>
        <input type="password" name="nova_senha" required placeholder="Mínimo 6 caracteres">
        
        <label>Confirmar Nova Senha</label>
        <input type="password" name="confirmar_senha" required placeholder="Digite novamente">
        
        <button type="submit">SALVAR NOVA SENHA</button>
    </form>

    <a href="index.php" class="btn-voltar">← Voltar para Área do Aluno</a>
</div>

</body>
</html>