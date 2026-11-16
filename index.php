<?php
// ARQUIVO: bm.contextopolicial.com/index.php
// VERSÃO: CORREÇÃO DO LOOP INFINITO

// 1. CARREGA O CONFIG (Sessão SSO)
if (file_exists(__DIR__ . '/../libs/config.php')) {
    require_once __DIR__ . '/../libs/config.php';
}
else {
    require_once __DIR__ . '/libs/config.php';
}

// 2. O MANOBRISTA (Redirecionamento Apenas para Sucesso)
// Se o aluno já tem sessão válida, ele não precisa ver essa tela.
// Manda direto para o painel.
if (isset($_SESSION['aluno_id'])) {
    header("Location: area_aluno.php");
    exit;
}

// 3. SE NÃO TIVER SESSÃO (O FIM DO LOOP) 🛑
// Aqui estava o erro: antes você mandava voltar pro Hub/Login.
// Agora, se não tiver sessão, o código simplesmente SEGUE e mostra o formulário abaixo.
// O aluno terá que fazer login nesta tela.

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MÉTODO CONTEXTO POLICIAL - CONCURSO BOMBEIRO MILITAR DE MG 2026</title>
    <link rel="icon" href="fotos/viatura.jpg">

    <script>
        !function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () {
                n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = !0;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = !0;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1659561894712709');
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=1659561894712709&ev=PageView&noscript=1" />
    </noscript>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #a176cc 100%);
        }

        .main-card {
            backdrop-filter: blur(10px);
            background: rgba(94, 123, 182, 0.95);
        }

        .login-section {
            display: none;
        }

        .login-section.active {
            display: block;
        }

        .home-section.active {
            display: block;
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>

<body class="gradient-bg min-h-screen flex items-center justify-center p-4"
    style="background-image: url('fotos/viatura.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">

    <div id="homeSection" class="home-section active">
        <div class="main-card max-w-lg w-full p-8 rounded-2xl shadow-2xl text-center"
            style="border: 2px solid #2563eb; transform: scale(1.02);">
            <div class="flex flex-col items-center justify-center mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2" style="color: #DC143C;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <h1 class="text-3xl font-bold text-white mb-2">MÉTODO CONTEXTO POLICIAL</h1>
                <h2 class="text-xl font-semibold text-red-600 mb-4" style="color: #d5e643;">
                    CONCURSO BOMBEIRO MILITAR DE MG 2026
                </h2>
            </div>

            <p class="text-gray-100 mb-8 text-lg leading-relaxed">
                Seu portal completo para garantir sua vaga de Bombeiro Militar em MG.
                <br><br>
                <strong style="color: #fff">Milhares de questões, resumos, flashcards e simulados!</strong>
            </p>

            <div class="space-y-4">
                <button onclick="window.open('https://www.contextopolicial.com', '_blank')"
                    class="w-full py-4 text-white font-bold text-lg rounded-full transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                    style="background: linear-gradient(90deg, #DC143C 0%, #B91C3C 100%);">
                    🛒 QUERO COMPRAR O PACOTE
                </button>

                <button onclick="showLoginSection()"
                    class="w-full py-3 text-white font-semibold border-2 rounded-full transition-all duration-300 transform hover:scale-105 hover:bg-blue-800"
                    style="background: rgba(0, 51, 153, 0.9); border-color: #003399;">
                    👤 ÁREA DO ALUNO
                </button>
            </div>

            <div class="mt-6 text-center">
                <p class="text-gray-200 text-sm mb-2">Dúvidas ou suporte?</p>
                <p class="font-semibold text-sm" style="color: #fff;">
                    📱 WhatsApp: (31) 98439-3953<br>
                    📧 Email: contato@contextopolicial.com
                </p>
            </div>
        </div>
    </div>

    <div id="loginSection" class="login-section">
        <div class="main-card max-w-md w-full p-8 rounded-2xl shadow-2xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">MÉTODO CONTEXTO POLICIAL</h1>
                <h2 class="text-lg font-semibold text-white mb-2">CONCURSO BOMBEIRO MILITAR DE MG 2026</h2>
                <p class="text-white">Faça login para acessar sua área de estudos</p>
            </div>

            <form id="loginForm" action="processa_login.php" method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-white mb-2">👤 E-mail de Acesso</label>
                    <input type="email" id="username" name="email" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                        placeholder="Digite seu e-mail">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-white mb-2">🔑 Senha</label>
                    <div class="relative">
                        <input type="password" id="password" name="senha" required
                            class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            placeholder="Digite sua senha">
                        <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 transition-colors"
                            onclick="togglePasswordVisibility()">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                            </svg>
                        </button>
                    </div>
                    <div class="text-right mt-1">
                        <a href="esqueci_senha.php" class="text-xs text-yellow-300 hover:text-yellow-100 font-medium">Esqueci minha senha</a>
                    </div>
                </div>

                <button type="submit" id="loginBtn"
                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-105">
                    🚀 Entrar
                </button>
            </form>

            <div id="message" class="mt-4 p-3 rounded-lg hidden text-center font-bold"></div>

            <div class="mt-8 text-center">
                <p class="text-sm text-white">
                    Não tem acesso ainda?
                    <a href="venda.html" class="text-yellow-300 hover:text-yellow-100 font-medium">
                        Adquira seu plano
                    </a>
                </p>
            </div>

            <div class="mt-6 text-center">
                <button onclick="showHomeSection()" class="text-yellow-300 hover:text-yellow-100 font-medium text-sm">
                    ← Voltar ao início
                </button>
            </div>
        </div>
    </div>

    <script>
        // Navegação entre Telas
        function showLoginSection() {
            if (typeof fbq !== 'undefined') {
                fbq('track', 'Lead', { content_name: 'Clique Área do Aluno', content_category: 'Login' });
            }
            document.getElementById('homeSection').style.display = 'none';
            document.getElementById('loginSection').style.display = 'block';
            document.getElementById('username').focus();
        }

        function showHomeSection() {
            document.getElementById('homeSection').style.display = 'block';
            document.getElementById('loginSection').style.display = 'none';
            document.getElementById('message').classList.add('hidden');
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // UX: Mostrar/Ocultar Senha
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        // LÓGICA DE ERROS E SUCESSO
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const erro = urlParams.get('erro');
            const senha_atualizada = urlParams.get('senha_atualizada');

            if (erro || senha_atualizada === 'true') {
                showLoginSection();

                const messageDiv = document.getElementById('message');
                let texto = '';
                let tipo = 'error';

                if (senha_atualizada === 'true') {
                    texto = '✅ Senha redefinida com sucesso! Faça seu login.';
                    tipo = 'success';
                } else {
                    switch (erro) {
                    case 'assinatura_vencida':
                        texto = '⚠️ Sua assinatura expirou! Renove seu plano para continuar o acesso.';
                        break;
                    case 'campos_vazios':
                        texto = '⚠️ Preencha e-mail e senha!';
                        break;
                    case 'dados_incorretos':
                        texto = '❌ E-mail ou senha incorretos.';
                        break;
                    case 'conta_pendente':
                        texto = '⏳ Pagamento em análise.';
                        break;
                    case 'sem_sessao':
                        texto = '⚠️ Sessão expirou. Logue novamente.';
                        break;
                    case 'vazio': texto = '⚠️ Preencha e-mail e senha!'; break;
                    case 'dados_invalidos': texto = '❌ E-mail ou senha incorretos.'; break;
                    case 'pendente': texto = '⏳ Pagamento em análise.'; break;
                    case 'sistema': texto = '❌ Erro no sistema.'; break;
                    // ... dentro do switch do index.php
                    case 'acesso_negado_escola':
                        texto = '🚫 Acesso negado! Este ambiente não existe ou você não tem permissão para acessá-lo.';
                        break;

                    default: texto = '❌ Ocorreu um erro no login.';
                    }
                } // fecha o else

                messageDiv.className = `mt-4 p-3 rounded-lg ${tipo === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} text-center font-bold`;
                messageDiv.textContent = texto;
                messageDiv.classList.remove('hidden');
            }
        });
    </script>
</body>

</html>