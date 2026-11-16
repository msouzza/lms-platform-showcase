<?php
// ARQUIVO: teste_fisico/taf.php
// 1. CARREGAMENTO INTELIGENTE DO CONFIG
$paths = [
    __DIR__ . '/../libs/config.php',
    __DIR__ . '/../../libs/config.php'
];

$config_loaded = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!defined('DB_HOST')) {
    $db_paths = [__DIR__ . '/../database/conexao.php', __DIR__ . '/../../database/conexao.php'];
    foreach ($db_paths as $db) { if(file_exists($db)) { require_once $db; break; } }
}

// 2. SEGURANÇA E SESSÃO
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../auto_load.php';

if (!isset($_SESSION['user_id'])) {
    if (defined('BASE_URL_ATUAL')) {
        header("Location: " . BASE_URL_ATUAL . "/index.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

// 3. CARREGA DADOS
$aluno_id = $_SESSION['user_id'];
$escola_id = defined('ID_ESCOLA_ATUAL') ? ID_ESCOLA_ATUAL : 1;

try {
    $stmt = $pdo->prepare("SELECT tipo_teste as id, sexo, data_treino as data, resultado as resultadoNumerico 
                           FROM historico_taf 
                           WHERE aluno_id = ? AND escola_id = ? 
                           ORDER BY data_treino ASC");
    $stmt->execute([$aluno_id, $escola_id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $historico = [];
}

foreach ($historico as &$h) {
    $h['resultadoNumerico'] = (float)$h['resultadoNumerico'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhamento TAF – Método Adrenalina</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../styles.css?v=20251115"> 

    <style>
        .test-card { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); }
        .progress-bar-fill { transition: width 0.5s ease-out; }
        .status-badge { font-size: 0.7rem; }
        .header-bg { background-color: #2563eb; background-image: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); }
        .history-table-status { font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; text-align: center; }
        .history-approved { background-color: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .history-reproved { background-color: #fee2e2; color: #991b1b; border: 1px solid #f87171; }
        .btn-voltar-red {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ef4444;
            color: #ffffff !important;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
            border: 1px solid #dc2626;
        }

        .btn-voltar-red:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 600px) {
            .result-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .res-score {
                text-align: left;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 10px;
                border-top: 1px solid #eee;
                padding-top: 10px;
            }

            .tabs-container {
                width: 100%;
                justify-content: space-between;
            }

            .tab-btn {
                flex: 1;
                padding: 10px;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto max-w-6xl p-4 sm:p-6 md:p-8">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="margin:0;"></h1>

            <a href="../area_aluno.php" class="btn-voltar-red">
                Fechar
            </a>
        </div>

        <header class="header-bg text-center relative py-8 px-4 rounded-xl shadow-lg mb-8">
            <h1 class="text-2xl sm:text-4xl font-bold mb-3 text-white">🏃 Treinamento para o Teste Físico (TAF)</h1>
            <p class="text-white/90 text-xl sm:text-2xl">Acompanhe sua evolução e garanta sua aprovação no TAF!</p>
        </header>

        <main class="mt-8">
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                <div class="flex items-center space-x-4 p-3 bg-white rounded-xl shadow-md">
                    <label for="sexo-select" class="font-semibold text-slate-700">Eu sou:</label>
                    <select id="sexo-select" class="p-2 border border-slate-300 rounded-md bg-white text-slate-800 font-medium" onchange="changeGender()">
                        <option value="masculino">Candidato Masculino</option>
                        <option value="feminino">Candidata Feminina</option>
                    </select>
                </div>
                <button onclick="showMinimosModal()" class="p-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl shadow-md transition-colors font-medium flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Ver Índices Mínimos
                </button>
            </div>

            <section class="max-w-xl mx-auto bg-white p-6 rounded-xl shadow-lg mb-10 border-t-4 border-yellow-500">
                <h2 class="text-xl font-bold text-slate-800 mb-4 text-center">Lançar Novo Resultado de Treino Completo</h2>
                <form id="registro-form" class="space-y-4">
                    <label for="data-registro" class="text-sm font-medium text-slate-700 block">Data do Treino:</label>
                    <input type="date" id="data-registro" required class="w-full p-3 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-slate-800 mb-6">
                    <div id="modalidades-inputs" class="space-y-4 border p-4 rounded-lg bg-gray-50 border-gray-200"></div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors shadow-md">Salvar Resultados do Treino</button>
                </form>
            </section>
            
            <section class="max-w-lg mx-auto bg-white p-6 rounded-xl shadow-lg mb-8 border-t-4 border-blue-500">
                <h2 class="text-xl font-bold text-slate-800 mb-4 text-center">Data do Desempenho Ativo</h2>
                <div class="flex items-center justify-center space-x-4">
                    <label for="data-selecionada" class="font-medium text-slate-700">Visualizar Dados do Dia:</label>
                    <input type="date" id="data-selecionada" class="p-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-slate-800" onchange="changeActiveDate(this.value)">
                </div>
            </section>

            <h2 class="text-2xl font-bold text-slate-800 mb-5 text-center">📊 Resumo do Desempenho Atual</h2>
            <section id="acompanhamento-painel" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 mb-10"></section>

            <section class="bg-white p-6 rounded-xl shadow-lg mb-10 border-t-4 border-blue-500">
                <h2 class="text-xl font-bold text-slate-800 mb-4">📈 Evolução no TAF (Gráfico de Progresso)</h2>
                <div class="flex items-center gap-3 mb-4">
                    <label for="grafico-teste-select" class="text-slate-700 font-medium">Visualizar Teste:</label>
                    <select id="grafico-teste-select" class="p-2 border border-slate-300 rounded-md bg-white text-slate-800 font-medium" onchange="renderChart()"></select>
                </div>
                <div class="h-80"><canvas id="evolutionChart"></canvas></div>
            </section>

            <section class="bg-white p-6 rounded-xl shadow-lg border-t-4 border-green-500">
                <h2 class="text-xl font-bold text-slate-800 mb-4">📜 Histórico Consolidado de Resultados</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr id="historico-tabela-header"></tr></thead>
                        <tbody id="historico-tabela-body" class="bg-white divide-y divide-gray-200"></tbody>
                    </table>
                </div>
                <div id="historico-vazio" class="text-center py-6 text-slate-500 hidden">Nenhum resultado de TAF registrado ainda. Comece a lançar seu primeiro treino!</div>
            </section>
        </main>
    </div>
    <footer class="text-center text-slate-500 text-sm mt-16 pb-8"><p> 2025 Choque da Aprovação – Método Adrenalina. Todos os direitos reservados.</p></footer>

    <div id="minimos-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 max-w-4xl w-full shadow-2xl transform transition-all duration-300">
            <h3 class="text-2xl font-bold text-slate-800 mb-4">Índices Mínimos de Aprovação</h3>
            <div id="minimos-content"></div>
            <button onclick="closeMinimosModal()" class="mt-6 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">Fechar</button>
        </div>
    </div>
    <div id="taf-notification" class="fixed top-4 right-4 px-6 py-3 rounded-lg shadow-xl z-50 transition-all duration-300 transform translate-x-full"></div>

<script>
    // --- 1. CONFIGURAÇÃO E DADOS INICIAIS ---
    let localData = <?php echo json_encode($historico); ?>; 

    const INDICES_TAF = {
        masculino: [
            { id: 'barra', nome: 'Barra Fixa', unidade: 'repetições', minimo: 3, formato: 'Repetições (ex: 3, 5, 10)' },
            { id: 'abdominal', nome: 'Abdominal', unidade: 'repetições', minimo: 31, formato: 'Repetições (ex: 31, 40)' },
            { id: 'impulsao', nome: 'Impulsão Horizontal', unidade: 'm', minimo: 1.86, formato: 'Metros (ex: 1,86, 2,05)' },
            { id: 'braco_solo', nome: 'Flexão de Braço (solo)', unidade: 'repetições', minimo: 16, formato: 'Repetições (ex: 16, 25)' },
            { id: 'corrida', nome: 'Corrida de 12 minutos', unidade: 'metros', minimo: 2300, formato: 'Metros (ex: 2300) ou KM (ex: 2,5km)' },
        ],
        feminino: [
            { id: 'barra', nome: 'Barra Fixa Estática', unidade: 'segundos', minimo: 10, formato: 'Segundos (ex: 10, 15)' },
            { id: 'abdominal', nome: 'Abdominal', unidade: 'repetições', minimo: 25, formato: 'Repetições (ex: 25, 30)' },
            { id: 'impulsao', nome: 'Impulsão Horizontal', unidade: 'm', minimo: 1.46, formato: 'Metros (ex: 1,46, 1,70)' },
            { id: 'braco_solo', nome: 'Flexão de Braço (solo)', unidade: 'repetições', minimo: 12, formato: 'Repetições (ex: 12, 18)' },
            { id: 'corrida', nome: 'Corrida de 12 minutos', unidade: 'metros', minimo: 2000, formato: 'Metros (ex: 2000) ou KM (ex: 2,1km)' },
        ]
    };

    let currentGender = localStorage.getItem('taf_gender') || 'masculino';
    let activeDate = localStorage.getItem('taf_active_date') || new Date().toISOString().split('T')[0];
    let evolutionChartInstance = null;

    // --- 2. INTEGRAÇÃO COM A API ---

    function loadResults() {
        return localData; 
    }

    async function saveResultsToApi(novosResultados) {
        try {
            const response = await fetch('../api/api_taf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'salvar', dados: novosResultados })
            });
            const res = await response.json();
            
            if (res.status === 'sucesso') {
                // Adiciona apenas os que foram realmente inseridos
                const inseridos = res.inseridos || novosResultados.length;
                if (inseridos > 0) {
                    localData = [...localData, ...novosResultados];
                }
                return true;
            } else {
                showNotification('Erro ao salvar no servidor: ' + (res.erro || 'Desconhecido'), 'error');
                return false;
            }
        } catch (error) {
            console.error(error);
            showNotification('Erro de conexão com a API.', 'error');
            return false;
        }
    }

    async function deleteDayFromApi(dateToDelete, sexo) {
        try {
            const response = await fetch('../api/api_taf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ acao: 'excluir_dia', data: dateToDelete, sexo: sexo })
            });
            const res = await response.json();
            
            if (res.status === 'sucesso') {
                localData = localData.filter(r => !(r.data === dateToDelete && r.sexo === sexo));
                return true;
            } else {
                showNotification('Erro ao excluir: ' + (res.erro || 'Desconhecido'), 'error');
                return false;
            }
        } catch (error) {
            console.error(error);
            showNotification('Erro de conexão.', 'error');
            return false;
        }
    }

    // --- 3. LÓGICA DE UI E NEGÓCIO ---

    function applyInputMask(event) {
        const input = event.target;
        const testId = input.id.replace('input-', '');
        if (testId === 'impulsao' || (testId === 'corrida' && !input.value.toLowerCase().includes('km'))) {
            let value = input.value.replace(/[^0-9,.]/g, '');
            if (value.includes('.')) value = value.replace('.', ',');
            const parts = value.split(',');
            if (parts.length > 2) value = parts[0] + ',' + parts.slice(1).join('');
            input.value = value;
        }
    }
    
    function parseResultado(teste, resultadoString) {
        if (!resultadoString) return 0;
        const res = resultadoString.trim().toLowerCase().replace(/,/g, '.').replace(/\s/g, '');
        if (teste.id === 'barra' && currentGender === 'feminino') {
            const segundosMatch = res.match(/(\d+)(\.\d+)?\s*(segundos|s|[''"])/);
            if (segundosMatch) return parseFloat(segundosMatch[1] + (segundosMatch[2] || ''));
        }
        if (teste.id === 'corrida' && res.includes('km')) {
            const numMatch = res.match(/[\d\.]+/);
            const num = numMatch ? parseFloat(numMatch[0]) : 0;
            return num * 1000;
        }
        const numMatch = res.match(/[\d\.]+/);
        return numMatch ? parseFloat(numMatch[0]) : 0;
    }

    function formatResultDisplay(teste, resultadoNumerico) {
        if (teste.id === 'impulsao') return `${resultadoNumerico.toFixed(2).replace('.', ',')} m`;
        if (teste.id === 'corrida') {
            if (resultadoNumerico >= 1000) return `${(resultadoNumerico / 1000).toFixed(2).replace(/\.00$/, '').replace('.', ',')} km (${resultadoNumerico.toFixed(0)} m)`;
            return `${resultadoNumerico.toFixed(0)} m`;
        }
        if (teste.id === 'barra' && currentGender === 'feminino') return `${resultadoNumerico.toFixed(0)}s`;
        if (teste.id === 'barra' || teste.id === 'abdominal' || teste.id === 'braco_solo') return `${resultadoNumerico.toFixed(0)}`;
        return String(resultadoNumerico).replace('.', ',');
    }

    function getStatus(teste, resultadoNumerico) {
        if (resultadoNumerico === 0) return { texto: 'Aguardando', class: 'bg-gray-100 text-gray-600', code: 'N/A' };
        const aprovado = resultadoNumerico >= teste.minimo;
        return aprovado 
            ? { texto: '✅ APROVADO', class: 'bg-green-100 text-green-700 font-bold border-green-500', code: 'APROVADO' }
            : { texto: '❌ REPROVADO', class: 'bg-red-100 text-red-700 font-bold border-red-500', code: 'REPROVADA' };
    }
    
    function calculateProgress(teste, resultadoNumerico) {
        if (teste.minimo <= 0) return 100;
        const progress = (resultadoNumerico / teste.minimo) * 100;
        return Math.min(progress, 200); 
    }
    
    function calculateDifference(teste, resultadoNumerico) {
        const diff = resultadoNumerico - teste.minimo;
        if (diff >= 0) return { texto: `+ ${formatResultDisplay(teste, diff)} acima`, class: 'text-green-600' };
        return { texto: `- ${formatResultDisplay(teste, Math.abs(diff))} abaixo`, class: 'text-red-600' };
    }
    
    // --- Renderização ---

    function renderTestCard(teste, latestResult, bestResult) {
        const bestParsed = bestResult ? bestResult.resultadoNumerico : 0;
        const status = getStatus(teste, bestParsed);
        const progress = calculateProgress(teste, bestParsed);
        const difference = calculateDifference(teste, bestParsed);
        const indiceRef = formatResultDisplay(teste, teste.minimo);
        
        return `
            <div class="test-card bg-white rounded-xl p-5 border-l-4 ${status.class.includes('border-green') ? 'border-green-500' : 'border-red-500'}">
                <h3 class="text-base font-bold text-slate-900 mb-1 leading-tight">${teste.nome}</h3>
                <p class="text-xs text-slate-500 mb-3">Mínimo: <strong>${indiceRef}</strong></p>
                <div class="mb-3">
                    <p class="text-sm text-slate-700">Melhor Marca:</p>
                    <p class="text-xl font-extrabold ${bestParsed >= teste.minimo ? 'text-green-600' : 'text-red-600'}">
                        ${bestResult ? formatResultDisplay(teste, bestParsed) : 'N/A'}
                    </p>
                    <span class="status-badge inline-block mt-1 px-2 py-0.5 rounded-full ${status.class.split(' ').slice(0, 2).join(' ')}">${status.texto}</span>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-slate-700 mb-1">Resultado (Melhor):</p>
                    <p class="text-sm font-semibold ${difference.class}">${difference.texto}</p>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="progress-bar-fill h-2.5 rounded-full ${bestParsed >= teste.minimo ? 'bg-green-500' : 'bg-blue-500'}" style="width: ${progress}%"></div>
                </div>
                <p class="text-xs text-slate-500 mt-1 text-right">${Math.round(progress)}% do Mínimo</p>
            </div>
        `;
    }

    function renderFormInputs(testes) {
        const container = document.getElementById('modalidades-inputs');
        container.innerHTML = ''; 
        let html = '';
        testes.forEach(teste => {
            const minimoFormatado = formatResultDisplay(teste, teste.minimo);
            const placeholder = `Mínimo: ${minimoFormatado}. Formato: ${teste.formato}`;
            html += `
                <div>
                    <label for="input-${teste.id}" class="text-sm font-semibold text-slate-700 block mb-1">${teste.nome}:</label>
                    <input type="text" id="input-${teste.id}" name="${teste.id}" placeholder="${placeholder}"
                        class="w-full p-2 border border-slate-300 rounded-lg focus:ring-yellow-500 focus:border-yellow-500 text-slate-800"
                        autocomplete="off" oninput="applyInputMask(event)">
                </div>`;
        });
        container.innerHTML = html;
    }

    function changeActiveDate(newDate) {
        activeDate = newDate;
        localStorage.setItem('taf_active_date', activeDate); 
        renderAll();
    }

    function renderAll() {
        const testes = INDICES_TAF[currentGender];
        const allResults = loadResults();
        const painel = document.getElementById('acompanhamento-painel');
        const selectGrafico = document.getElementById('grafico-teste-select');
        
        painel.innerHTML = '';
        if (selectGrafico.children.length === 0) {
            testes.forEach(teste => selectGrafico.innerHTML += `<option value="${teste.id}">${teste.nome}</option>`);
        }

        testes.forEach(teste => {
            const resultsForActiveDate = allResults
                .filter(r => r.id === teste.id && r.sexo === currentGender && r.data === activeDate)
                .sort((a, b) => new Date(b.data) - new Date(a.data)); 
            
            let bestResult = null;
            if (resultsForActiveDate.length > 0) {
                bestResult = resultsForActiveDate.reduce((max, current) => (current.resultadoNumerico > max.resultadoNumerico ? current : max), resultsForActiveDate[0]);
            }
            const latestResult = resultsForActiveDate.length > 0 ? resultsForActiveDate[0] : null;
            painel.innerHTML += renderTestCard(teste, latestResult, bestResult);
        });
        
        renderFormInputs(testes); 
        const currentSelected = selectGrafico.value;
        if (!currentSelected && selectGrafico.children.length > 0) selectGrafico.selectedIndex = 0;

        renderHistoryTable(allResults);
        renderChart(); 
    }

    function renderHistoryTable(allResults) {
        const tabelaHead = document.getElementById('historico-tabela-header');
        const tabelaBody = document.getElementById('historico-tabela-body');
        const historicoVazio = document.getElementById('historico-vazio');
        tabelaHead.innerHTML = '';
        tabelaBody.innerHTML = '';

        const testesMeta = INDICES_TAF[currentGender];
        const resultsByDate = allResults.filter(r => r.sexo === currentGender).sort((a, b) => new Date(b.data) - new Date(a.data));
        
        const consolidatedHistory = {};
        resultsByDate.forEach(r => {
            if (!consolidatedHistory[r.data]) consolidatedHistory[r.data] = { date: r.data, results: {} };
            if (!consolidatedHistory[r.data].results[r.id] || r.resultadoNumerico > consolidatedHistory[r.data].results[r.id].resultadoNumerico) {
                    consolidatedHistory[r.data].results[r.id] = r;
            }
        });

        const historyDates = Object.keys(consolidatedHistory).sort((a, b) => new Date(b) - new Date(a));

        if (historyDates.length === 0) {
            historicoVazio.classList.remove('hidden');
            return;
        } else {
            historicoVazio.classList.add('hidden');
        }

        let headerHtml = `<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>`;
        testesMeta.forEach(t => headerHtml += `<th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">${t.nome.replace(' Fixa', '').replace(' Horizontal', '').replace(' de 12 minutos', '').replace('(solo)', '')}</th>`);
        headerHtml += `<th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>`;
        tabelaHead.innerHTML = headerHtml;

        let bodyHtml = '';
        historyDates.forEach((date, index) => {
            const dayData = consolidatedHistory[date];
            const rowClass = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
            let row = `<tr class="hover:bg-gray-100 ${rowClass}"><td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-medium">${date}</td>`;

            testesMeta.forEach(testeMeta => {
                const result = dayData.results[testeMeta.id];
                let resultCell = `<td class="px-4 py-3 text-center text-sm">`;
                if (result) {
                    const status = getStatus(testeMeta, result.resultadoNumerico);
                    const resultDisplay = formatResultDisplay(testeMeta, result.resultadoNumerico).replace(/(\s*\(.*\))|(\s*(repetições|m|s))/, '');
                    resultCell += `<div class="font-semibold text-slate-800">${resultDisplay}</div><span class="history-table-status ${status.code === 'APROVADO' ? 'history-approved' : 'history-reproved'} mt-1 inline-block">${status.code}</span>`;
                } else {
                    resultCell += `<div class="text-gray-400">N/A</div>`;
                }
                resultCell += `</td>`;
                row += resultCell;
            });
            
            row += `<td class="px-4 py-3 whitespace-nowrap text-center text-sm font-medium">
                        <button onclick="deleteDayResults('${date}', '${currentGender}')" title="Excluir" class="text-red-600 hover:text-red-900 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </td></tr>`;
            bodyHtml += row;
        });
        tabelaBody.innerHTML = bodyHtml;
    }

    // --- Ações Principais ---

    async function deleteDayResults(dateToDelete, sexo) {
        if (!confirm(`Excluir TODOS os resultados de ${dateToDelete}?`)) return;
        const success = await deleteDayFromApi(dateToDelete, sexo);
        if (success) {
            if (dateToDelete === activeDate) {
                activeDate = new Date().toISOString().split('T')[0];
                localStorage.setItem('taf_active_date', activeDate);
                document.getElementById('data-selecionada').value = activeDate;
            }
            renderAll();
            showNotification(`Resultados de ${dateToDelete} excluídos.`, 'info');
        }
    }

    function renderChart() {
        const selectedTestId = document.getElementById('grafico-teste-select').value;
        const allResults = loadResults();
        const testeMeta = INDICES_TAF[currentGender].find(t => t.id === selectedTestId);
        
        if (!testeMeta || !selectedTestId) { if (evolutionChartInstance) evolutionChartInstance.destroy(); return; }

        const dados = allResults.filter(r => r.id === selectedTestId && r.sexo === currentGender).sort((a, b) => new Date(a.data) - new Date(b.data)); 
        const labels = dados.map(r => r.data);
        const resultados = dados.map(r => r.resultadoNumerico);
        
        if (dados.length === 0) { if (evolutionChartInstance) evolutionChartInstance.destroy(); return; }
        
        const linhaMinimo = new Array(dados.length).fill(testeMeta.minimo);
        const data = {
            labels: labels,
            datasets: [
                { label: 'Seu Desempenho', data: resultados, borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.1)', tension: 0.3, fill: true, pointRadius: 5 },
                { label: `Mínimo de Aprovação`, data: linhaMinimo, borderColor: '#dc2626', backgroundColor: 'transparent', borderDash: [5, 5], pointRadius: 0 }
            ]
        };

        const config = {
            type: 'line', data: data,
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { type: 'time', time: { unit: 'day', tooltipFormat: 'dd/MM/yyyy' }, title: { display: true, text: 'Data do Treino' } },
                    y: { beginAtZero: true, title: { display: true, text: `${testeMeta.nome} (${testeMeta.unidade})` }, ticks: { callback: function(value) { return formatResultDisplay(testeMeta, value); } } }
                },
                plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: function(context) { return `${context.dataset.label}: ${formatResultDisplay(testeMeta, context.parsed.y)}`; } } } }
            }
        };

        if (evolutionChartInstance) evolutionChartInstance.destroy();
        const ctx = document.getElementById('evolutionChart').getContext('2d');
        evolutionChartInstance = new Chart(ctx, config);
    }

    function changeGender() {
        currentGender = document.getElementById('sexo-select').value;
        localStorage.setItem('taf_gender', currentGender);
        document.getElementById('grafico-teste-select').innerHTML = ''; 
        renderAll();
        showNotification(`Modo alterado para ${currentGender === 'masculino' ? 'Masculino' : 'Feminina'}.`, 'info');
    }

    // Modal Mínimos
    function showMinimosModal() {
        const modal = document.getElementById('minimos-modal');
        const content = document.getElementById('minimos-content');
        const testes = INDICES_TAF[currentGender];
        const generoTexto = currentGender === 'masculino' ? 'Masculino' : 'Feminino';
        let tableHtml = `<h4 class="text-xl font-medium mb-3">${generoTexto}</h4><table class="min-w-full divide-y divide-gray-200 border border-gray-200"><thead class="bg-gray-100"><tr><th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Teste</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Índice Mínimo</th><th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Formato</th></tr></thead><tbody class="bg-white divide-y divide-gray-200">`;
        testes.forEach(t => tableHtml += `<tr><td class="px-4 py-3 text-sm font-medium text-gray-900">${t.nome}</td><td class="px-4 py-3 text-sm text-red-600 font-bold">${formatResultDisplay(t, t.minimo)}</td><td class="px-4 py-3 text-sm text-gray-500">${t.formato}</td></tr>`);
        tableHtml += `</tbody></table>`;
        content.innerHTML = tableHtml;
        modal.classList.remove('hidden');
    }
    function closeMinimosModal() { document.getElementById('minimos-modal').classList.add('hidden'); }

    // Notificação
    function showNotification(message, type = 'success') {
        const n = document.getElementById('taf-notification');
        const colors = { success: 'bg-green-600 text-white', error: 'bg-red-600 text-white', info: 'bg-blue-600 text-white' };
        n.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-xl z-50 transition-all duration-300 transform translate-x-full ${colors[type] || colors.success}`;
        n.textContent = message;
        setTimeout(() => n.classList.remove('translate-x-full'), 100);
        setTimeout(() => n.classList.add('translate-x-full'), 3000);
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        
        document.getElementById('data-registro').value = new Date().toISOString().split('T')[0];
        const dtSel = document.getElementById('data-selecionada');
        if (dtSel) dtSel.value = activeDate;
        document.getElementById('sexo-select').value = currentGender;

        document.getElementById('registro-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const dataRegistro = document.getElementById('data-registro').value;
            if (!dataRegistro) { showNotification('Selecione a data.', 'error'); return; }
            
            const testes = INDICES_TAF[currentGender];
            let novosResultados = [];
            
            testes.forEach(t => {
                const inputEl = document.getElementById(`input-${t.id}`);
                const raw = inputEl ? inputEl.value.trim() : '';
                const valor = parseResultado(t, raw);
                if (raw !== '' && valor >= 0) {
                    novosResultados.push({ id: t.id, sexo: currentGender, data: dataRegistro, resultadoNumerico: valor });
                }
            });

            if (novosResultados.length === 0) { showNotification('Informe ao menos um resultado.', 'error'); return; }

            const sucesso = await saveResultsToApi(novosResultados);
            if (sucesso) {
                activeDate = dataRegistro;
                localStorage.setItem('taf_active_date', activeDate);
                if (dtSel) dtSel.value = activeDate;
                testes.forEach(t => { const el = document.getElementById(`input-${t.id}`); if (el) el.value = ''; });
                renderAll();
                showNotification(`Resultados de ${dataRegistro} salvos!`, 'success');
            }
        });
        
        // --- ROTINA DE MIGRAÇÃO AUTOMÁTICA (LocalStorage -> Banco) ---
        (async function checkMigration() {
            if (localStorage.getItem('migracao_taf_concluida') === 'true') return;

            const oldData = JSON.parse(localStorage.getItem('taf_results') || '[]');
            
            if (oldData.length > 0) {
                console.log("🔄 Migrando dados do LocalStorage para o Banco...");
                
                // CORREÇÃO: Filtra apenas registros que NÃO existem no banco
                const novos = oldData.filter(old => {
                    return !localData.some(db => 
                        db.id === old.id && 
                        db.sexo === old.sexo && 
                        db.data === old.data && 
                        parseFloat(db.resultadoNumerico) === parseFloat(old.resultadoNumerico)
                    );
                });

                if (novos.length > 0) {
                    const sucesso = await saveResultsToApi(novos);
                    if (sucesso) {
                        localStorage.setItem('migracao_taf_concluida', 'true');
                        localStorage.removeItem('taf_results');
                        showNotification(`♻️ ${novos.length} registro(s) migrado(s) para a nuvem!`, 'info');
                        setTimeout(() => window.location.reload(), 1500);
                        return;
                    }
                } else {
                    // Tudo já existe no banco, marca como concluído
                    localStorage.setItem('migracao_taf_concluida', 'true');
                    localStorage.removeItem('taf_results');
                    console.log("✅ Migração TAF: todos os dados já existiam no banco.");
                }
            } else {
                localStorage.setItem('migracao_taf_concluida', 'true');
            }
        })();

        renderAll();
    });
</script>
</body>
</html>