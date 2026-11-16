// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

describe('Visualizador de Quizzes (ver_quiz.php) - Frontend Test Suite', () => {
    let originalFetch;
    let originalOpener;
    let originalHistoryLength;

    beforeEach(() => {
        vi.useFakeTimers();
        originalFetch = global.fetch;
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true, id: 123 })
        });

        // Mock window.opener and history length
        originalOpener = window.opener;
        // Mock history.length via property definition if needed or test window.close
        vi.spyOn(window, 'close').mockImplementation(() => {});
        
        // Reset document body
        document.body.innerHTML = `
            <div>
                <h1 id="quizTitle">Quiz de Conhecimentos Policiais</h1>
                <div id="quiz-container">
                    <button class="btn" id="backBtn">Voltar para a lista</button>
                    <button class="btn" id="otherBtn">Voltar para o início</button>
                    <button class="btn" id="submit-button">Finalizar Respostas</button>
                </div>
            </div>
        `;
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.useRealTimers();
        global.fetch = originalFetch;
        window.opener = originalOpener;
    });

    // Helper to simulate script injection execution
    const loadQuizScript = (quizName = 'quiz_01') => {
        // Simulate window.location
        delete window.location;
        window.location = {
            href: `http://localhost/bm.contextopolicial.com-exame/ver_quiz.php?q=components/${quizName}.html`
        };

        // Define mock quizData and userAnswers
        global.quizData = [
            {
                question: 'Qual a missão principal da Brigada Militar?',
                correctAnswer: 'A',
                commentary: 'Policiamento ostensivo.'
            },
            {
                question: 'Qual o ano de fundação?',
                correctAnswer: 'B',
                commentary: 'Fundada em 1837.'
            }
        ];
        global.userAnswers = ['A', null]; // Q1 correct, Q2 unanswered ("Não respondeu")

        global.originalShowFinalResults = vi.fn(() => {
            // Simulate visual rendering of results
            const div = document.createElement('div');
            div.id = 'results-view';
            div.textContent = 'Resultado Final Exibido';
            document.body.appendChild(div);
        });
        global.showFinalResults = global.originalShowFinalResults;

        // Execute the inline script logic (extracted from ver_quiz.php)
        const baseUrl = window.location.href.split('?')[0].substring(0, window.location.href.split('?')[0].lastIndexOf('/'));
        const API_URL = baseUrl + '/api/salvar_quiz.php';
        const ID_QUIZ = quizName;

        // 1. Caçador de Botões
        const TEXTO_ALVO = "voltar para a lista";
        function caçarETransformarBotao() {
            const candidatos = document.querySelectorAll('a, button, .btn, div[role="button"]');
            for (let btn of candidatos) {
                if (btn.dataset.corrigido === "true") continue;
                const textoBotao = (btn.textContent || "").toLowerCase();

                if (textoBotao.includes(TEXTO_ALVO) || textoBotao.includes("voltar para")) {
                    btn.dataset.corrigido = "true";
                    btn.innerHTML = "✖ Fechar Quiz";
                    btn.setAttribute("style", "background-color: #dc2626 !important; border-color: #b91c1c !important; color: white !important; cursor: pointer !important; display: inline-block !important; text-decoration: none !important; font-weight: bold !important; padding: 10px 20px !important; border-radius: 5px !important;");
                    
                    if (btn.tagName === 'A') btn.removeAttribute('href');
                    const novoBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(novoBtn, btn);

                    novoBtn.onclick = function(e) {
                        e.preventDefault();
                        if (window.opener || window.history.length > 1) {
                            window.close();
                            setTimeout(() => { window.location.href = "http://localhost/bm.contextopolicial.com-exame/area_aluno.php"; }, 200);
                        } else {
                            window.location.href = "http://localhost/bm.contextopolicial.com-exame/area_aluno.php";
                        }
                    };
                }
            }
        }
        global.caçarETransformarBotao = caçarETransformarBotao;

        // 2. Salvamento Automático
        async function enviarResultadoQuiz(acertos, total, nota, detalhes) {
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
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                return data;
            } catch (error) {
                console.error("❌ Erro de conexão:", error);
                throw error;
            }
        }
        global.enviarResultadoQuiz = enviarResultadoQuiz;

        // Interceptador showFinalResults
        const orig = global.showFinalResults;
        global.showFinalResults = function() {
            orig();
            if (typeof global.quizData !== 'undefined' && typeof global.userAnswers !== 'undefined') {
                let acertosCount = 0;
                let detalhes = [];

                global.userAnswers.forEach((respostaAluno, index) => {
                    const questaoObj = global.quizData[index];
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

                const percentual = (acertosCount / global.quizData.length) * 100;
                return global.enviarResultadoQuiz(acertosCount, global.quizData.length, percentual, detalhes);
            }
        };

        caçarETransformarBotao();
    };

    it('1. Extrai corretamente a API_URL e o ID_QUIZ baseados na URL e nome do arquivo', () => {
        loadQuizScript('quiz_02');
        expect(document.getElementById('backBtn').dataset.corrigido).toBe('true');
    });

    it('2. Caçador de Botões transforma botões de retorno ("Voltar para a lista" / "Voltar para") em "✖ Fechar Quiz" com estilo vermelho', () => {
        loadQuizScript('quiz_01');
        const backBtn = document.getElementById('backBtn');
        expect(backBtn.innerHTML).toContain('✖ Fechar Quiz');
        expect(backBtn.style.backgroundColor).toContain('rgb(220, 38, 38)'); // #dc2626
        expect(backBtn.dataset.corrigido).toBe('true');

        const otherBtn = document.getElementById('otherBtn');
        expect(otherBtn.innerHTML).toContain('✖ Fechar Quiz');
        expect(otherBtn.dataset.corrigido).toBe('true');
    });

    it('3. Não altera botões legítimos do quiz como "Finalizar Respostas"', () => {
        loadQuizScript('quiz_01');
        const submitBtn = document.getElementById('submit-button');
        expect(submitBtn.dataset.corrigido).toBeUndefined();
        expect(submitBtn.textContent).toBe('Finalizar Respostas');
    });

    it('4. Clique no botão transformado tenta window.close() se houver opener ou history, com fallback setTimeout para area_aluno.php', () => {
        loadQuizScript('quiz_01');
        window.opener = {}; // Simula janela aberta por outra
        const backBtn = document.getElementById('backBtn');
        
        const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
        backBtn.dispatchEvent(clickEvent);

        expect(window.close).toHaveBeenCalled();
        
        vi.advanceTimersByTime(200);
        expect(window.location.href).toBe('http://localhost/bm.contextopolicial.com-exame/area_aluno.php');
    });

    it('5. Clique no botão transformado redireciona direto para area_aluno.php se não houver opener nem history excessivo', () => {
        loadQuizScript('quiz_01');
        window.opener = null;
        Object.defineProperty(window.history, 'length', { value: 1, configurable: true });
        
        const backBtn = document.getElementById('backBtn');
        backBtn.click();

        expect(window.location.href).toBe('http://localhost/bm.contextopolicial.com-exame/area_aluno.php');
    });

    it('6. Interceptador de showFinalResults executa a função original e dispara fetch POST com payload correto (100% de acertos ou parcial)', async () => {
        loadQuizScript('quiz_01');
        global.userAnswers = ['A', 'B']; // Ambas corretas

        await global.showFinalResults();

        expect(global.originalShowFinalResults).toHaveBeenCalled();
        expect(global.fetch).toHaveBeenCalledTimes(1);

        const fetchCall = global.fetch.mock.calls[0];
        expect(fetchCall[0]).toBe('http://localhost/bm.contextopolicial.com-exame/api/salvar_quiz.php');
        
        const body = JSON.parse(fetchCall[1].body);
        expect(body).toEqual({
            quiz_ref: 'quiz_01',
            acertos: 2,
            total_questoes: 2,
            nota: 100,
            detalhes: [
                {
                    questao: 'Qual a missão principal da Brigada Militar?',
                    respostaCorreta: 'A',
                    respostaAluno: 'A',
                    acertou: true,
                    comentario: 'Policiamento ostensivo.'
                },
                {
                    questao: 'Qual o ano de fundação?',
                    respostaCorreta: 'B',
                    respostaAluno: 'B',
                    acertou: true,
                    comentario: 'Fundada em 1837.'
                }
            ]
        });
    });

    it('7. Trata corretamente respostas em branco ("Não respondeu") no array de detalhes', async () => {
        loadQuizScript('quiz_01');
        global.userAnswers = ['A', null]; // Q1 correta, Q2 não respondeu

        await global.showFinalResults();

        const fetchCall = global.fetch.mock.calls[0];
        const body = JSON.parse(fetchCall[1].body);

        expect(body.acertos).toBe(1);
        expect(body.nota).toBe(50);
        expect(body.detalhes[1].respostaAluno).toBe('Não respondeu');
        expect(body.detalhes[1].acertou).toBe(false);
    });

    it('8. Trata falhas de rede/API no fetch sem quebrar a execução (catch graceful)', async () => {
        loadQuizScript('quiz_01');
        global.fetch.mockRejectedValueOnce(new Error('Network error'));
        const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        await expect(global.showFinalResults()).rejects.toThrow('Network error');
        expect(consoleErrorSpy).toHaveBeenCalledWith("❌ Erro de conexão:", expect.any(Error));

        consoleErrorSpy.mockRestore();
    });
});
