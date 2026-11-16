<?php
// ARQUIVO: area_aluno.php
// SOLUÇÃO: Fallback de Segurança para Localização do Config

// 1. BUSCA INTELIGENTE DO CONFIG (Resolve o erro de "No such file or directory")
$caminhosConfig = [
    __DIR__ . '/../libs/config.php', // Tenta 1 nível acima (Padrão XAMPP)
    __DIR__ . '/libs/config.php', // Tenta na mesma pasta
    dirname($_SERVER['DOCUMENT_ROOT']) . '/libs/config.php' // Tenta padrão cPanel
];

$configCarregado = false;
foreach ($caminhosConfig as $caminho) {
    if (file_exists($caminho)) {
        require_once $caminho;
        $configCarregado = true;
        break;
    }
}

if (!$configCarregado) {
    die("<h1>Erro de Sistema</h1> O arquivo de configuração (libs/config.php) não foi encontrado.");
}

// 2. VALIDAÇÃO DE ACESSO (Unificada)
if (!isset($_SESSION['aluno_id']) && !isset($_SESSION['user_id'])) {
    header("Location: index.php?erro=sem_sessao");
    exit;
}

$id_aluno = $_SESSION['aluno_id'] ?? $_SESSION['user_id'];
// Usa a constante definida no config.php, com fallback seguro para 1
$escolaIdAtual = defined('ID_ESCOLA_ATUAL') ? ID_ESCOLA_ATUAL : 1;


// ----------------------------------------------------------------------
// 3. CARREGAMENTOS SECUNDÁRIOS
// ----------------------------------------------------------------------
require_once 'auto_load.php';

// Cabeçalhos de Segurança
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// 4. Conexão com Banco (Garantia)
if (!file_exists(__DIR__ . '/../database/conexao.php')) {
    die("Erro Crítico: Arquivo de conexão não encontrado.");
}
require_once __DIR__ . '/../database/conexao.php';

// ======================================================================
// 5. RESGATE DE DADOS DA SESSÃO (Fazemos isso ANTES de qualquer bloqueio)
// ======================================================================
$nome_aluno_layout = $_SESSION['user_nome'] ?? $_SESSION['nome'] ?? 'Aluno';
$nome_aluno = $nome_aluno_layout;
$email_aluno = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$status_pagamento = $_SESSION['status_pagamento'] ?? 'pending';

// ======================================================================
// 6. LÓGICA DE NEGÓCIO (Modo Blindado - Mantendo o Layout)
// ======================================================================

// Agora a constante vai existir graças ao config.php!
$familia_do_site = defined('FAMILIA_ESCOLA_ATUAL') ? FAMILIA_ESCOLA_ATUAL : 'indefinida';

$fam_site_clean = trim(strtolower($familia_do_site));
$fam_aluno_clean = trim(strtolower($_SESSION['familia_aluno'] ?? 'indefinida'));

$acesso_liberado = true;
$msg_bloqueio = "";

// REGRA DE FERRO AJUSTADA
if ($fam_site_clean !== $fam_aluno_clean) {
    $acesso_liberado = false;
    $msg_bloqueio = "Seu acesso pertence à escola " . strtoupper($fam_aluno_clean) . " e não é válido neste ambiente (" . strtoupper($fam_site_clean) . ").";

// REMOVIDO: session_destroy(); 
// Em vez de destruir a sessão (o que afeta outras abas do navegador),
// apenas bloqueamos a interface.
}
// ======================================================================

// Link padrão de checkout 
$link_mercado_pago = "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=SEU-ID-AQUI";

// 7. BUSCAR PROGRESSO (Apenas se o acesso for liberado!)
$progresso_array = [];
$progresso_json = '[]';

if ($acesso_liberado && $id_aluno) {
    try {
        $stmt = $pdo->prepare("SELECT conteudo_ref, tipo_conteudo FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ?");
        $stmt->execute([$id_aluno, $escolaIdAtual]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultados as $item) {
            $chave = $item['conteudo_ref'] . '||' . $item['tipo_conteudo'];
            $progresso_array[] = $chave;
        }
        $progresso_json = json_encode($progresso_array);
    }
    catch (Exception $e) {
    // Silêncio é ouro
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Método Adrenalina - Bombeiro Militar de MG 2026</title>

    <script>
        // --- VARIÁVEL GLOBAL DE PROGRESSO ---
        window.progressoSalvo = <?php echo $progresso_json; ?> || [];

        !function (f, b, e, v, n, t, s) {
            if (f.fbq) return; n = f.fbq = function () {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0';
            n.queue = []; t = b.createElement(e); t.async = !0;
            t.src = v; s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1659561894712709');
        fbq('track', 'PageView');

        <?php if ($status_pagamento === 'approved' || $status_pagamento === 'ativo'): ?>
            fbq('track', 'CompleteRegistration', {
                content_name: 'Área do Aluno - Bommeiro Militar MG 2026',
                status: 'completed'
            });
        <?php
endif; ?>
    </script>
    <noscript><img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=1659561894712709&ev=PageView&noscript=1" /></noscript>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="conteudos.js"></script>

    <script>
        if (typeof quizzes === 'undefined') window.quizzes = [];
        if (typeof flashcards === 'undefined') window.flashcards = [];
        if (typeof resumos === 'undefined') window.resumos = [];
        if (typeof simulados === 'undefined') window.simulados = [];

        window.todosConteudos = [
            ...quizzes.map(item => ({ ...item, tipo: 'Quiz' })),
            ...flashcards.map(item => ({ ...item, tipo: 'Flashcard' })),
            ...resumos.map(item => ({ ...item, tipo: 'Resumo' })),
            ...simulados.map(item => ({ ...item, tipo: 'Simulado' }))
        ];
    </script>

    <script>
        // ============================================================
        // MIGRAÇÃO: studied-card-* (localStorage → Banco de Dados)
        // Roda uma única vez e se auto-desativa
        // ============================================================
        (async function migrarStudiedCards() {
            if (localStorage.getItem('migracao_studied_cards_ok') === 'true') return;

            const lista = window.todosConteudos || [];
            if (lista.length === 0) return; // Arrays ainda não carregaram

            const progressoAtual = new Set(window.progressoSalvo || []);
            const paraEnviar = [];
            const chavesParaLimpar = [];

            // 1. Varre o localStorage buscando studied-card-{Tipo}-{Index}
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (!key || !key.startsWith('studied-card-')) continue;

                // Ex: "studied-card-Resumo-228" → tipo="Resumo", index=228
                const partes = key.replace('studied-card-', '').match(/^(.+?)-(\d+)$/);
                if (!partes) continue;

                const tipo = partes[1];       // "Resumo", "Flashcard", "Quiz", "Simulado"
                const globalIndex = parseInt(partes[2], 10);

                // 2. Busca o conteúdo correspondente no todosConteudos
                const conteudo = lista[globalIndex];
                if (!conteudo || !conteudo.path) continue;

                // 3. Verifica se o tipo bate (segurança)
                if (conteudo.tipo !== tipo) {
                    console.warn(`⚠️ Migração: índice ${globalIndex} é ${conteudo.tipo}, não ${tipo}. Pulando.`);
                    continue;
                }

                const chaveUnica = conteudo.path + '||' + conteudo.tipo;

                // 4. Só migra se ainda NÃO está no banco
                if (progressoAtual.has(chaveUnica)) {
                    chavesParaLimpar.push(key); // Já existe, só limpa
                    continue;
                }

                paraEnviar.push({ ref: conteudo.path, tipo: conteudo.tipo });
                chavesParaLimpar.push(key);
            }

            // 5. Envia para a API em lote
            if (paraEnviar.length > 0) {
                try {
                    const resp = await fetch('api_progresso.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ acao: 'sincronizar', lista: paraEnviar })
                    });
                    const res = await resp.json();

                    if (res.status === 'sucesso' || res.status === 'ok') {
                        console.log(`✅ Migração studied-cards: ${paraEnviar.length} item(ns) enviado(s).`);

                        // Atualiza o progressoSalvo em memória para refletir imediatamente
                        paraEnviar.forEach(item => {
                            const chave = item.ref + '||' + item.tipo;
                            if (!window.progressoSalvo.includes(chave)) {
                                window.progressoSalvo.push(chave);
                            }
                        });

                        // Re-renderiza os cards e gauges
                        if (typeof renderConteudos === 'function') renderConteudos();
                        if (typeof updateAllStudyGauges === 'function') {
                            updateAllStudyGauges(new Set(window.progressoSalvo));
                        }
                    } else {
                        console.error('❌ Migração studied-cards falhou:', res);
                        return; // Não marca como concluída, tenta de novo no próximo load
                    }
                } catch (err) {
                    console.error('❌ Erro de rede na migração:', err);
                    return; // Não marca como concluída
                }
            }

            // 6. Limpa localStorage e marca como concluída
            chavesParaLimpar.forEach(key => localStorage.removeItem(key));
            localStorage.setItem('migracao_studied_cards_ok', 'true');

            if (paraEnviar.length > 0) {
                console.log(`♻️ ${paraEnviar.length} card(s) migrado(s) e ${chavesParaLimpar.length} chave(s) limpa(s) do localStorage.`);
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">

    <style>
        .header-bg {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto max-w-6xl p-4 sm:p-6 md:p-8 relative">

        <div class="absolute right-4 top-4 z-50">
            <button id="logout-button"
                class="bg-red-500 hover:bg-red-600 text-white text-sm px-3 py-1 rounded-md transition-all duration-300 shadow-md hover:shadow-lg flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Sair
            </button>
        </div>

        <header class="header-bg text-center relative py-8 px-4 rounded-xl shadow-lg mb-8">
            <h1 class="text-2xl sm:text-4xl font-bold mb-3 text-white">
                CONCURSO BOMBEIRO MILITAR DE MG 2026
            </h1>
            <p class="text-white/100 text-xl sm:text-2xl">
                Seu portal para garantir sua vaga no concurso do Bombeiro Militar de MG.
            </p>
        </header>

        <?php if (!$acesso_liberado || ($status_pagamento !== 'approved' && $status_pagamento !== 'ativo')): ?>

            <div
                class="bg-white border-l-4 border-red-500 p-8 mb-8 rounded-r shadow-2xl relative overflow-hidden max-w-3xl mx-auto mt-10">
                <div class="flex flex-col items-center text-center gap-6 relative z-10">
                    <div class="bg-red-100 p-4 rounded-full animate-bounce">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>

                    <div>
                        <h3 class="text-red-800 font-bold text-2xl mb-2">
                            Acesso Bloqueado Temporariamente
                        </h3>
                        <p class="text-gray-700 text-lg">
                            Olá <strong><?php echo htmlspecialchars($nome_aluno); ?></strong>. Identificamos que sua
                            assinatura está pendente.
                        </p>
                        <p class="text-gray-600 mt-2">
                            Para desbloquear as aulas, simulados e o organizador, finalize sua inscrição abaixo.
                        </p>
                    </div>

                    <a href="<?php echo $link_mercado_pago; ?>" target="_blank"
                        class="group bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-8 rounded-full transition-all transform hover:scale-105 shadow-lg flex items-center gap-2 animate-pulse text-lg">
                        <span>LIBERAR MEU ACESSO AGORA</span>
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </a>

                    <p class="text-xs text-gray-400 mt-4">
                        Já realizou o pagamento? O sistema pode levar alguns minutos para atualizar. Tente sair e entrar
                        novamente.
                    </p>
                </div>
                <div class="absolute -right-10 -bottom-10 opacity-5 text-red-900 transform rotate-12 pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-64 w-64" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

        <?php
else: ?>

            <div class="flex flex-col md:flex-row justify-center gap-4 mb-8 max-w-6xl mx-auto">
                <button onclick="registrarEIr('orgEstudos.php', 'Ferramenta', 'Organizador de Estudos')"
                    class="tab-btn px-6 py-4 w-full md:w-auto rounded-xl font-medium text-white border-2 transition-colors text-center shadow-lg flex-1"
                    style="background: linear-gradient(90deg, #1d4ed8 0%, #3b82f6 100%); border-color: #1d4ed8;">
                    🗓️ ORGANIZADOR DE ESTUDOS
                </button>

                <button onclick="registrarEIr('teste_fisico/taf.php', 'Ferramenta', 'Treino TAF')"
                    class="tab-btn px-6 py-4 w-full md:w-auto rounded-xl font-medium text-white border-2 transition-colors text-center shadow-lg flex-1"
                    style="background: linear-gradient(90deg, #d97706 0%, #f59e0b 100%); border-color: #b45309;">
                    🏃 TREINAMENTO PARA O TAF
                </button>

                <button onclick="registrarEIr('meus_resultados.php', 'Ferramenta', 'Resultados')"
                    class="tab-btn px-6 py-4 w-full md:w-auto rounded-xl font-medium text-white border-2 transition-colors text-center shadow-lg flex-1"
                    style="background: linear-gradient(90deg, #1d4ed8 0%, #3b82f6 100%); border-color: #1d4ed8;">
                    📝 MEUS RESULTADOS
                </button>
            </div>

            <div class="flex flex-wrap justify-center items-center gap-4 mt-4 mb-8">
                <div class="flex flex-col items-center">
                    <span class="font-semibold text-slate-700 mb-1">Resumos</span>
                    <canvas id="gauge-Resumo" width="120" height="70" style="max-width:140px;"></canvas>
                </div>
                <div class="flex flex-col items-center">
                    <span class="font-semibold text-slate-700 mb-1">Flashcard</span>
                    <canvas id="gauge-Flashcard" width="120" height="70" style="max-width:140px;"></canvas>
                </div>
                <div class="flex flex-col items-center">
                    <span class="font-semibold text-slate-700 mb-1">Quizzes</span>
                    <canvas id="gauge-Quiz" width="120" height="70" style="max-width:140px;"></canvas>
                </div>
                <div class="flex flex-col items-center">
                    <span class="font-semibold text-slate-700 mb-1">Simulado</span>
                    <canvas id="gauge-Simulado" width="120" height="70" style="max-width:140px;"></canvas>
                </div>
            </div>

            <main class="mt-8">
                <div id="tabs-container" class="grid grid-cols-2 gap-4 mb-8 max-w-lg mx-auto">
                    <button
                        class="tab-btn px-6 py-4 rounded-xl font-medium bg-slate-100 text-slate-800 border-2 border-slate-300 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 transition-colors text-center shadow-md"
                        data-tipo="Resumo">Resumos</button>
                    <button
                        class="tab-btn px-6 py-4 rounded-xl font-medium bg-slate-100 text-slate-800 border-2 border-slate-300 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 transition-colors text-center shadow-md"
                        data-tipo="Flashcard">Flashcard</button>
                    <button
                        class="tab-btn px-6 py-4 rounded-xl font-medium bg-slate-100 text-slate-800 border-2 border-slate-300 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 transition-colors text-center shadow-md"
                        data-tipo="Quiz">Quizzes</button>
                    <button
                        class="tab-btn px-6 py-4 rounded-xl font-medium bg-slate-100 text-slate-800 border-2 border-slate-300 hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 transition-colors text-center shadow-md"
                        data-tipo="Simulado">Simulado</button>
                </div>

                <section class="mb-12">
                    <div class="relative max-w-2xl mx-auto mb-8">
                        <input type="text" id="search-input" placeholder="Buscar por título, assunto ou categoria..."
                            class="w-full p-4 pl-12 rounded-lg border border-slate-300 focus:ring-2 focus:ring-slate-500 focus:border-transparent shadow-sm bg-white/95 text-slate-800 text-base"
                            autocomplete="off">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <span class="text-slate-500 text-xl">🔍</span>
                        </div>
                    </div>

                    <div id="category-filters" class="flex flex-wrap justify-end gap-3 mb-10"></div>

                    <div id="quiz-list" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    </div>

                    <div id="load-more-container" class="text-center mt-6 hidden">
                        <button id="load-more-btn"
                            class="bg-slate-200 hover:bg-slate-300 text-slate-800 font-medium py-2 px-6 rounded-full transition-colors">
                            Carregar mais
                        </button>
                    </div>
                </section>
            </main>

        <?php
endif; ?>
        <footer class="text-center text-slate-500 text-sm mt-16 pb-8">
            <p>© 2025 Método Adrenalina - Bombeiro Militar MG 2026. Todos os direitos reservados.</p>
        </footer>

        <div id="visit-counter"
            style="font-size:10px; opacity:0.35; text-align:center; margin-top:-6px; padding-bottom:8px;">
            Acessos: —
        </div>
    </div>

    <script>
        // --- JAVASCRIPT GERAL ---
        const QUIZZES_PER_PAGE = 15;
        let currentPage = 1;
        let filteredConteudos = [];
        let activeTipo = 'Resumo';

        // Função para criar um card de conteúdo
        function createConteudoCard(conteudo, index) {
            return `
        <div class="quiz-card bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition-shadow group relative cursor-pointer" data-card-index="${index}">
            <button type="button" class="absolute top-4 right-4 px-3 py-1 text-xs rounded-full bg-slate-100 text-slate-600 font-semibold studied-btn" data-card-index="${index}">${conteudo.tipo}</button>
            <div class="card-content" data-path="${conteudo.path}">
                <h2 class="text-lg font-semibold mb-2 text-slate-900">${conteudo.title}</h2>
                <p class="text-slate-700 mb-3">${conteudo.description}</p>
                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>${conteudo.category}</span>
                    <span>${conteudo.lastModified}</span>
                </div>
            </div>
        </div>
    `;
        }

        // Função para rastrear ferramentas externas e depois redirecionar
        function registrarEIr(url, tipo, nome) {
            fetch('api_progresso.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    acao: 'marcar',
                    ref: nome,
                    tipo: tipo
                })
            }).finally(() => {
                window.location.href = url;
            });
        }

        // Função para renderizar os conteúdos
        function renderConteudos() {
            const quizList = document.getElementById('quiz-list');
            const loadMoreContainer = document.getElementById('load-more-container');

            if (!quizList) return;

            quizList.innerHTML = '';

            const endIndex = Math.min(currentPage * QUIZZES_PER_PAGE, filteredConteudos.length);
            const conteudosToShow = filteredConteudos.slice(0, endIndex);

            let listaGlobal = typeof todosConteudos !== 'undefined' ? todosConteudos : (typeof conteudos !== 'undefined' ? conteudos : []);
            const pathTipoToGlobalIndex = {};
            listaGlobal.forEach((c, idx) => {
                pathTipoToGlobalIndex[c.path + '||' + c.tipo] = idx;
            });

            conteudosToShow.forEach((conteudo) => {
                const globalIndex = pathTipoToGlobalIndex[conteudo.path + '||' + conteudo.tipo];
                const conteudoCard = createConteudoCard(conteudo, globalIndex);
                quizList.innerHTML += conteudoCard;
            });

            // SINCRONIZAÇÃO DE ESTADO
            const itensConcluidos = new Set(window.progressoSalvo);

            // 2. ATUALIZAR A LÓGICA DOS BOTÕES E DOS CARDS
            setTimeout(() => {
                document.querySelectorAll('.studied-btn').forEach(btn => {
                    const card = btn.closest('.quiz-card');
                    const cardContent = card.querySelector('.card-content');
                    const path = cardContent.dataset.path;
                    const tipo = btn.textContent.trim();
                    const chaveUnica = path + '||' + tipo;

                    // A. RESTAURAR ESTADO VISUAL
                    if (itensConcluidos.has(chaveUnica)) {
                        card.classList.add('studied');
                        btn.classList.add('bg-green-600', 'text-white');
                        btn.dataset.estudado = "true";
                    } else {
                        card.classList.remove('studied');
                        btn.classList.remove('bg-green-600', 'text-white');
                        btn.dataset.estudado = "false";
                    }

                    // B. CLIQUE NO BOTÃOZINHO (Marcar/Desmarcar)
                    btn.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        const estaEstudado = btn.dataset.estudado === "true";
                        const novaAcao = estaEstudado ? 'desmarcar' : 'marcar';

                        // Atualização visual imediata
                        const isStudiedNow = !estaEstudado;
                        btn.dataset.estudado = isStudiedNow.toString();
                        card.classList.toggle('studied', isStudiedNow);
                        btn.classList.toggle('bg-green-600', isStudiedNow);
                        btn.classList.toggle('text-white', isStudiedNow);

                        // Atualiza variável global (Resolve erro de F5)
                        if (isStudiedNow) {
                            if (!window.progressoSalvo.includes(chaveUnica)) window.progressoSalvo.push(chaveUnica);
                        } else {
                            window.progressoSalvo = window.progressoSalvo.filter(i => i !== chaveUnica);
                        }

                        updateAllStudyGauges(new Set(window.progressoSalvo));

                        fetch('api_progresso.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ acao: novaAcao, ref: path, tipo: tipo })
                        }).catch(err => console.error("Erro ao salvar:", err));
                    };

                    // C. CLIQUE NO CARTÃO (ABRIR A AULA)
                    card.onclick = (e) => {
                        if (e.target.closest('.studied-btn')) return;

                        if (path && path !== '#' && path !== '') {
                            const pathEncoded = encodeURIComponent(path);
                            if (tipo === 'Quiz') window.open('ver_quiz.php?q=' + pathEncoded, '_blank');
                            else if (tipo === 'Resumo') window.open('ver_resumo.php?q=' + pathEncoded, '_blank');
                            else if (tipo === 'Flashcard') window.open('ver_flashcard.php?q=' + pathEncoded, '_blank');
                            else if (tipo === 'Simulado') window.open('ver_simulado.php?q=' + pathEncoded, '_blank');
                            else window.open(path, '_blank');
                        }
                    };
                });

                updateAllStudyGauges(new Set(window.progressoSalvo));

            }, 10);

            if (endIndex < filteredConteudos.length) {
                loadMoreContainer.classList.remove('hidden');
            } else {
                loadMoreContainer.classList.add('hidden');
            }
        }

        // Gráficos de progresso
        const tiposGauges = ['Resumo', 'Flashcard', 'Quiz', 'Simulado'];
        const gaugeCharts = {};

        function updateAllStudyGauges(setItensConcluidos) {
            if (!setItensConcluidos) setItensConcluidos = new Set(window.progressoSalvo);

            tiposGauges.forEach(tipo => {
                const conteudosTipo = (window.todosConteudos || []).filter(c => c.tipo === tipo);
                const total = conteudosTipo.length;
                let studied = 0;

                conteudosTipo.forEach((c) => {
                    if (setItensConcluidos.has(c.path + '||' + c.tipo)) studied++;
                });

                const percent = total > 0 ? Math.round((studied / total) * 100) : 0;
                const canvas = document.getElementById(`gauge-${tipo}`);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                if (!gaugeCharts[tipo]) {
                    gaugeCharts[tipo] = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Estudado', 'Restante'],
                            datasets: [{
                                data: [percent, 100 - percent],
                                backgroundColor: ['#22c55e', '#e5e7eb'],
                                borderWidth: 0, cutout: '70%', circumference: 180, rotation: 270
                            }]
                        },
                        options: { responsive: false, plugins: { legend: { display: false }, tooltip: { enabled: false } } }
                    });
                } else {
                    gaugeCharts[tipo].data.datasets[0].data = [percent, 100 - percent];
                    gaugeCharts[tipo].update();
                }
            });
        }

        // CSS para destacar card estudado
        const styleStudied = document.createElement('style');
        styleStudied.innerHTML = `
            .quiz-card.studied {
                background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%) !important;
                border: 2.5px solid #10b981 !important;
                box-shadow: 0 6px 24px 0 rgba(16, 185, 129, 0.2) !important;
            }
            .studied-btn.bg-green-600 {
                background: #10b981 !important;
                color: white !important;
            }
        `;
        document.head.appendChild(styleStudied);

        function loadMoreConteudos() {
            currentPage++;
            renderConteudos();
        }

        function conteudoCorrespondeCategoria(conteudo, categoria) {
            if (categoria === 'all') return true;
            const mapeamento = {
                'Matemática': ['/Matematica/', 'Matemática'],
                'Direitos_DH': ['/Direito_DH/', 'Direitos Humanos', 'Direito'],
                'Português': ['/Portugues/', 'Português'],                
                'Ciências Humanas': ['/Ciencias_Humanas/', 'Ciências Humanas'],
                'Ciências Naturais': ['/Ciencias_Naturais/', 'Ciências Naturais'],
                'Defesa Civil': ['/Defesa_Civil/', 'Defesa Civil']
            };
            const termos = mapeamento[categoria] || [categoria];
            return termos.some(t => conteudo.category.includes(t) || (conteudo.path && conteudo.path.includes(t)));
        }

        function filterConteudos() {
            const searchInput = document.getElementById('search-input');
            const categoryFilters = document.getElementById('category-filters');
            if (!searchInput || !categoryFilters) return;

            const searchTerm = searchInput.value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.bg-slate-300')?.dataset.category || 'all';

            filteredConteudos = (window.todosConteudos || []).filter(conteudo => {
                if (conteudo.tipo !== activeTipo) return false;
                if (activeFilter !== 'all' && !conteudoCorrespondeCategoria(conteudo, activeFilter)) return false;
                if (searchTerm) {
                    const searchIn = `${conteudo.title} ${conteudo.description} ${conteudo.category}`.toLowerCase();
                    if (!searchIn.includes(searchTerm)) return false;
                }
                return true;
            });

            currentPage = 1;
            renderConteudos();
        }

        const materiasPrincipais = [
            'Português',
            'Matemática',
            'Direitos_DH',
            'Ciências Humanas',
            'Ciências Naturais',
            'Defesa Civil'
        ];
        const estilosMaterias = {
            'Português': 'bg-green-100 text-green-800 hover:bg-green-200 border-green-300',
            'Matemática': 'bg-purple-100 text-purple-800 hover:bg-purple-200 border-purple-300',
            'Direitos_DH': 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200 border-yellow-300',
            'Ciências Humanas': 'bg-orange-100 text-orange-800 hover:bg-orange-200 border-orange-300',
            'Ciências Naturais': 'bg-teal-100 text-teal-800 hover:bg-teal-200 border-teal-300',
            'Defesa Civil': 'bg-red-100 text-red-800 hover:bg-red-200 border-red-300'
        };

        function renderCategoryFilters() {
            let lastCategory = localStorage.getItem('lastCategory_' + activeTipo) || 'all';
            const categoryFilters = document.getElementById('category-filters');
            if (!categoryFilters) return;

            let html = `<div class="grid grid-cols-2 sm:grid-cols-3 gap-3 w-full max-w-2xl mx-auto">
                <button class="filter-btn px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 transition-colors text-slate-800 font-medium text-sm text-center border border-slate-300" data-category="all">Todos</button>`;

            materiasPrincipais.forEach(materia => {
                const estilo = estilosMaterias[materia] || 'bg-slate-100 hover:bg-slate-200 text-slate-800 border-slate-300';
                html += `<button class="filter-btn px-4 py-2 rounded-full font-medium transition-colors text-sm text-center border ${estilo}" data-category="${materia}">${materia}</button>`;
            });
            html += `</div>`;
            categoryFilters.innerHTML = html;

            setTimeout(() => {
                categoryFilters.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('bg-slate-300', 'text-slate-900', 'border-slate-500'));
                const btnToActivate = categoryFilters.querySelector(`[data-category="${lastCategory}"]`);
                if (btnToActivate) btnToActivate.classList.add('bg-slate-300', 'text-slate-900', 'border-slate-500');
                filterConteudos();
            }, 0);
        }

        function showLogoutConfirmation() {
            const modal = document.createElement('div');
            modal.id = 'logout-modal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `<div class="bg-white rounded-lg p-6 max-w-sm mx-4 shadow-xl text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4"><svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg></div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Confirmar Saída</h3><p class="text-sm text-gray-500 mb-6">Tem certeza que deseja sair da área do aluno?</p>
                <div class="flex gap-3 justify-center">
                    <button id="confirm-logout-act" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">Confirmar</button>
                    <button id="cancel-logout-act" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors">Cancelar</button>
                </div></div>`;
            document.body.appendChild(modal);

            document.getElementById('confirm-logout-act').addEventListener('click', () => {
                // Redireciona para o arquivo EXCLUSIVO de logout
                window.location.href = 'logout.php';
            });
            document.getElementById('cancel-logout-act').addEventListener('click', () => { modal.remove(); });
        }

        function initializeEventListeners() {
            // CORREÇÃO CRUCIAL: Só aplica listener de aba se o botão estiver dentro do #tabs-container
            document.querySelectorAll('#tabs-container .tab-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('#tabs-container .tab-btn').forEach(b => {
                        b.classList.remove('bg-blue-600', 'border-blue-600', 'text-white', 'shadow-lg', 'active');
                        b.classList.add('bg-slate-100', 'text-slate-800', 'border-slate-300');
                    });
                    this.classList.remove('bg-slate-100', 'text-slate-800', 'border-slate-300');
                    this.classList.add('bg-blue-600', 'border-blue-600', 'text-white', 'shadow-lg', 'active');
                    activeTipo = this.dataset.tipo;
                    localStorage.setItem('lastActiveTab', activeTipo);
                    renderCategoryFilters();
                });
            });

            const searchInput = document.getElementById('search-input');
            if (searchInput) searchInput.addEventListener('input', filterConteudos);

            const catFilt = document.getElementById('category-filters');
            if (catFilt) catFilt.addEventListener('click', (e) => {
                if (e.target.classList.contains('filter-btn')) {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('bg-slate-300', 'text-slate-900', 'border-slate-500'));
                    e.target.classList.add('bg-slate-300', 'text-slate-900', 'border-slate-500');
                    localStorage.setItem('lastCategory_' + activeTipo, e.target.dataset.category);
                    filterConteudos();
                }
            });

            const lmb = document.getElementById('load-more-btn');
            if (lmb) lmb.addEventListener('click', loadMoreConteudos);

            const logb = document.getElementById('logout-button');
            if (logb) logb.addEventListener('click', (e) => { e.preventDefault(); showLogoutConfirmation(); });
        }

        function activateTab(tabElement) {
            if (!tabElement) return;
            // CORREÇÃO: Limpa estilos apenas dos botões dentro do container
            document.querySelectorAll('#tabs-container .tab-btn').forEach(b => {
                b.classList.remove('bg-blue-600', 'border-blue-600', 'text-white', 'shadow-lg', 'active');
                b.classList.add('bg-slate-100', 'text-slate-800', 'border-slate-300');
            });
            tabElement.classList.remove('bg-slate-100', 'text-slate-800', 'border-slate-300');
            tabElement.classList.add('bg-blue-600', 'border-blue-600', 'text-white', 'shadow-lg', 'active');
            activeTipo = tabElement.dataset.tipo;
        }

        // --- SCRIPT DE MIGRAÇÃO COM DETALHES COMPLETOS ---
        async function migrarSimuladosAntigos() {
            if (localStorage.getItem('migracao_simulados_v2_completa') === 'true') return;

            const historicoLocal = localStorage.getItem('simuladosPerformance');
            if (!historicoLocal) {
                localStorage.setItem('migracao_simulados_v2_completa', 'true');
                return;
            }

            let simulados;
            try { simulados = JSON.parse(historicoLocal); } catch (e) { return; }

            if (!Array.isArray(simulados) || simulados.length === 0) {
                localStorage.setItem('migracao_simulados_v2_completa', 'true');
                return;
            }

            console.log(`🔄 Migrando ${simulados.length} simulados com detalhes...`);

            const converterData = (dataStr) => {
                try {
                    const limpa = dataStr.replace('às', '').trim();
                    const p = limpa.split(/[\s,]+/);
                    const d = p[0].split('/');
                    const h = p[1] ? p[1] : '00:00:00';
                    return `${d[2]}-${d[1]}-${d[0]} ${h}`;
                } catch (e) { return new Date().toISOString().slice(0, 19).replace('T', ' '); }
            };

            let sucessoTotal = true;

            for (const item of simulados) {
                const dataFim = converterData(item.dataRealizacao);
                let dataInicio = dataFim;

                const payload = {
                    simulado_ref: item.simuladoId || 'antigo',
                    titulo: item.simuladoTitulo || 'Simulado Migrado',
                    acertos: item.questoesCorretas || 0,
                    total_questoes: item.questoesTotais || 0,
                    nota: item.percentualAcerto || 0,
                    data_inicio: dataInicio,
                    data_fim: dataFim,
                    tempo_decorrido: item.tempoGasto || '00:00:00',
                    detalhes: item.respostasDetalhadas || []
                };

                try {
                    const baseUrl = window.location.href.split('?')[0].substring(0, window.location.href.split('?')[0].lastIndexOf('/'));
                    const response = await fetch(baseUrl + '/api/salvar_simulado.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json();
                    if (data.success) console.log(`✅ Simulado ${item.simuladoId} salvo com detalhes!`);
                } catch (error) {
                    console.error("Erro na migração:", error);
                    sucessoTotal = false;
                }
            }

            if (sucessoTotal) {
                localStorage.setItem('migracao_simulados_v2_completa', 'true');
                console.log("🎉 Migração Completa Finalizada!");
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initializeEventListeners();

            const lastActiveTab = localStorage.getItem('lastActiveTab') || 'Resumo';

            // CORREÇÃO: Busca apenas botões dentro do container, evitando clicar no "Organizador"
            const tabToActivate = document.querySelector(`#tabs-container .tab-btn[data-tipo="${lastActiveTab}"]`) ||
                document.querySelector('#tabs-container .tab-btn');

            if (tabToActivate) {
                activateTab(tabToActivate);
                activeTipo = tabToActivate.dataset.tipo;
                renderCategoryFilters();
                filterConteudos();
            }

            document.body.classList.add('page-loaded');

            if (typeof migrarSimuladosAntigos === 'function') {
                setTimeout(migrarSimuladosAntigos, 3000);
            }
        });

        const styleTag = document.createElement('style');
        styleTag.textContent = `
            .tab-btn.active { background: #2563eb !important; border-color: #2563eb !important; color: white !important; }
            .quiz-card { opacity: 0; transform: translateY(20px); transition: opacity 0.3s ease, transform 0.3s ease; }
            .page-loaded .quiz-card { opacity: 1; transform: translateY(0); }
            @media (max-width: 640px) { #quiz-list { grid-template-columns: 1fr !important; } }
        `;
        document.head.appendChild(styleTag);
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-6R76EHLX84"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-6R76EHLX84');
    </script>
</body>

</html>