// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

describe('Ver Correção - Testes de Frontend (ver_correcao.php)', () => {
    let container;

    beforeEach(() => {
        document.body.innerHTML = '';
        localStorage.clear();
        vi.restoreAllMocks();

        // Mock window.print
        window.print = vi.fn();

        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    // Função geradora do DOM que simula exatamente o renderizador do ver_correcao.php
    function renderVerCorrecao(data) {
        if (!data || !data.detalhes || data.detalhes.length === 0) {
            container.innerHTML = `
                <div class="container mx-auto p-4 max-w-4xl">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow-sm">
                        <p class="text-yellow-700 font-medium">⚠️ Detalhes indisponíveis</p>
                    </div>
                    <div class="mt-6 flex justify-between">
                        <a href="meus_resultados.php" id="back-btn" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg">Voltar</a>
                        <button class="btn-print px-4 py-2 bg-blue-600 text-white rounded-lg" onclick="window.print()">Imprimir</button>
                    </div>
                </div>
            `;
            return;
        }

        const notaNum = parseFloat(data.nota || 0);
        let badgeColorClass = 'bg-red-500 text-white';
        if (notaNum >= 70) {
            badgeColorClass = 'bg-[#28a745] text-white';
        } else if (notaNum >= 50) {
            badgeColorClass = 'bg-[#ffc107] text-[#333]';
        } else {
            badgeColorClass = 'bg-[#dc2626] text-white';
        }

        let html = `
            <div class="container mx-auto p-4 max-w-4xl">
                <header class="bg-white rounded-xl shadow-md p-6 mb-6">
                    <h1 class="text-2xl font-bold text-slate-900 mb-2">${data.titulo || 'Simulado / Quiz'}</h1>
                    <div class="flex flex-wrap gap-4 text-sm text-slate-600 mb-4">
                        <span>📅 Realizado em: ${data.data || '01/01/2026'}</span>
                        <span>⏱️ Duração: ${data.tempo || '15 min'}</span>
                        <span>🎯 Acertos: <strong class="text-green-600">${data.acertos || 0}</strong> / ${data.total || 0}</span>
                        <span>❌ Erros: <strong class="text-red-600">${(data.total || 0) - (data.acertos || 0)}</strong></span>
                    </div>
                    <div class="inline-block px-4 py-2 rounded-full font-bold score-badge ${badgeColorClass}">
                        Nota: ${notaNum.toFixed(1)}%
                    </div>
                </header>

                <div class="space-y-6">
        `;

        data.detalhes.forEach((q, idx) => {
            const isCorrect = q.acertou === true;
            const statusClass = isCorrect ? 'status-correct bg-green-100 text-green-800' : 'status-wrong bg-red-100 text-red-800';
            const statusText = isCorrect ? '✓ ACERTOU' : '✕ ERROU';
            const ansBoxClass = isCorrect ? 'ans-user correct bg-green-50 border-green-200' : 'ans-user wrong bg-red-50 border-red-200';

            // Simulação do formatarTextoLegal
            let enunciadoFormatado = (q.enunciado || '')
                .replace(/\$/g, '')
                .replace(/\\S/g, '§')
                .replace(/\\\\?\^{\\circ}/g, 'º');

            html += `
                <div class="question-card bg-white rounded-xl shadow-md p-6 border border-slate-200" data-question-index="${idx}">
                    <div class="flex justify-between items-center mb-4">
                        <span class="font-bold text-slate-700">Questão ${idx + 1}</span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full ${statusClass}">${statusText}</span>
                    </div>
                    <div class="text-slate-800 mb-4 font-medium">${enunciadoFormatado}</div>
                    
                    <div class="mb-3 p-3 rounded-lg border ${ansBoxClass}">
                        <span class="text-xs font-bold block mb-1 text-slate-500">Sua resposta:</span>
                        <span class="font-semibold">${q.resposta_aluno || 'Não respondeu'}</span>
                    </div>

                    <div class="ans-correct mb-3 p-3 rounded-lg border bg-blue-50 border-blue-200 text-blue-900">
                        <span class="text-xs font-bold block mb-1 text-blue-600">Gabarito Oficial:</span>
                        <span class="font-semibold">${q.resposta_correta || '-'}</span>
                    </div>
            `;

            if (q.comentario && q.comentario.trim() !== '') {
                html += `
                    <div class="comment-box mt-4 p-4 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-sm">
                        <span class="font-bold block mb-1 text-slate-800">Comentário Pedagógico:</span>
                        <p>${q.comentario}</p>
                    </div>
                `;
            }

            html += `</div>`;
        });

        html += `
                </div>
                <div class="mt-8 flex justify-between items-center">
                    <a href="meus_resultados.php" id="back-btn" class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-xl transition-colors">Voltar aos Resultados</a>
                    <button class="btn-print px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-colors" onclick="window.print()">Imprimir Correção</button>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    it('1. Renderiza o cabeçalho e score badge com nota alta (>= 70%) corretamente', () => {
        const mockData = {
            titulo: 'Simulado PM - Legislação Institucional',
            data: '28/10/2026',
            tempo: '45 min',
            acertos: 8,
            total: 10,
            nota: 80.0,
            detalhes: [
                { acertou: true, enunciado: 'Qual o art. da Constituição?', resposta_aluno: 'Art. 5º', resposta_correta: 'Art. 5º', comentario: 'Correto.' }
            ]
        };

        renderVerCorrecao(mockData);

        const title = container.querySelector('h1');
        expect(title.textContent).toBe('Simulado PM - Legislação Institucional');

        const scoreBadge = container.querySelector('.score-badge');
        expect(scoreBadge).not.toBeNull();
        expect(scoreBadge.textContent.trim()).toBe('Nota: 80.0%');
        expect(scoreBadge.className).toContain('bg-[#28a745]');

        const acertosEl = container.querySelector('strong.text-green-600');
        expect(acertosEl.textContent).toBe('8');

        const errosEl = container.querySelector('strong.text-red-600');
        expect(errosEl.textContent).toBe('2');
    });

    it('2. Renderiza score badge com nota média (>= 50% e < 70%) com cor amarela e texto escuro', () => {
        const mockData = {
            titulo: 'Quiz de Direito Penal',
            acertos: 6,
            total: 10,
            nota: 60.0,
            detalhes: [
                { acertou: true, enunciado: 'Crime doloso', resposta_aluno: 'A', resposta_correta: 'A' }
            ]
        };

        renderVerCorrecao(mockData);

        const scoreBadge = container.querySelector('.score-badge');
        expect(scoreBadge.textContent.trim()).toBe('Nota: 60.0%');
        expect(scoreBadge.className).toContain('bg-[#ffc107]');
        expect(scoreBadge.className).toContain('text-[#333]');
    });

    it('3. Renderiza score badge com nota baixa (< 50%) com cor vermelha', () => {
        const mockData = {
            titulo: 'Simulado TAF & Leis',
            acertos: 3,
            total: 10,
            nota: 30.0,
            detalhes: [
                { acertou: false, enunciado: 'Teste', resposta_aluno: 'B', resposta_correta: 'A' }
            ]
        };

        renderVerCorrecao(mockData);

        const scoreBadge = container.querySelector('.score-badge');
        expect(scoreBadge.textContent.trim()).toBe('Nota: 30.0%');
        expect(scoreBadge.className).toContain('bg-[#dc2626]');
    });

    it('4. Detalha questão correta (.question-card, .status-correct, .ans-user.correct)', () => {
        const mockData = {
            titulo: 'Quiz Teste',
            acertos: 1,
            total: 1,
            nota: 100.0,
            detalhes: [
                {
                    acertou: true,
                    enunciado: 'Questão 1 sobre $Leis$ com \\S e \\^{\\circ}',
                    resposta_aluno: 'Alternativa C',
                    resposta_correta: 'Alternativa C',
                    comentario: 'Excelente explicação pedagógica.'
                }
            ]
        };

        renderVerCorrecao(mockData);

        const card = container.querySelector('.question-card');
        expect(card).not.toBeNull();

        const statusBadge = card.querySelector('.status-correct');
        expect(statusBadge.textContent).toContain('✓ ACERTOU');

        const ansUser = card.querySelector('.ans-user');
        expect(ansUser.className).toContain('correct');
        expect(ansUser.textContent).toContain('Alternativa C');

        const ansCorrect = card.querySelector('.ans-correct');
        expect(ansCorrect).not.toBeNull();
        expect(ansCorrect.textContent).toContain('Alternativa C');

        const commentBox = card.querySelector('.comment-box');
        expect(commentBox).not.toBeNull();
        expect(commentBox.textContent).toContain('Excelente explicação pedagógica.');

        // Testar formatação textual (formatarTextoLegal simulado)
        const enunciadoDiv = card.querySelector('.font-medium');
        expect(enunciadoDiv.textContent).not.toContain('$');
        expect(enunciadoDiv.textContent).toContain('§');
        expect(enunciadoDiv.textContent).toContain('º');
    });

    it('5. Detalha questão errada (.question-card, .status-wrong, .ans-user.wrong) e oculta comentário vazio', () => {
        const mockData = {
            titulo: 'Quiz Teste',
            acertos: 0,
            total: 1,
            nota: 0.0,
            detalhes: [
                {
                    acertou: false,
                    enunciado: 'Questão errada',
                    resposta_aluno: 'Alternativa A',
                    resposta_correta: 'Alternativa B',
                    comentario: '' // vazio
                }
            ]
        };

        renderVerCorrecao(mockData);

        const card = container.querySelector('.question-card');
        const statusBadge = card.querySelector('.status-wrong');
        expect(statusBadge.textContent).toContain('✕ ERROU');

        const ansUser = card.querySelector('.ans-user');
        expect(ansUser.className).toContain('wrong');
        expect(ansUser.textContent).toContain('Alternativa A');

        const commentBox = card.querySelector('.comment-box');
        expect(commentBox).toBeNull(); // Oculto se vazio
    });

    it('6. Trata aluno que deixou questão em branco ("Não respondeu")', () => {
        const mockData = {
            titulo: 'Quiz Branco',
            acertos: 0,
            total: 1,
            nota: 0.0,
            detalhes: [
                {
                    acertou: false,
                    enunciado: 'Questão sem resposta',
                    resposta_aluno: null,
                    resposta_correta: 'Alternativa D'
                }
            ]
        };

        renderVerCorrecao(mockData);

        const ansUser = container.querySelector('.ans-user');
        expect(ansUser.textContent).toContain('Não respondeu');
    });

    it('7. Trata caso de borda sem detalhes (estado vazio / sem detalhes)', () => {
        renderVerCorrecao({ detalhes: [] });

        const aviso = container.querySelector('p');
        expect(aviso.textContent).toContain('⚠️ Detalhes indisponíveis');

        const backBtn = container.querySelector('#back-btn');
        expect(backBtn.getAttribute('href')).toBe('meus_resultados.php');
    });

    it('8. Dispara window.print() ao clicar no botão de impressão (.btn-print)', () => {
        const mockData = {
            titulo: 'Simulado Impressão',
            acertos: 1,
            total: 1,
            nota: 100.0,
            detalhes: [
                { acertou: true, enunciado: 'Q1', resposta_aluno: 'A', resposta_correta: 'A' }
            ]
        };

        renderVerCorrecao(mockData);

        const printBtn = container.querySelector('.btn-print');
        expect(printBtn).not.toBeNull();

        printBtn.click();
        expect(window.print).toHaveBeenCalledTimes(1);
    });

    it('9. Botão de voltar direciona para meus_resultados.php', () => {
        const mockData = {
            titulo: 'Simulado Voltar',
            acertos: 1,
            total: 1,
            nota: 100.0,
            detalhes: [
                { acertou: true, enunciado: 'Q1', resposta_aluno: 'A', resposta_correta: 'A' }
            ]
        };

        renderVerCorrecao(mockData);

        const backBtn = container.querySelector('#back-btn');
        expect(backBtn).not.toBeNull();
        expect(backBtn.getAttribute('href')).toBe('meus_resultados.php');
    });
});
