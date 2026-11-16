<?php
// ARQUIVO: area-do-aluno/orgEstudos.php
// Objetivo: Organizador de Estudos com Lógica "Adrenalina" (Fases 1-4) + Banco de Dados + Nuvem

// 1. Carrega Sessão e Configurações (Caminhos Corrigidos)
$paths = [__DIR__ . '/../libs/config.php', __DIR__ . '/../../libs/config.php'];
foreach ($paths as $path) { if (file_exists($path)) { require_once $path; break; } }

require_once __DIR__ . '/../database/conexao.php';

// 2. Segurança e Redirecionamento
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    $url_login = defined('BASE_URL_ATUAL') ? BASE_URL_ATUAL . '/index.php' : '../index.php';
    header("Location: " . $url_login);
    exit;
}

if (isset($_SESSION['status_pagamento']) && $_SESSION['status_pagamento'] !== 'approved' && $_SESSION['status_pagamento'] !== 'ativo') {
    header("Location: area_aluno.php"); 
    exit;
}

$alunoId = $_SESSION['user_id'];
$escolaId = defined('ID_ESCOLA_ATUAL') ? ID_ESCOLA_ATUAL : 1;

// 3. Busca progresso do Banco de Dados (FILTRADO POR ESCOLA)
$topicosConcluidos = [];
$diasPuladosDB = [];
$configOrgDB = "null";
$jsonHexFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

try {
    // A. Busca Tópicos Concluídos
    $stmt = $pdo->prepare("SELECT conteudo_ref FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ? AND tipo_conteudo = 'Organizador'");
    $stmt->execute([$alunoId, $escolaId]);
    $topicosConcluidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // B. Busca Dias Pulados
    $stmtDias = $pdo->prepare("SELECT conteudo_ref FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ? AND tipo_conteudo = 'DiaPulado'");
    $stmtDias->execute([$alunoId, $escolaId]);
    $diasPuladosDB = $stmtDias->fetchAll(PDO::FETCH_COLUMN);

    // C. Busca Configurações Salvas (decodifica e re-serializa com escape de tags HTML contra Stored XSS)
    $stmtConfig = $pdo->prepare("SELECT conteudo_ref FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ? AND tipo_conteudo = 'ConfigOrg' LIMIT 1");
    $stmtConfig->execute([$alunoId, $escolaId]);
    $configResult = $stmtConfig->fetchColumn();
    if ($configResult) {
        $decodedConfig = json_decode($configResult, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
            $configOrgDB = json_encode($decodedConfig, $jsonHexFlags);
        }
    }
} catch (Exception $e) { }

$topicosConcluidosJson = json_encode($topicosConcluidos, $jsonHexFlags);
$diasPuladosJson = json_encode($diasPuladosDB, $jsonHexFlags);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizador de Estudos - Método Adrenalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="conteudos.js?v=20251115"></script>
    <script>
        // Carregamento de segurança dos conteúdos
        (function(){
            if (typeof window.todosConteudos === 'undefined') {
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
            }
        })();
    </script>
    <style>
        .header-bg { background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); }
        .study-day-card {
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06);
            border: 2px solid transparent;
        }
        .study-day-card:hover:not(.skipped) {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
        }
        .skipped {
            opacity: 0.5;
            background-color: #fca5a5 !important;
            border-color: #ef4444 !important;
            box-shadow: none;
            text-decoration: line-through;
            transform: none;
        }
        .skipped .topic-list, .skipped .daily-tip, .skipped .revision-box { color: #7f1d1d !important; }
        .completed {
            background-color: #d1fae5 !important;
            border-color: #10b981 !important;
            opacity: 1;
        }
        .completed .topic-list, .completed .daily-tip, .completed .revision-box { color: #065f46 !important; }
        .revision-box { background: #fffae0; border-left-color: #f59e0b; }
        /* =========================================
           BOTÃO PADRÃO DE VOLTAR (VERMELHO)
           ========================================= */
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

<body class="bg-gray-50 p-4 font-sans">
    <div class="container mx-auto max-w-6xl p-4 sm:p-8 bg-white rounded-xl shadow-2xl mt-4">
        
        <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:20px;">
            <a href="area_aluno.php" class="btn-voltar-red">
                Fechar
            </a>
        </div>
        
        <header class="header-bg text-center relative py-6 px-4 rounded-xl shadow-lg mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold mb-2 text-white">🗓️ Organizador de Estudos Adrenalina</h1>
            <p class="text-white/90 text-lg">Monte seu plano de estudos dividindo os tópicos na reta final.</p>
        </header>

        <main>
            <section class="mb-10 p-6 bg-blue-50 border-t-4 border-blue-600 rounded-lg shadow-inner">
                <h2 class="text-xl font-semibold text-blue-800 mb-4">1. Defina o Período de Estudo</h2>
                <div class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1 w-full">
                        <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">Data de Início:</label>
                        <input type="date" id="startDate" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                    </div>
                    <div class="flex-1 w-full">
                        <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">Data Final:</label>
                        <input type="date" id="endDate" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                    </div>
                    <div class="flex-1 w-full">
                        <label for="hoursPerDay" class="block text-sm font-medium text-gray-700 mb-1">Tempo disponível por dia:</label>
                        <select id="hoursPerDay" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-shadow">
                            <option value="1">1 hora (2 tópicos/dia)</option>
                            <option value="2">2 horas (3 tópicos/dia)</option>
                            <option value="3" selected>3+ horas (4 tópicos/dia)</option>
                        </select>
                    </div>
                    <button id="generatePlanBtn" class="w-full md:w-auto px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-colors transform hover:scale-[1.02]">
                        Gerar Plano de Estudos
                    </button>
                </div>
                <div id="planInfo" class="mt-4 p-3 rounded-lg text-sm text-gray-700 hidden"></div>
            </section>

            <section id="studyPlanSection" class="mb-10 hidden">
                <h2 class="text-xl font-semibold text-gray-700 border-b pb-2 mb-6">2. Seu Plano Dia a Dia</h2>
                <div id="studyDaysContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"></div>
                <div id="noDaysMsg" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded mt-6">
                    Seu período de estudos é inválido ou muito curto. Por favor, ajuste as datas.
                </div>
            </section>

            <div class="fixed bottom-4 right-4 bg-yellow-500 text-white p-3 rounded-lg shadow-xl text-sm font-medium" id="flashcardReminder" style="z-index: 10;">
                <span role="img" aria-label="Lâmpada">💡</span> Lembrete Diário: Revise seus Flashcards!
            </div>
        </main>

    </div>

    <script>
        // --- 1. INTEGRAÇÃO COM BANCO DE DADOS (PHP -> JS) ---
        let dbCompletedTopics = new Set();
        let dbSkippedDays = new Set();
        const configDoBanco = <?php echo $configOrgDB; ?>;

        try {
            const dadosPhp = '<?php echo isset($topicosConcluidosJson) ? $topicosConcluidosJson : "[]"; ?>';
            if (dadosPhp.startsWith('[')) dbCompletedTopics = new Set(JSON.parse(dadosPhp));
            
            const diasPhp = '<?php echo isset($diasPuladosJson) ? $diasPuladosJson : "[]"; ?>';
            if (diasPhp.startsWith('[')) dbSkippedDays = new Set(JSON.parse(diasPhp));
        } catch (e) { console.error("Erro DB:", e); }

        // --- 2. CONFIGURAÇÕES GERAIS ---
        let currentPlan = [];
        let allTopicsBySubject = {};
        const FLASHCARD_TIP = "DICA: Revise todos os seus Flashcards marcados como 'precisa de atenção' hoje!";
        const REVISION_ACTIVE_DAYS_INTERVAL = 7;

        function getTopicsPerDayByHours(hours) {
            switch(parseInt(hours)) {
                case 1: return 2;
                case 2: return 3;
                case 3: return 4;
                default: return 4;
            }
        }

        function getDesiredTopicsPerDay() {
            const sel = document.getElementById('hoursPerDay');
            return getTopicsPerDayByHours(sel ? sel.value : '3');
        }

        // --- 3. PERSISTÊNCIA NA NUVEM & MIGRAÇÃO LOCALSTORAGE ---
        async function migrateLocalStorage() {
            let migrated = false;
            
            // 1. Migra configurações
            const localSettings = localStorage.getItem('adrenalinaStudySettings');
            if (localSettings && (!configDoBanco || configDoBanco === "null")) {
                try {
                    const parsedSettings = JSON.parse(localSettings);
                    await fetch('api_progresso.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({acao: 'salvar_config_org', config: parsedSettings})
                    });
                    if (parsedSettings.startDate) document.getElementById('startDate').value = parsedSettings.startDate;
                    if (parsedSettings.endDate) document.getElementById('endDate').value = parsedSettings.endDate;
                    if (parsedSettings.hoursPerDay) document.getElementById('hoursPerDay').value = parsedSettings.hoursPerDay;
                    migrated = true;
                } catch(e) {}
            }

            // 2. Migra dias pulados e TÓPICOS CONCLUÍDOS
            const localPlan = localStorage.getItem('adrenalinaStudyPlan');
            if (localPlan) {
                try {
                    const parsedPlan = JSON.parse(localPlan);
                    const dadosParaMigrar = [];

                    parsedPlan.forEach(day => {
                        // A. Migra os dias pulados
                        if (day.isSkipped) {
                            const dateParts = day.formattedDate.split('/');
                            const dateForDb = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
                            if (!dbSkippedDays.has(dateForDb)) {
                                dadosParaMigrar.push({ ref: dateForDb, tipo: 'DiaPulado' });
                                dbSkippedDays.add(dateForDb);
                            }
                        }
                        // B. CORREÇÃO: Migra os tópicos que o aluno já tinha concluído
                        if (day.isCompleted && day.topicsStudied) {
                            day.topicsStudied.forEach(topic => {
                                if (!dbCompletedTopics.has(topic.id)) {
                                    dadosParaMigrar.push({ ref: topic.id, tipo: 'Organizador' });
                                    dbCompletedTopics.add(topic.id);
                                }
                            });
                        }
                    });

                    // Envia tudo para o banco de uma vez
                    if (dadosParaMigrar.length > 0) {
                        await fetch('api_progresso.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ acao: 'sincronizar', lista: dadosParaMigrar })
                        });
                        migrated = true;
                    }
                } catch(e) {}
            }

            // Limpeza
            if (localSettings) localStorage.removeItem('adrenalinaStudySettings');
            if (localPlan) localStorage.removeItem('adrenalinaStudyPlan');
            
            return migrated;
        }

        async function saveUserSettings() {
            const payload = {
                startDate: document.getElementById('startDate').value,
                endDate: document.getElementById('endDate').value,
                hoursPerDay: document.getElementById('hoursPerDay').value
            };
            try {
                await fetch('api_progresso.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({acao: 'salvar_config_org', config: payload})
                });
            } catch(e) { console.error("Erro ao salvar config no banco", e); }
        }

        function applySavedState(plan) {
            return plan.map(day => {
                const dateParts = day.formattedDate.split('/');
                const dateForDb = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;
                if (dbSkippedDays.has(dateForDb)) {
                    day.isSkipped = true;
                }
                return day;
            });
        }

        function prepareAllTopics() {
            // DADOS FIXOS COM OS NÚMEROS EXATOS ESPECIFICADOS
            const topicsData = [
                { subject: 'Português', count: 20 },
                { subject: 'Matemática', count: 10 },
                { subject: 'Ciências Humanas', count: 20},
                { subject: 'Ciências da Natureza', count: 18 },
                { subject: 'Defesa Civil', count: 8 },
                { subject: 'Direitos Humanos', count: 10 },
            ];

            let rawTopics = [];
            topicsData.forEach(data => {
                for (let i = 1; i <= data.count; i++) {
                    rawTopics.push({
                        subject: data.subject,
                        originalTitle: `${i}`,
                        id: `${data.subject.substring(0, 3).toUpperCase()}-${i}`,
                        order: i
                    });
                }
            });

            const topicsBySubject = {};
            rawTopics.forEach(topic => {
                const subject = topic.subject;
                topicsBySubject[subject] = topicsBySubject[subject] || [];
                topicsBySubject[subject].push(topic);
            });
            return topicsBySubject;
        }

        function formatDate(date) {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }

        // --- 4. ENGINE: GERAÇÃO DO PLANO ---
        function generatePlan(shouldRecalculate = false) {
            const startDateStr = document.getElementById('startDate').value;
            const endDateStr = document.getElementById('endDate').value;
            const studyPlanSection = document.getElementById('studyPlanSection');
            
            document.getElementById('planInfo').classList.add('hidden');
            studyPlanSection.classList.add('hidden');
            document.getElementById('noDaysMsg').classList.add('hidden');

            if (!startDateStr || !endDateStr) return;

            const startDate = new Date(startDateStr + 'T00:00:00');
            const endDate = new Date(endDateStr + 'T23:59:59');

            if (startDate >= endDate) {
                document.getElementById('noDaysMsg').textContent = "Data final inválida.";
                document.getElementById('noDaysMsg').classList.remove('hidden');
                return;
            }

            allTopicsBySubject = prepareAllTopics();
            let newPlan = [];
            let currentDate = new Date(startDate);

            while (currentDate <= endDate) {
                newPlan.push({
                    date: new Date(currentDate),
                    formattedDate: formatDate(currentDate),
                    isSkipped: false,
                    isCompleted: false,
                    topics: {},
                    revisions: [],
                    simulado: null
                });
                currentDate.setDate(currentDate.getDate() + 1);
            }

            if (newPlan.length === 0) return;

            // Aplica os dias pulados vindos do banco de dados
            newPlan = applySavedState(newPlan);
            currentPlan = newPlan;
            studyPlanSection.classList.remove('hidden');
            
            calculateAndRenderPlan(currentPlan);
        }

        // --- 5. O CORAÇÃO DO SISTEMA ---
  // --- 5. O CORAÇÃO DO SISTEMA (Lógica Blindada - Âncora Fixa) ---
        function calculateAndRenderPlan(plan) {
            const totalDays = plan.length;
            const simuladoCount = Math.min(5, totalDays);
            
            // 1. FIXA OS SIMULADOS NOS ÚLTIMOS 5 DIAS DO CALENDÁRIO (Independente de pulos)
            const simuladoIndices = [];
            for (let i = totalDays - simuladoCount; i < totalDays; i++) {
                simuladoIndices.push(i);
            }

            const studyDaysContainer = document.getElementById('studyDaysContainer');
            studyDaysContainer.innerHTML = '';

            // 2. Limpeza TOTAL da memória
            plan.forEach(day => {
                day.topics = {};
                day.revisions = [];
                day.simulado = null;
            });

            // 3. SEPARAÇÃO ABSOLUTA: Dias de Estudo vs Dias de Simulado
            const activeStudyDaysIndices = [];
            const activeSimuladoDaysIndices = [];

            plan.forEach((day, index) => {
                if (simuladoIndices.includes(index)) {
                    // É um dia de Simulado fixo na reta final
                    if (!day.isSkipped) activeSimuladoDaysIndices.push(index);
                    
                    const simuladoNumber = simuladoIndices.indexOf(index) + 1;
                    day.simulado = {
                        id: `SIM-${simuladoNumber}`, 
                        title: `Simulado ${simuladoNumber}`,
                        subject: 'Simulado'
                    };
                } else {
                    // É um dia normal de estudo
                    if (!day.isSkipped) activeStudyDaysIndices.push(index);
                }
            });

            const topicsPerDay = getDesiredTopicsPerDay();
            const subjects = ['Português', 'Matemática', 'Ciências Humanas', 'Ciências da Natureza', 'Defesa Civil', 'Direitos Humanos'];

            // =================================================================
            // FASE 1: Distribuir tópicos originais (APENAS nos dias de estudo ativos)
            // =================================================================
            if (activeStudyDaysIndices.length > 0) {
                let remainingTopics = {};
                subjects.forEach(subject => {
                    remainingTopics[subject] = [...(allTopicsBySubject[subject] || [])];
                });

                let currentSubjectIndex = 0;
                let currentDayIndex = 0;

                const countTopicsInDay = (day) => Object.values(day.topics).reduce((total, list) => total + list.length, 0);
                const hasRemainingTopics = () => Object.values(remainingTopics).some(arr => arr.length > 0);

                while (hasRemainingTopics() && currentDayIndex < activeStudyDaysIndices.length * 10) {
                    const simplePlanIndex = activeStudyDaysIndices[currentDayIndex % activeStudyDaysIndices.length];
                    const day = plan[simplePlanIndex];
                    
                    if (countTopicsInDay(day) < topicsPerDay) {
                        let topicAdded = false;
                        for (let i = 0; i < subjects.length; i++) {
                            const subjectIndex = (currentSubjectIndex + i) % subjects.length;
                            const subject = subjects[subjectIndex];
                            if (remainingTopics[subject].length > 0 && countTopicsInDay(day) < topicsPerDay) {
                                const topic = remainingTopics[subject].shift();
                                day.topics[subject] = day.topics[subject] || [];
                                day.topics[subject].push({ ...topic, pass: 1 });
                                topicAdded = true;
                                currentSubjectIndex = (subjectIndex + 1) % subjects.length;
                                break;
                            }
                        }
                        if (!topicAdded) currentDayIndex++;
                    } else {
                        currentDayIndex++;
                    }
                    if (currentDayIndex > activeStudyDaysIndices.length * 100) break;
                }

                // =================================================================
                // FASE 2: Revisões para preencher buracos (Apenas dias de estudo ativos)
                // =================================================================
                const totalCapacity = activeStudyDaysIndices.length * topicsPerDay;
                let totalDistributed = 0;
                activeStudyDaysIndices.forEach(idx => totalDistributed += countTopicsInDay(plan[idx]));

                if (totalCapacity > totalDistributed) {
                    let allTopicsForReview = [];
                    subjects.forEach(subject => {
                        allTopicsForReview.push(...(allTopicsBySubject[subject] || []).map(t => ({ ...t, subject })));
                    });

                    let reviewIndex = 0;
                    for (let i = 0; i < activeStudyDaysIndices.length; i++) {
                        const day = plan[activeStudyDaysIndices[i]];
                        const remaining = topicsPerDay - countTopicsInDay(day);
                        for (let j = 0; j < remaining; j++) {
                            const topic = allTopicsForReview[reviewIndex % allTopicsForReview.length];
                            day.topics[topic.subject] = day.topics[topic.subject] || [];
                            day.topics[topic.subject].push({ ...topic, pass: 2, isRevision: true });
                            reviewIndex++;
                        }
                    }
                }
            }

            // =================================================================
            // FASE 3: Verificação de Conclusão e Revisão Programada
            // =================================================================
            plan.forEach((day, index) => {
                if (day.isSkipped) return; 

                const dayTopics = [...Object.values(day.topics).flat(), ...day.revisions];
                if (day.simulado && day.simulado.id) {
                    dayTopics.push(day.simulado);
                }

                const isCompletedDB = dayTopics.length > 0 && dayTopics.every(t => dbCompletedTopics.has(t.id));
                day.isCompleted = isCompletedDB;

                const topicosDeEstudo = dayTopics.filter(t => t.subject !== 'Simulado');

                if (day.isCompleted && topicosDeEstudo.length > 0) {
                    let activeDayCount = 0;
                    let revisionPlanIndex = -1;
                    for (let i = index + 1; i < plan.length; i++) {
                        // Agenda a revisão (D+7) ignorando os dias pulados E os dias fixos de simulado
                        if (!plan[i].isSkipped && !simuladoIndices.includes(i)) {
                            activeDayCount++;
                            if (activeDayCount === REVISION_ACTIVE_DAYS_INTERVAL) {
                                revisionPlanIndex = i;
                                break;
                            }
                        }
                    }
                    if (revisionPlanIndex !== -1) {
                        plan[revisionPlanIndex].revisions.push(...topicosDeEstudo);
                    }
                }
            });

            // =================================================================
            // RENDERIZAÇÃO
            // =================================================================
            const activeDaysCount = activeStudyDaysIndices.length + activeSimuladoDaysIndices.length;
            const skippedDaysCount = plan.length - activeDaysCount;
            const infoText = `Total de dias: ${plan.length}. Ativos: ${activeDaysCount}. Pulados: ${skippedDaysCount}. Tópicos/dia: ${topicsPerDay}`;
            const planInfo = document.getElementById('planInfo');
            planInfo.textContent = infoText;
            planInfo.classList.remove('hidden');

            if (plan.length === 0) {
                document.getElementById('noDaysMsg').classList.remove('hidden');
                planInfo.classList.add('hidden');
                return;
            }

            plan.forEach((day, index) => {
                studyDaysContainer.innerHTML += createStudyDayCard(day, index);
            });

            attachListeners();
        }

        // --- 6. INTERFACE (Componentes) ---
        function createStudyDayCard(day, index) {
            const cardClass = day.isSkipped ? 'skipped' : (day.isCompleted ? 'completed' : 'bg-white hover:bg-gray-100');
            
            // 1. LÓGICA DO BOTÃO PULAR DIA (Esconde se estiver concluído)
            let skipBtnHtml = '';
            if (!day.isCompleted) {
                const skipBtnText = day.isSkipped ? '✔️ Reativar' : '❌ Pular Dia';
                const skipBtnColor = day.isSkipped ? 'bg-green-500 hover:bg-green-600' : 'bg-yellow-500 hover:bg-yellow-600';
                skipBtnHtml = `<button class="skip-day-btn w-full py-2 text-xs font-semibold rounded-md transition-colors ${skipBtnColor} text-white" data-index="${index}" data-action="${day.isSkipped ? 'activate' : 'skip'}">${skipBtnText}</button>`;
            }

            // 2. LÓGICA DO BOTÃO MARCAR CONCLUÍDO (Esconde se estiver pulado)
            let completeBtnHtml = '';
            if (!day.isSkipped) {
                const completeBtnText = day.isCompleted ? '🔄 Desmarcar Conclusão' : '✅ Marcar Concluído';
                const completeBtnColor = day.isCompleted ? 'bg-slate-500 hover:bg-slate-600' : 'bg-green-600 hover:bg-green-700';
                
                // Adicionei um margin-top dinâmico: se não tem o botão de pular em cima, não precisa de margem.
                const marginTop = (!day.isCompleted) ? 'mt-2' : ''; 
                
                completeBtnHtml = `<button class="complete-day-btn w-full py-2 text-xs font-semibold rounded-md transition-colors ${marginTop} ${completeBtnColor} text-white" data-index="${index}" data-action="${day.isCompleted ? 'uncomplete' : 'complete'}">${completeBtnText}</button>`;
            }

            let topicsHtml = '';
            const hasStudyContent = (Object.keys(day.topics).length > 0) || (day.revisions && day.revisions.length > 0);

            if (day.simulado) {
                const tituloSimulado = day.simulado.title || day.simulado;
                topicsHtml = `
                    <div class="mt-3 p-3 bg-red-100 border-l-4 border-red-500 rounded text-red-800">
                        <h5 class="font-bold text-base">🚨 ${tituloSimulado} AGENDADO 🚨</h5>
                    </div>
                `;
            } else if (hasStudyContent) {
                let revisionsHtml = '';
                if (day.revisions && day.revisions.length > 0) {
                    revisionsHtml = `<div class="revision-box mt-3 p-3 border-l-4 rounded text-yellow-800"><h5 class="font-bold text-sm mb-1">REVISÃO PROGRAMADA (D+7)</h5><ul class="text-xs space-y-1">${day.revisions.map(t => `<li class="ml-2">&bull; ${t.subject} - Tópico ${t.originalTitle}</li>`).join('')}</ul></div>`;
                }
                let newTopicsHtml = '';
                if (Object.keys(day.topics).length > 0) {
                    newTopicsHtml = `<h4 class="font-semibold text-gray-900 mb-1">Tópicos do Dia:</h4><ul class="topic-list text-sm space-y-2">${Object.keys(day.topics).map(subject => day.topics[subject].map(t => `<li class="ml-4">&bull; ${subject} - Tópico ${t.originalTitle}${t.isRevision ? ' (Rev)' : ''}</li>`).join('')).join('')}</ul>`;
                }
                topicsHtml = newTopicsHtml + revisionsHtml;
            } else {
                topicsHtml = `<p class="text-base font-bold text-slate-700 mt-2">Dia Livre!</p>`;
            }

            if (day.isSkipped) {
                // Se esse dia tinha um simulado programado, muda o aviso
                if (day.simulado) {
                    topicsHtml = `<p class="text-base font-bold text-red-700 mt-2">🚨 Simulado Pulado</p>`;
                } else {
                    topicsHtml = `<p class="text-base font-bold text-red-700 mt-2">Dia Pulado</p>`;
                }
            }

            return `
                <div class="study-day-card p-4 rounded-lg shadow-lg flex flex-col h-full ${cardClass}">
                    <div class="flex justify-between items-center mb-2 border-b pb-2">
                        <h3 class="text-lg font-bold text-gray-800">${day.formattedDate}</h3>
                        <span class="text-xs font-semibold text-gray-600">Dia ${index + 1}</span>
                    </div>
                    <div class="flex-grow">
                        ${topicsHtml}
                    </div>
                    <div class="mt-4 space-y-2">
                        ${skipBtnHtml}
                        ${completeBtnHtml}
                    </div>
                </div>
            `;
        }

        // --- 7. LISTENERS E SINCRONIZAÇÃO COM BANCO ---
        async function syncDayWithDatabase(day, action) {
            const allTopics = [...Object.values(day.topics).flat(), ...day.revisions];
            if (day.simulado && day.simulado.id) allTopics.push(day.simulado);
            if (allTopics.length === 0) return;

            const listaParaApi = allTopics.map(t => ({ ref: t.id, tipo: 'Organizador' }));
            const apiUrl = 'api_progresso.php'; 

            if (action === 'complete') {
                await fetch(apiUrl, { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({ acao: 'sincronizar', lista: listaParaApi }) 
                });
                listaParaApi.forEach(item => dbCompletedTopics.add(item.ref));
            } else {
                for (const item of listaParaApi) {
                    await fetch(apiUrl, { 
                        method: 'POST', 
                        headers: {'Content-Type': 'application/json'}, 
                        body: JSON.stringify({ acao: 'desmarcar', ref: item.ref, tipo: 'Organizador' }) 
                    });
                    dbCompletedTopics.delete(item.ref);
                }
            }
        }

        function attachListeners() {
            const studyDaysContainer = document.getElementById('studyDaysContainer');
            
            // Remove ouvintes antigos substituindo o elemento para não duplicar eventos
            studyDaysContainer.replaceWith(studyDaysContainer.cloneNode(true));
            const newContainer = document.getElementById('studyDaysContainer');

            newContainer.addEventListener('click', async function(e) {
                // Lógica de PULAR DIA
                if (e.target.classList.contains('skip-day-btn')) {
                    const button = e.target;
                    const index = parseInt(button.dataset.index);
                    const action = button.dataset.action;
                    const day = currentPlan[index];
                    
                    const dateParts = day.formattedDate.split('/');
                    const dateForDb = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`;

                    const originalText = button.innerText;
                    button.innerText = "Salvando...";
                    button.disabled = true;

                    currentPlan[index].isSkipped = (action === 'skip');
                    if(currentPlan[index].isSkipped) currentPlan[index].isCompleted = false;

                    const apiUrl = 'api_progresso.php'; 
                    const reqBody = action === 'skip' 
                        ? { acao: 'sincronizar', lista: [{ ref: dateForDb, tipo: 'DiaPulado' }] }
                        : { acao: 'desmarcar', ref: dateForDb, tipo: 'DiaPulado' };

                    await fetch(apiUrl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(reqBody)
                    });

                    if (action === 'skip') {
                        dbSkippedDays.add(dateForDb);
                    } else {
                        dbSkippedDays.delete(dateForDb);
                    }

                    // Redesenha para atualizar e redistribuir as matérias sem recriar datas
                    calculateAndRenderPlan(currentPlan); 
                }

                // Lógica de MARCAR CONCLUÍDO
                if (e.target.classList.contains('complete-day-btn')) {
                    const button = e.target;
                    const index = parseInt(button.dataset.index);
                    const action = button.dataset.action;
                    const day = currentPlan[index];

                    const originalText = button.innerText;
                    button.innerText = "Salvando...";
                    button.disabled = true;

                    await syncDayWithDatabase(day, action);

                    button.disabled = false;
                    button.innerText = originalText;
                    
                    calculateAndRenderPlan(currentPlan); 
                }
            });
        }

        document.addEventListener('DOMContentLoaded', async () => {
            // Executa a migração do LocalStorage antes de tudo
            await migrateLocalStorage();

            const todayStr = new Date().toISOString().split('T')[0];

            // Preenche com os dados que o PHP trouxe do banco
            if (configDoBanco && typeof configDoBanco === 'object') {
                if (configDoBanco.startDate) document.getElementById('startDate').value = configDoBanco.startDate;
                if (configDoBanco.endDate) document.getElementById('endDate').value = configDoBanco.endDate;
                if (configDoBanco.hoursPerDay) document.getElementById('hoursPerDay').value = configDoBanco.hoursPerDay;
            } else {
                document.getElementById('startDate').value = todayStr;
                const end = new Date(); end.setDate(end.getDate() + 60);
                document.getElementById('endDate').value = end.toISOString().split('T')[0];
            }

            // AÇÃO DO BOTÃO: Agora ele é o único responsável por gerar e salvar!
            const btnGerar = document.getElementById('generatePlanBtn');
            btnGerar.addEventListener('click', async () => {
                // Efeito visual de carregamento
                const originalText = btnGerar.innerText;
                btnGerar.innerText = "⏳ Gerando Plano...";
                btnGerar.disabled = true;

                // 1. Salva a configuração no banco de dados
                await saveUserSettings();
                
                // 2. Gera o novo calendário
                generatePlan(true);

                // Retorna o botão ao normal
                btnGerar.innerText = originalText;
                btnGerar.disabled = false;

                // 3. Rola a tela suavemente para a área do plano (UX excelente)
                const studyPlanSection = document.getElementById('studyPlanSection');
                if(studyPlanSection && !studyPlanSection.classList.contains('hidden')) {
                    studyPlanSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });

            // Gera o plano inicial silenciosamente ao carregar a página (sem rolar a tela)
            setTimeout(() => generatePlan(), 500);
        });
    </script>
</body>
</html>