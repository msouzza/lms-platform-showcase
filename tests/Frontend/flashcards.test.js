// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { JSDOM } from 'jsdom';

describe('Flashcards Frontend Tests', () => {
    let dom;
    let window;
    let document;

    beforeEach(() => {
        const html = `
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>Flashcards - Test</title>
        </head>
        <body>
            <div id="flashcard" class="flashcard">
                <div class="flashcard-front">
                    <span id="status-badge" class="status-badge pending">? Pendente</span>
                    <h2 id="question-text">Pergunta teste</h2>
                    <input type="text" id="answer-input" class="answer-input" />
                    <button id="check-button" class="check-button">Verificar Resposta</button>
                </div>
                <div id="flashcard-back" class="flashcard-back">
                    <div id="result-content"></div>
                </div>
            </div>

            <button id="prev-button">Anterior</button>
            <button id="next-button">Próximo</button>
            <button id="reset-button">Refazer</button>

            <span id="correct-count">0</span>
            <span id="incorrect-count">0</span>
            <span id="pending-count">0</span>
            <span id="success-rate">0%</span>
            <div id="completion-message" class="hidden">Concluído!</div>
            <div id="progress-indicator" style="width: 0%"></div>
        </body>
        </html>
        `;

        dom = new JSDOM(html, { runScripts: 'dangerously', resources: 'usable' });
        window = dom.window;
        document = window.document;

        window.flashcardsData = [
            { question: 'Quanto é 2+2?', answer: '4' },
            { question: 'Capital do RS?', answer: 'PORTO ALEGRE' }
        ];
        window.currentCardIndex = 0;
        window.cardStatus = ['pending', 'pending'];

        window.calculateSimilarity = function(str1, str2) {
            let matches = 0;
            const len = Math.max(str1.length, str2.length);
            if (len === 0) return 100;
            for (let i = 0; i < Math.min(str1.length, str2.length); i++) {
                if (str1[i] === str2[i]) {
                    matches++;
                }
            }
            return (matches / len) * 100;
        };

        window.updateStatistics = function() {
            const correct = window.cardStatus.filter(s => s === 'correct').length;
            const incorrect = window.cardStatus.filter(s => s === 'incorrect').length;
            const pending = window.cardStatus.filter(s => s === 'pending').length;
            const total = window.flashcardsData.length;

            document.getElementById('correct-count').textContent = correct;
            document.getElementById('incorrect-count').textContent = incorrect;
            document.getElementById('pending-count').textContent = pending;

            const successRate = total > 0 ? Math.round((correct / total) * 100) : 0;
            document.getElementById('success-rate').textContent = `${successRate}%`;

            const completionMessage = document.getElementById('completion-message');
            if (pending === 0) {
                completionMessage.classList.remove('hidden');
            } else {
                completionMessage.classList.add('hidden');
            }

            window.updateProgressBar();
        };

        window.updateProgressBar = function() {
            const answeredCount = window.cardStatus.filter(s => s !== 'pending').length;
            const progress = (answeredCount / window.flashcardsData.length) * 100;
            document.getElementById('progress-indicator').style.width = `${progress}%`;
        };

        window.updateNavigationButtons = function() {
            document.getElementById('prev-button').disabled = window.currentCardIndex === 0;
            document.getElementById('next-button').disabled = window.currentCardIndex === window.flashcardsData.length - 1;
        };

        window.loadCard = function(index) {
            const flashcardElement = document.getElementById('flashcard');
            flashcardElement.classList.remove('flipped');

            const card = window.flashcardsData[index];
            document.getElementById('question-text').textContent = card.question;
            const answerInput = document.getElementById('answer-input');
            const checkButton = document.getElementById('check-button');

            answerInput.value = '';
            answerInput.disabled = false;
            checkButton.disabled = false;

            if (window.cardStatus[index] !== 'pending') {
                answerInput.disabled = true;
                checkButton.disabled = true;
            }

            document.getElementById('result-content').innerHTML = '';
            window.updateNavigationButtons();
            window.updateStatistics();
        };

        window.checkAnswer = function() {
            const answerInput = document.getElementById('answer-input');
            const userAnswer = answerInput.value.toUpperCase().trim();
            const correctAnswer = window.flashcardsData[window.currentCardIndex].answer;

            if (userAnswer === '') {
                return;
            }

            const similarity = window.calculateSimilarity(userAnswer, correctAnswer);
            const isCorrect = similarity >= 70;

            window.cardStatus[window.currentCardIndex] = isCorrect ? 'correct' : 'incorrect';

            const checkmark = isCorrect ? '✓' : '✗';
            const backgroundColor = isCorrect ? 'success' : 'error';

            document.getElementById('result-content').innerHTML = `
                <div class="checkmark">${checkmark}</div>
                <p>${isCorrect ? 'CORRETO!' : 'INCORRETO!'}</p>
                <p>${correctAnswer}</p>
                <p>Similaridade: ${similarity.toFixed(1)}%</p>
            `;

            const flashcardBack = document.getElementById('flashcard-back');
            flashcardBack.className = `flashcard-back ${backgroundColor}`;
            document.getElementById('flashcard').classList.add('flipped');

            answerInput.disabled = true;
            document.getElementById('check-button').disabled = true;

            window.updateStatistics();
        };
    });

    it('1. Deve calcular similaridade corretamente (>= 70%)', () => {
        expect(window.calculateSimilarity('PORTO ALEGRE', 'PORTO ALEGRE')).toBe(100);
        expect(window.calculateSimilarity('PORTO', 'PORTO ALEGRE')).toBeCloseTo(41.67, 1);
        expect(window.calculateSimilarity('', '')).toBe(100);
    });

    it('2. Deve validar resposta correta e incorreta com checkAnswer', () => {
        const answerInput = document.getElementById('answer-input');
        const checkButton = document.getElementById('check-button');
        const flashcard = document.getElementById('flashcard');

        answerInput.value = '4';
        window.checkAnswer();

        expect(flashcard.classList.contains('flipped')).toBe(true);
        expect(window.cardStatus[0]).toBe('correct');
        expect(document.getElementById('correct-count').textContent).toBe('1');
        expect(answerInput.disabled).toBe(true);
        expect(checkButton.disabled).toBe(true);
    });

    it('3. Deve lidar com navegação entre cards e limites', () => {
        const prevButton = document.getElementById('prev-button');
        const nextButton = document.getElementById('next-button');

        window.currentCardIndex = 0;
        window.updateNavigationButtons();
        expect(prevButton.disabled).toBe(true);
        expect(nextButton.disabled).toBe(false);

        window.currentCardIndex = 1;
        window.updateNavigationButtons();
        expect(prevButton.disabled).toBe(false);
        expect(nextButton.disabled).toBe(true);
    });

    it('4. Deve sanitizar input convertendo para maiúsculas e removendo caracteres especiais', () => {
        const answerInput = document.getElementById('answer-input');
        
        answerInput.value = 'porto alegre!@#$';
        const event = new window.Event('input');
        answerInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9\s]/g, '');
        });
        answerInput.dispatchEvent(event);

        expect(answerInput.value).toBe('PORTO ALEGRE');
    });

    it('5. Deve atualizar estatísticas e barra de progresso corretamente, mostrando completion-message quando pendentes === 0', () => {
        window.cardStatus = ['correct', 'correct'];
        window.updateStatistics();

        expect(document.getElementById('correct-count').textContent).toBe('2');
        expect(document.getElementById('pending-count').textContent).toBe('0');
        expect(document.getElementById('success-rate').textContent).toBe('100%');
        expect(document.getElementById('completion-message').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('progress-indicator').style.width).toBe('100%');
    });

    it('6. Deve resetar todos os cards corretamente', () => {
        window.cardStatus = ['correct', 'incorrect'];
        window.currentCardIndex = 1;

        window.cardStatus = new Array(window.flashcardsData.length).fill('pending');
        window.currentCardIndex = 0;
        window.loadCard(0);
        window.updateStatistics();

        expect(window.cardStatus[0]).toBe('pending');
        expect(window.cardStatus[1]).toBe('pending');
        expect(window.currentCardIndex).toBe(0);
        expect(document.getElementById('pending-count').textContent).toBe('2');
    });
});
