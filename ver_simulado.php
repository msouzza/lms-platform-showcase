<?php
// ARQUIVO: area-do-aluno/ver_simulado.php

require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php';

// 1. SEGURANÇA E IDENTIDADE
if (session_status() === PHP_SESSION_NONE) session_start();

$id_logado = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? null;
if (!$id_logado) die("Acesso negado. Faça login.");

// 2. CARREGAMENTO DO ARQUIVO
$caminhoRelativo = $_GET['q'] ?? '';

// Proteção básica
if (empty($caminhoRelativo) || strpos($caminhoRelativo, 'components/') === false || strpos($caminhoRelativo, '..') !== false) {
    die("Arquivo inválido ou não permitido.");
}

$arquivoFisico = __DIR__ . '/' . $caminhoRelativo;
if (!file_exists($arquivoFisico)) die("Simulado não encontrado: " . htmlspecialchars($caminhoRelativo));

// Pega o nome do arquivo para usar como ID de referência
$nomeSimulado = pathinfo($arquivoFisico, PATHINFO_FILENAME);

// MUDANÇA: CÁLCULO DINÂMICO DE URL
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); 
$urlSistema = $protocolo . $host . rtrim($scriptPath, '/\\');

// 3. PROCESSAMENTO DO HTML
$html = file_get_contents($arquivoFisico);

// Ajusta o <base> para que imagens e CSS funcionem
$pastaBase = dirname($caminhoRelativo) . '/'; 
$html = str_replace('<head>', '<head><base href="' . $pastaBase . '">', $html);

// 4. INJEÇÃO DOS SCRIPTS
$scriptsExtras = <<<JS
<script>
(function() {
    console.log("🚀 Sistema Iniciado: $nomeSimulado");
    
    const baseUrl = window.location.href.split('?')[0].substring(0, window.location.href.split('?')[0].lastIndexOf('/'));
    const API_URL = baseUrl + '/api/salvar_simulado.php';

    // ============================================================
    // PARTE 1: O CAÇADOR DE BOTÕES (MUDANÇA: Fechamento Robusto)
    // ============================================================
    function caçarETransformarBotao() {
        const candidatos = document.querySelectorAll('a, button, .btn, .btn-voltar, div[role="button"]');

        for (let btn of candidatos) {
            if (btn.textContent.toLowerCase().includes("enviar") || btn.textContent.toLowerCase().includes("corrigir") || btn.textContent.toLowerCase().includes("salvar") || btn.textContent.toLowerCase().includes("iniciar")) continue;
            if (btn.dataset.corrigido === "true") continue;

            const texto = (btn.textContent || "").toLowerCase().trim();
            const link = (btn.getAttribute('href') || "").toLowerCase();

            let ehOAlvo = false;
            if (texto.includes('voltar') || texto.includes('sair') || texto.includes('lista')) ehOAlvo = true;
            else if (link.includes('area_aluno') || link.includes('index.php')) ehOAlvo = true;

            if (ehOAlvo) {
                btn.dataset.corrigido = "true"; 
                btn.innerHTML = ""; 
                btn.textContent = "✖ Fechar Simulado";
                btn.setAttribute("style", "background-color: #dc2626 !important; border: 2px solid #b91c1c !important; color: white !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; text-decoration: none !important; font-weight: bold !important; padding: 10px 20px !important; border-radius: 8px !important; min-width: 150px; box-shadow: 0 4px 6px rgba(0,0,0,0.2) !important; z-index: 9999; margin-bottom: 20px;");
                
                if (btn.tagName === 'A') { btn.removeAttribute('href'); btn.removeAttribute('target'); }

                const novoBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(novoBtn, btn);

                novoBtn.onclick = function(e) {
                    e.preventDefault();
                    if(confirm('Deseja realmente sair do simulado?')) {
                        if (window.opener || window.history.length > 1) {
                            window.close(); 
                            setTimeout(() => { window.location.href = "{$urlSistema}/area_aluno.php"; }, 200);
                        } else {
                            window.location.href = "{$urlSistema}/area_aluno.php";
                        }
                    }
                };
            }
        }
    }

    // ============================================================
    // PARTE 2: LÓGICA DE ENVIO E DATA
    // ============================================================
    let dataHoraInicio = new Date();
    const ID_SIMULADO = "$nomeSimulado";
    
    // Formata data local (Brasil)
    const formatarData = (d) => {
        const pad = (n) => (n < 10 ? '0' + n : n);
        return d.getFullYear() + '-' +
               pad(d.getMonth() + 1) + '-' +
               pad(d.getDate()) + ' ' +
               pad(d.getHours()) + ':' +
               pad(d.getMinutes()) + ':' +
               pad(d.getSeconds());
    };

    // Título Padrão (Fallback) - Pega do H1 ou da Aba
    let tituloFallback = document.title;
    const h1 = document.querySelector('h1');
    if (h1) tituloFallback = h1.innerText;

    async function enviarResultado(score, total, nota, detalhesArray, tituloFinal) {
        const dataHoraFim = new Date();
        const diffMs = dataHoraFim - dataHoraInicio;
        
        const diffHrs = Math.floor((diffMs % 86400000) / 3600000);
        const diffMins = Math.floor(((diffMs % 86400000) % 3600000) / 60000);
        const diffSecs = Math.floor((((diffMs % 86400000) % 3600000) % 60000) / 1000);
        const tempoDecorrido = `\${String(diffHrs).padStart(2,'0')}:\${String(diffMins).padStart(2,'0')}:\${String(diffSecs).padStart(2,'0')}`;

        // Usa o título passado ou o fallback
        const tituloParaSalvar = tituloFinal || tituloFallback;
        console.log("💾 Salvando com o título:", tituloParaSalvar);

        const payload = {
            simulado_ref: ID_SIMULADO,
            titulo: tituloParaSalvar, 
            acertos: score,
            total_questoes: total,
            nota: nota,
            data_inicio: formatarData(dataHoraInicio),
            data_fim: formatarData(dataHoraFim),
            tempo_decorrido: tempoDecorrido,
            detalhes: detalhesArray
        };

        try {
            const response = await fetch(API_URL, { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if(data.success) console.log("✅ Dados salvos com sucesso!");
            else console.error("❌ Erro ao salvar:", data.message);
        } catch (error) {
            console.error("❌ Erro de conexão:", error);
        }
    }

    // Interceptador do Envio
    if (typeof window.submitQuiz === 'function') {
        const originalSubmitQuiz = window.submitQuiz;
        
        window.submitQuiz = function(isTimeUp) {
            // 1. Executa a função original (popula as variáveis)
            originalSubmitQuiz(isTimeUp); 
            
            // 2. Busca Título INTELIGENTE
            let tituloDetectado = null;

            try {
                if (typeof currentPerformanceData !== 'undefined' && currentPerformanceData && currentPerformanceData.simuladoTitulo) {
                    tituloDetectado = currentPerformanceData.simuladoTitulo;
                    console.log("🎯 Título encontrado via currentPerformanceData:", tituloDetectado);
                }
            } catch (e) {}

            if (!tituloDetectado) {
                try {
                    if (typeof simuladoTitulo !== 'undefined') {
                        tituloDetectado = simuladoTitulo;
                        console.log("🎯 Título encontrado via simuladoTitulo:", tituloDetectado);
                    }
                } catch (e) {}
            }

            // 3. Coleta os dados
            const dadosQuestoes = (typeof questions !== 'undefined') ? questions : ((typeof quizData !== 'undefined') ? quizData : null);
            
            if (typeof userAnswers !== 'undefined' && dadosQuestoes) {
                let score = 0;
                let detalhes = [];

                userAnswers.forEach((letraAluno, index) => {
                    const questaoObj = dadosQuestoes[index];
                    const letraGabarito = questaoObj.correctAnswer || questaoObj.correct;
                    const acertou = (letraAluno === letraGabarito);
                    if (acertou) score++;

                    // Pega o TEXTO
                    let textoRespostaCorreta = letraGabarito;
                    let textoRespostaAluno = "Não respondeu";

                    if (questaoObj.options && questaoObj.options[letraGabarito]) {
                        textoRespostaCorreta = questaoObj.options[letraGabarito];
                    }
                    if (letraAluno && questaoObj.options && questaoObj.options[letraAluno]) {
                        textoRespostaAluno = questaoObj.options[letraAluno];
                    }

                    detalhes.push({
                        id_questao: questaoObj.id || index,
                        questao: questaoObj.text || questaoObj.question,
                        respostaCorreta: textoRespostaCorreta,
                        respostaAluno: textoRespostaAluno,
                        letraCorreta: letraGabarito,
                        letraAluno: letraAluno || "",
                        acertou: acertou
                    });
                });
                
                const percentage = Math.round((score / dadosQuestoes.length) * 100);
                enviarResultado(score, dadosQuestoes.length, percentage, detalhes, tituloDetectado);
            }
        };
    }

    document.addEventListener('DOMContentLoaded', caçarETransformarBotao);
    setInterval(caçarETransformarBotao, 1000);

})();
</script>
</body>
JS;

echo str_replace('</body>', $scriptsExtras, $html);
?>