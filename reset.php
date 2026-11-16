<?php
// ARQUIVO: reset.php
require_once '../libs/config.php';
require_once '../database/conexao.php';

ini_set('display_errors', 0);
error_reporting(0);

$token = $_GET['token'] ?? '';
$erro = $_GET['erro'] ?? '';

if (empty($token)) {
    header("Location: index.php");
    exit;
}

// Verifica se o token existe e ainda está no prazo de validade (usando o mesmo fuso horário do PHP)
$stmt = $pdo->prepare("SELECT id, nome FROM alunos WHERE reset_token = ? AND reset_token_expiracao >= ? LIMIT 1");
$stmt->execute([$token, date('Y-m-d H:i:s')]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);

$token_valido = ($aluno !== false);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Senha - <?= NOME_ESCOLA ?></title>
    <link rel="icon" href="fotos/viatura.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #a176cc 100%);
        }
        .main-card {
            backdrop-filter: blur(10px);
            background: rgba(94, 123, 182, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4"
    style="background-image: url('fotos/viatura.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
    
    <div class="main-card max-w-md w-full p-8 rounded-2xl shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">MÉTODO CONTEXTO POLICIAL</h1>
            <h2 class="text-lg font-semibold text-white mb-2">CONCURSO BOMBEIRO MILITAR DE MG 2026</h2>
            <p class="text-white mb-4 font-bold uppercase">
                <?= $token_valido ? 'Criar Nova Senha' : 'Link Expirado' ?>
            </p>
            
            <?php if ($token_valido): ?>
                <p class="text-white">
                    Olá, <strong><?= htmlspecialchars($aluno['nome']) ?></strong>! Digite sua nova senha abaixo.
                </p>
            <?php endif; ?>
        </div>

        <?php if (!$token_valido): ?>
            <!-- Tela de Token Inválido -->
            <div class="bg-red-100 text-red-700 px-4 py-4 rounded-lg shadow-sm mb-6 text-center font-bold" role="alert">
                <span class="block">Este link é inválido ou já expirou.</span>
                <p class="text-xs mt-2 text-red-600">Para sua segurança, os links de recuperação de senha têm validade de 1 hora e só funcionam uma vez.</p>
            </div>
            
            <a href="esqueci_senha.php" class="w-full block text-center bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 transition-all transform hover:scale-105">
                Solicitar Novo Link
            </a>
            <div class="mt-6 text-center">
                <a href="index.php" class="text-yellow-300 hover:text-yellow-100 font-medium text-sm transition-colors">← Voltar ao Login</a>
            </div>

        <?php else: ?>
            <!-- Tela de Troca de Senha -->
            
            <?php if ($erro === 'senhas_diferentes'): ?>
                <div class="bg-red-100 text-red-700 font-bold px-4 py-3 rounded-lg shadow-sm mb-6 text-center" role="alert">
                    <span class="block">As senhas não coincidem. Tente novamente.</span>
                </div>
            <?php elseif ($erro === 'senha_curta'): ?>
                 <div class="bg-red-100 text-red-700 font-bold px-4 py-3 rounded-lg shadow-sm mb-6 text-center" role="alert">
                    <span class="block">A senha deve ter pelo menos 6 caracteres.</span>
                </div>
            <?php elseif ($erro === 'sistema'): ?>
                 <div class="bg-red-100 text-red-700 font-bold px-4 py-3 rounded-lg shadow-sm mb-6 text-center" role="alert">
                    <span class="block">Erro ao atualizar a senha. Tente novamente.</span>
                </div>
            <?php endif; ?>

            <form action="processa_reset.php" method="POST" id="resetForm" class="space-y-6">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label for="senha1" class="block text-sm font-medium text-white mb-2">🔑 Nova Senha</label>
                    <input type="password" id="senha1" name="nova_senha" required minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        placeholder="Mínimo 6 caracteres">
                </div>
                
                <div>
                    <label for="senha2" class="block text-sm font-medium text-white mb-2">🔑 Confirmar Nova Senha</label>
                    <input type="password" id="senha2" name="confirma_senha" required minlength="6"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        placeholder="Repita a senha">
                    <p id="errorMatch" class="text-yellow-300 text-xs font-semibold mt-1 hidden">As senhas não coincidem.</p>
                </div>
                
                <button type="submit" id="submitBtn"
                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-105 mt-2">
                    💾 Salvar e Entrar
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="index.php" class="text-yellow-300 hover:text-yellow-100 font-medium text-sm">← Voltar ao Login</a>
            </div>

            <script>
                // Validação no frontend
                const form = document.getElementById('resetForm');
                const p1 = document.getElementById('senha1');
                const p2 = document.getElementById('senha2');
                const btn = document.getElementById('submitBtn');
                const errorMatch = document.getElementById('errorMatch');

                form.addEventListener('submit', function(e) {
                    if (p1.value !== p2.value) {
                        e.preventDefault();
                        p2.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                        errorMatch.classList.remove('hidden');
                    } else {
                        btn.innerHTML = 'Salvando...';
                        btn.disabled = true;
                        btn.classList.add('opacity-75', 'cursor-not-allowed');
                    }
                });

                p2.addEventListener('input', function() {
                    this.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                    errorMatch.classList.add('hidden');
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
