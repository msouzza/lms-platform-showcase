<?php
require_once '../libs/config.php';

$erro = $_GET['erro'] ?? '';
$sucesso = $_GET['sucesso'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci Minha Senha - <?= NOME_ESCOLA ?></title>
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
            <p class="text-white mb-4 font-bold">RECUPERAR SENHA</p>
            <p class="text-white">Digite o e-mail cadastrado na plataforma e enviaremos um link seguro para você redefinir sua senha.</p>
        </div>

        <?php if ($erro === 'nao_encontrado'): ?>
            <div class="bg-red-100 text-red-700 px-4 py-3 rounded-lg shadow-sm mb-6 flex items-start font-bold text-center justify-center">
                <span class="block">Não localizamos nenhum aluno com este e-mail.</span>
            </div>
        <?php elseif ($erro === 'sistema'): ?>
             <div class="bg-red-100 text-red-700 px-4 py-3 rounded-lg shadow-sm mb-6 flex items-start font-bold text-center justify-center">
                <span class="block">Ocorreu um erro no servidor. Tente novamente mais tarde.</span>
            </div>
        <?php elseif ($sucesso === 'enviado'): ?>
            <div class="bg-green-100 text-green-700 px-4 py-3 rounded-lg shadow-sm mb-6 flex flex-col items-center font-bold text-center">
                <span class="block text-sm mb-1">Tudo certo! Um link de recuperação foi enviado.</span>
                <span class="block text-xs text-green-600">Verifique sua caixa de entrada e também o Lixo Eletrônico (Spam).</span>
            </div>
        <?php endif; ?>

        <?php if ($sucesso !== 'enviado'): ?>
            <form action="processa_esqueci_senha.php" method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-white mb-2">👤 Seu E-mail de Acesso</label>
                    <input type="email" id="email" name="email" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        placeholder="exemplo@email.com">
                </div>
                
                <button type="submit" id="submitBtn"
                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-105">
                    ✉️ Enviar Link Seguro
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="index.php" class="text-yellow-300 hover:text-yellow-100 font-medium text-sm">
                ← Voltar para o Login
            </a>
        </div>
    </div>

    <!-- Script de loading -->
    <script>
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                btn.innerHTML = 'Enviando...';
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            });
        }
    </script>
</body>
</html>
