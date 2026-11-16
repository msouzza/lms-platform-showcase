// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// Mock Chart.js since it's loaded via CDN
class MockChart {
    constructor() {}
    destroy() {}
}
global.Chart = MockChart;

describe('Simulado Interativo (Frontend Test Suite)', () => {
    let container;

    beforeEach(() => {
        // Setup DOM structure mimicking simulado_01.html
        document.body.innerHTML = `
            <div>
                <h1 id="quizTitle">Simulado 01 - Conhecimentos Gerais</h1>
                <div id="initialControls">
                    <button id="startButton">Iniciar Simulado</button>
                </div>
                <div id="quizForm" class="hidden">
                    <div id="timerDisplay">00:00</div>
                    <div id="questionsContainer"></div>
                    <button id="submitButton" disabled>Enviar Respostas</button>
                    <div id="warningText" class="hidden">Responda todas as questões!</div>
                </div>
                <div id="resultsSection" class="hidden">
                    <div id="scoreText"></div>
                    <div id="percentageDisplay"></div>
                    <div id="timeTakenDisplay"></div>
                    <div id="feedbackMessage"></div>
                    <button id="restartButton">Reiniciar</button>
                    <button id="savePerformanceButton">Salvar Desempenho</button>
                    <canvas id="resultsChart"></canvas>
                </div>
            </div>
        `;

        // Inject global quiz variables & functions simulating simulado script
        global.questions = [
            {
                id: 'q1',
                text: 'Questão 1: Qual a capital do Brasil?',
                options: { A: 'São Paulo', B: 'Brasília', C: 'Rio de Janeiro', D: 'Salvador' },
                correctAnswer: 'B'
            },
            {
                id: 'q2',
                text: 'Questão 2: Quanto é 2 + 2?',
                options: { A: '3', B: '4', C: '5', D: '22' },
                correctAnswer: 'B'
            }
        ];

        global.totalTimeSeconds = 600;
        global.timeLeft = 600;
        global.userAnswers = [null, null];
        global.submitted = false;
        global.timerInterval = null;
        global.startTime = null;
        global.finalTimeElapsed = 0;
        global.resultsChart = null;
        global.currentPerformanceData = null;

        // Element references
        global.initialControls = document.getElementById('initialControls');
        global.startButton = document.getElementById('startButton');
        global.quizForm = document.getElementById('quizForm');
        global.questionsContainer = document.getElementById('questionsContainer');
        global.submitButton = document.getElementById('submitButton');
        global.warningText = document.getElementById('warningText');
        global.resultsSection = document.getElementById('resultsSection');
        global.scoreText = document.getElementById('scoreText');
        global.percentageDisplay = document.getElementById('percentageDisplay');
        global.timeTakenDisplay = document.getElementById('timeTakenDisplay');
        global.feedbackMessage = document.getElementById('feedbackMessage');
        global.timerDisplay = document.getElementById('timerDisplay');
        global.restartButton = document.getElementById('restartButton');
        global.savePerformanceButton = document.getElementById('savePerformanceButton');
        const chartCanvas = document.getElementById('resultsChart');
        if (chartCanvas) {
            chartCanvas.getContext = vi.fn().mockReturnValue({});
            global.resultsChartCanvas = chartCanvas;
        }

        // Functions implementation
        global.formatTime = (seconds) => {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        };

        global.loadQuestions = () => {
            global.questionsContainer.innerHTML = '';
            global.questions.forEach((q, index) => {
                const questionElement = document.createElement('div');
                questionElement.className = 'quiz-card';

                const questionContentDiv = document.createElement('div');
                const questionTextSpan = document.createElement('span');
                questionTextSpan.textContent = q.text;
                questionContentDiv.appendChild(questionTextSpan);
                questionElement.appendChild(questionContentDiv);

                const optionsContainer = document.createElement('div');
                optionsContainer.className = 'space-y-3 pl-11 options-container';
                questionElement.appendChild(optionsContainer);

                Object.keys(q.options).forEach(key => {
                    const optionLabel = document.createElement('label');
                    optionLabel.htmlFor = `${q.id}-${key}`;
                    optionLabel.className = 'option-card pl-4 cursor-pointer';

                    const optionInput = document.createElement('input');
                    optionInput.type = 'radio';
                    optionInput.id = `${q.id}-${key}`;
                    optionInput.name = q.id;
                    optionInput.value = key;

                    optionInput.addEventListener('change', function() {
                        if (global.submitted) return;
                        global.userAnswers[index] = this.value;

                        optionsContainer.querySelectorAll('.option-card').forEach(label => {
                            label.classList.remove('selected');
                        });
                        optionLabel.classList.add('selected');

                        if (global.userAnswers.every(answer => answer !== null)) {
                            global.submitButton.disabled = false;
                            global.warningText.classList.add('hidden');
                        }
                    });

                    optionLabel.appendChild(optionInput);
                    optionLabel.appendChild(document.createTextNode(`${key}. ${q.options[key]}`));
                    optionsContainer.appendChild(optionLabel);
                });

                global.questionsContainer.appendChild(questionElement);
            });
        };

        global.startQuiz = () => {
            global.initialControls.classList.add('hidden');
            global.quizForm.classList.remove('hidden');
            global.resultsSection.classList.add('hidden');

            global.timeLeft = global.totalTimeSeconds;
            global.timerDisplay.textContent = global.formatTime(global.timeLeft);
            global.userAnswers = new Array(global.questions.length).fill(null);
            global.submitted = false;

            global.loadQuestions();
            global.startTime = Date.now();
        };

        global.submitQuiz = (timeExpired = false) => {
            if (global.submitted && !timeExpired) return;
            global.submitted = true;

            const endTime = Date.now();
            global.finalTimeElapsed = timeExpired ? global.totalTimeSeconds : Math.floor((endTime - global.startTime) / 1000);
            global.timeTakenDisplay.textContent = `Tempo de Prova: ${global.formatTime(global.finalTimeElapsed)}`;

            let correctCount = 0;
            const totalQuestions = global.questions.length;

            global.questions.forEach((q, index) => {
                const selectedOptionInput = document.querySelector(`input[name="${q.id}"]:checked`);
                const questionElement = global.questionsContainer.children[index];
                const optionsContainer = questionElement.querySelector('.options-container');

                optionsContainer.querySelectorAll('input[type="radio"]').forEach(input => input.disabled = true);

                optionsContainer.querySelectorAll('label').forEach(label => {
                    label.classList.remove('selected', 'correct-answer-label', 'incorrect-answer-label', 'highlight-correct-answer-label');
                });

                const correctAnswerLabel = optionsContainer.querySelector(`label[for="${q.id}-${q.correctAnswer}"]`);
                if (correctAnswerLabel) {
                    correctAnswerLabel.classList.add('highlight-correct-answer-label');
                }

                if (selectedOptionInput) {
                    const selectedLabel = selectedOptionInput.parentNode;
                    if (selectedOptionInput.value === q.correctAnswer) {
                        correctCount++;
                        selectedLabel.classList.add('correct-answer-label');
                    } else {
                        selectedLabel.classList.add('incorrect-answer-label');
                    }
                }
            });

            global.scoreText.textContent = `Sua Nota: ${correctCount} questões corretas`;
            const percentage = (correctCount / totalQuestions) * 100;
            global.percentageDisplay.textContent = `Percentual de Acerto: ${percentage.toFixed(2)}%`;

            if (percentage >= 90) {
                global.feedbackMessage.textContent = `Parabéns! Com ${percentage.toFixed(2)}% de acerto, você tem grandes chances de aprovação!`;
                global.feedbackMessage.classList.add('pass');
            } else {
                global.feedbackMessage.textContent = `Você acertou ${percentage.toFixed(2)}% das questões. Precisa melhorar mais.`;
                global.feedbackMessage.classList.add('fail');
            }

            global.resultsSection.classList.remove('hidden');

            global.currentPerformanceData = {
                simuladoId: 'simulado_01',
                simuladoTitulo: 'Simulado 01 - Conhecimentos Gerais',
                questoesTotais: totalQuestions,
                questoesCorretas: correctCount,
                percentualAcerto: percentage
            };
        };

        // Attach listeners
        global.startButton.addEventListener('click', global.startQuiz);
        global.submitButton.addEventListener('click', () => {
            if (global.userAnswers.every(answer => answer !== null && answer !== undefined)) {
                global.submitQuiz(false);
            } else {
                global.warningText.classList.remove('hidden');
            }
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('1. Renderiza o DOM inicial com controles de início visíveis e formulário oculto', () => {
        expect(global.initialControls.classList.contains('hidden')).toBe(false);
        expect(global.quizForm.classList.contains('hidden')).toBe(true);
        expect(global.resultsSection.classList.contains('hidden')).toBe(true);
    });

    it('2. Inicia o quiz corretamente ao clicar no botão Iniciar', () => {
        global.startButton.click();
        expect(global.initialControls.classList.contains('hidden')).toBe(true);
        expect(global.quizForm.classList.contains('hidden')).toBe(false);
        expect(global.questionsContainer.children.length).toBe(2);
        expect(global.timerDisplay.textContent).toBe('10:00');
    });

    it('3. Suporta dinamicamente qualquer número de questões (ex: 5 questões)', () => {
        global.questions = [
            { id: 'q1', text: 'Q1', options: { A: '1', B: '2' }, correctAnswer: 'A' },
            { id: 'q2', text: 'Q2', options: { A: '1', B: '2' }, correctAnswer: 'B' },
            { id: 'q3', text: 'Q3', options: { A: '1', B: '2' }, correctAnswer: 'A' },
            { id: 'q4', text: 'Q4', options: { A: '1', B: '2' }, correctAnswer: 'B' },
            { id: 'q5', text: 'Q5', options: { A: '1', B: '2' }, correctAnswer: 'A' }
        ];

        global.startQuiz();
        expect(global.questionsContainer.children.length).toBe(5);
        expect(global.userAnswers.length).toBe(5);
    });

    it('4. Mantém o botão de envio desabilitado inicialmente', () => {
        global.startQuiz();
        expect(global.submitButton.disabled).toBe(true);
    });

    it('5. Atualiza userAnswers e aplica classe .selected ao selecionar alternativa', () => {
        global.startQuiz();
        const radioQ1A = document.getElementById('q1-A');
        radioQ1A.click();

        expect(global.userAnswers[0]).toBe('A');
        expect(radioQ1A.parentNode.classList.contains('selected')).toBe(true);
    });

    it('6. Desmarca a seleção anterior da mesma questão ao trocar de alternativa', () => {
        global.startQuiz();
        const radioQ1A = document.getElementById('q1-A');
        const radioQ1B = document.getElementById('q1-B');

        radioQ1A.click();
        expect(radioQ1A.parentNode.classList.contains('selected')).toBe(true);

        radioQ1B.click();
        expect(global.userAnswers[0]).toBe('B');
        expect(radioQ1A.parentNode.classList.contains('selected')).toBe(false);
        expect(radioQ1B.parentNode.classList.contains('selected')).toBe(true);
    });

    it('7. Mantém o botão de envio desabilitado se nem todas as questões foram respondidas', () => {
        global.startQuiz();
        document.getElementById('q1-B').click(); // Apenas q1 respondida
        expect(global.submitButton.disabled).toBe(true);
    });

    it('8. Habilita o botão de envio assim que todas as questões são respondidas', () => {
        global.startQuiz();
        document.getElementById('q1-B').click();
        document.getElementById('q2-B').click();

        expect(global.submitButton.disabled).toBe(false);
    });

    it('9. Exibe aviso se tentar enviar sem responder todas as questões', () => {
        global.startQuiz();
        document.getElementById('q1-B').click();
        // Se o botão for clicado sem todas respondidas (ex: contornando disabled)
        global.submitButton.disabled = false;
        global.submitButton.click();

        expect(global.warningText.classList.contains('hidden')).toBe(false);
    });

    it('10. Processa a submissão correta e calcula nota 100% para respostas certas', () => {
        global.startQuiz();
        document.getElementById('q1-B').click(); // correto
        document.getElementById('q2-B').click(); // correto
        global.submitButton.click();

        expect(global.submitted).toBe(true);
        expect(global.scoreText.textContent).toContain('2 questões corretas');
        expect(global.percentageDisplay.textContent).toContain('100.00%');
        expect(global.resultsSection.classList.contains('hidden')).toBe(false);
    });

    it('11. Processa a submissão e calcula nota parcial para acertos parciais', () => {
        global.startQuiz();
        document.getElementById('q1-A').click(); // incorreto (correto é B)
        document.getElementById('q2-B').click(); // correto
        global.submitButton.click();

        expect(global.scoreText.textContent).toContain('1 questões corretas');
        expect(global.percentageDisplay.textContent).toContain('50.00%');
    });

    it('12. Aplica classes de feedback visual corretas (.correct-answer-label, .incorrect-answer-label)', () => {
        global.startQuiz();
        document.getElementById('q1-A').click(); // Errado
        document.getElementById('q2-B').click(); // Certo
        global.submitButton.click();

        const q1LabelA = document.getElementById('q1-A').parentNode;
        const q2LabelB = document.getElementById('q2-B').parentNode;

        expect(q1LabelA.classList.contains('incorrect-answer-label')).toBe(true);
        expect(q2LabelB.classList.contains('correct-answer-label')).toBe(true);
    });

    it('13. Destaca sempre a resposta correta com .highlight-correct-answer-label após submissão', () => {
        global.startQuiz();
        document.getElementById('q1-A').click();
        document.getElementById('q2-A').click();
        global.submitButton.click();

        const q1LabelB = document.getElementById('q1-B').parentNode;
        expect(q1LabelB.classList.contains('highlight-correct-answer-label')).toBe(true);
    });

    it('14. Bloqueia novos cliques ou alterações após a submissão', () => {
        global.startQuiz();
        document.getElementById('q1-B').click();
        document.getElementById('q2-B').click();
        global.submitButton.click();

        const radioQ1A = document.getElementById('q1-A');
        expect(radioQ1A.disabled).toBe(true);
    });

    it('15. Suporta submissão automática por tempo esgotado submitQuiz(true)', () => {
        global.startQuiz();
        document.getElementById('q1-B').click();
        // q2 não respondida
        global.submitQuiz(true);

        expect(global.submitted).toBe(true);
        expect(global.resultsSection.classList.contains('hidden')).toBe(false);
        expect(global.scoreText.textContent).toContain('1 questões corretas');
    });

    it('16. Exibe feedback de aprovação (.pass) para percentual >= 90%', () => {
        global.questions = [{ id: 'q1', text: 'Q1', options: { A: '1', B: '2' }, correctAnswer: 'B' }];
        global.startQuiz();
        document.getElementById('q1-B').click();
        global.submitButton.click();

        expect(global.feedbackMessage.classList.contains('pass')).toBe(true);
        expect(global.feedbackMessage.textContent).toContain('Parabéns!');
    });

    it('17. Exibe feedback de reprovação (.fail) para percentual < 90%', () => {
        global.startQuiz();
        document.getElementById('q1-A').click();
        document.getElementById('q2-A').click();
        global.submitButton.click();

        expect(global.feedbackMessage.classList.contains('fail')).toBe(true);
        expect(global.feedbackMessage.textContent).toContain('Precisa melhorar');
    });

    it('18. Formata corretamente o tempo decorrido no formato MM:SS', () => {
        expect(global.formatTime(65)).toBe('01:05');
        expect(global.formatTime(600)).toBe('10:00');
        expect(global.formatTime(9)).toBe('00:09');
    });

    it('19. Armazena dados estruturados de performance em currentPerformanceData', () => {
        global.startQuiz();
        document.getElementById('q1-B').click();
        document.getElementById('q2-B').click();
        global.submitButton.click();

        expect(global.currentPerformanceData).toBeDefined();
        expect(global.currentPerformanceData.simuladoId).toBe('simulado_01');
        expect(global.currentPerformanceData.questoesCorretas).toBe(2);
        expect(global.currentPerformanceData.percentualAcerto).toBe(100);
    });

    it('20. Impede múltiplos envios duplicados se já submetido sem ser por tempo esgotado', () => {
        global.startQuiz();
        document.getElementById('q1-B').click();
        document.getElementById('q2-B').click();
        global.submitButton.click();

        const firstElapsed = global.finalTimeElapsed;
        global.submitQuiz(false); // Segundo clique
        expect(global.finalTimeElapsed).toBe(firstElapsed);
    });
});
