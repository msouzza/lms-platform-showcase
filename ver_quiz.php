<?php
// ARQUIVO: area-do-aluno/ver_quiz.php

// 1. CARREGA CONFIG E SESSÃO
require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php'; // Adicionado conexão caso precise no futuro

if (session_status() === PHP_SESSION_NONE) session_start();

// MUDANÇA: Identidade Unificada
$id_logado = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? null;
if (!$id_logado) die("Acesso negado. Faça login.");

// 2. VALIDAÇÃO DO ARQUIVO
$caminhoQuiz = $_GET['q'] ?? '';

// Proteção contra Directory Traversal e acesso fora da pasta components/
if (empty($caminhoQuiz) || strpos($caminhoQuiz, 'components/') !== 0 || strpos($caminhoQuiz, '.html') === false || strpos($caminhoQuiz, '..') !== false) {
    die("Arquivo inválido.");
}

$arquivoFisico = __DIR__ . '/' . $caminhoQuiz;
if (!file_exists($arquivoFisico)) die("Quiz não encontrado: " . htmlspecialchars($caminhoQuiz));

// Pega o nome do arquivo para usar como ID (ex: quizzes_01)
$nomeQuiz = pathinfo($arquivoFisico, PATHINFO_FILENAME);

// 3. LÊ O HTML ORIGINAL
$html = file_get_contents($arquivoFisico);

// 4. MUDANÇA: CÁLCULO DINÂMICO DE URL (Evita Erro 404 ao fechar)
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); 
$urlSistema = $protocolo . $host . rtrim($scriptPath, '/\\');

// CORRIGE BASE PARA IMAGENS/CSS
$pastaBase = dirname($caminhoQuiz) . '/'; 
$html = str_replace('<head>', '<head><base href="' . $pastaBase . '">', $html);

// Remove navegação original para limpar a tela (opcional, igual fizemos no simulado)
$cssLimpeza = "<style>.bg-white nav button { display: none !important; }</style>";
$html = str_replace('</head>', $cssLimpeza . '</head>', $html);

// ==========================================================
// SCRIPT INJETADO: CAÇADOR + SALVADOR AUTOMÁTICO
// ==========================================================
$scriptInjetado = <<<JS
<script>
(function() {
    console.log("🚀 Sistema de Quiz Iniciado: $nomeQuiz");
    
    // Calcula URL da API automaticamente
    const baseUrl = window.location.href.split('?')[0].substring(0, window.location.href.split('?')[0].lastIndexOf('/'));
    const API_URL = baseUrl + '/api/salvar_quiz.php';
    const ID_QUIZ = "$nomeQuiz";

    // -------------------------------------------------------
    // 1. CAÇADOR DE BOTÕES (MUDANÇA: Fechamento Robusto)
    // -------------------------------------------------------
    const TEXTO_ALVO = "voltar para a lista"; 
    
    function caçarETransformarBotao() {
        const candidatos = document.querySelectorAll('a, button, .btn, div[role="button"]');
        for (let btn of candidatos) {
            if (btn.dataset.corrigido === "true") continue;
            const textoBotao = (btn.textContent || "").toLowerCase();

            // Lógica para achar botão de sair
            if (textoBotao.includes(TEXTO_ALVO) || textoBotao.includes("voltar para")) {
                btn.dataset.corrigido = "true"; 
                btn.innerHTML = "✖ Fechar Quiz";
                // Estilo agressivo
                btn.setAttribute("style", "background-color: #dc2626 !important; border-color: #b91c1c !important; color: white !important; cursor: pointer !important; display: inline-block !important; text-decoration: none !important; font-weight: bold !important; padding: 10px 20px !important; border-radius: 5px !important;");
                
                if (btn.tagName === 'A') btn.removeAttribute('href');
                const novoBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(novoBtn, btn);

                novoBtn.onclick = function(e) {
                    e.preventDefault();
                    if (window.opener || window.history.length > 1) {
                        window.close(); 
                        setTimeout(() => { window.location.href = "{$urlSistema}/area_aluno.php"; }, 200);
                    } else {
                        window.location.href = "{$urlSistema}/area_aluno.php";
                    }
                };
            }
        }
    }

    // -------------------------------------------------------
    // 2. SALVAMENTO AUTOMÁTICO (NOVA LÓGICA)
    // -------------------------------------------------------
    async function enviarResultadoQuiz(acertos, total, nota, detalhes) {
        // Tenta pegar o título do H1
        let tituloPagina = document.title;
        const h1 = document.querySelector('h1');
        if (h1) tituloPagina = h1.innerText;

        const payload = {
            quiz_ref: ID_QUIZ,
            titulo: tituloPagina,
            acertos: acertos,
            total_questoes: total,
            nota: nota,
            detalhes: detalhes
        };

        try {
            console.log("💾 Salvando Quiz...", payload);
            const response = await fetch(API_URL, { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if(data.success) console.log("✅ Quiz salvo com sucesso! ID:", data.id);
            else console.error("❌ Erro ao salvar quiz:", data.message);
        } catch (error) {
            console.error("❌ Erro de conexão:", error);
        }
    }

    // INTERCEPTADOR: Vigia a função showFinalResults do quiz original
    if (typeof window.showFinalResults !== 'undefined' || typeof showFinalResults === 'function') {
        const originalShowFinalResults = window.showFinalResults || showFinalResults;
        
        // Sobrescreve a função original
        window.showFinalResults = function() {
            // 1. Roda a lógica visual original (mostra gráfico, nota, etc)
            originalShowFinalResults(); 
            
            // 2. Coleta os dados para salvar
            if (typeof quizData !== 'undefined' && typeof userAnswers !== 'undefined') {
                let acertosCount = 0;
                let detalhes = [];

                userAnswers.forEach((respostaAluno, index) => {
                    const questaoObj = quizData[index];
                    const gabarito = questaoObj.correctAnswer;
                    const acertou = (respostaAluno === gabarito);
                    
                    if (acertou) acertosCount++;

                    detalhes.push({
                        questao: questaoObj.question,
                        respostaCorreta: gabarito,
                        respostaAluno: respostaAluno || "Não respondeu",
                        acertou: acertou,
                        comentario: questaoObj.commentary
                    });
                });

                const percentual = (acertosCount / quizData.length) * 100;

                // Envia para o banco
                enviarResultadoQuiz(acertosCount, quizData.length, percentual, detalhes);
            } else {
                console.warn("⚠️ Variáveis quizData ou userAnswers não encontradas.");
            }
        };
    } else {
        // Fallback: Se não achar a função, tenta adicionar listener no botão de finalizar
        const btnFinalizar = document.getElementById('submit-button');
        if (btnFinalizar) {
            btnFinalizar.addEventListener('click', () => {
                // Pequeno delay para garantir que o userAnswers foi preenchido
                setTimeout(() => {
                    if (typeof window.showFinalResults !== 'undefined') window.showFinalResults();
                }, 100);
            });
        }
    }

    // Inicia os processos
    document.addEventListener('DOMContentLoaded', caçarETransformarBotao);
    setInterval(caçarETransformarBotao, 1000);

})();
</script>
</body>
JS;

echo str_replace('</body>', $scriptInjetado, $html);
?>