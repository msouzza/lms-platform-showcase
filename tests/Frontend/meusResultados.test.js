// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

describe('Meus Resultados - Testes de Frontend (meus_resultados.php)', () => {
    let container;

    // Helper de escape HTML idêntico ao backend para segurança
    const escapeHTML = (str) => {
        if (!str) return '';
        return String(str).replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    };

    // Função auxiliar de cores correspondente ao PHP getCorNota($nota)
    const getCorNota = (nota) => {
        if (nota >= 70) return { bg: '#d4edda', text: '#155724', border: '#c3e6cb' };
        if (nota >= 50) return { bg: '#fff3cd', text: '#856404', border: '#ffeeba' };
        return { bg: '#f8d7da', text: '#721c24', border: '#f5c6cb' };
    };

    // Helper de renderização da página meus_resultados.php simulando dados e abas
    const renderMeusResultados = (simulados = [], quizzes = [], mediaGeralSim = 75.0, mediaGeralQuiz = 70.0) => {
        document.body.innerHTML = '';
        container = document.createElement('div');

        // Cálculo da média do aluno
        const totalSim = simulados.length;
        const somaSim = simulados.reduce((acc, s) => acc + Number(s.nota || 0), 0);
        const mediaSimAluno = totalSim > 0 ? (somaSim / totalSim) : 0;

        const totalQuiz = quizzes.length;
        const somaQuiz = quizzes.reduce((acc, q) => acc + Number(q.nota || 0), 0);
        const mediaQuizAluno = totalQuiz > 0 ? (somaQuiz / totalQuiz) : 0;

        const statusSim = mediaSimAluno >= mediaGeralSim ? 'acima-media' : 'abaixo-media';
        const statusQuiz = mediaQuizAluno >= mediaGeralQuiz ? 'acima-media' : 'abaixo-media';

        container.innerHTML = `
            <div class="container" style="max-width: 900px; margin: 30px auto; padding: 0 20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h1 style="margin:0;">Meu Desempenho</h1>
                    <a href="area_aluno.php" class="btn-voltar-red">Fechar</a>
                </div>

                <div class="dashboard-desempenho" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Card Simulados -->
                    <div class="card-desempenho">
                        <div class="card-titulo">
                            <span>🎯 Média em Simulados</span>
                        </div>
                        ${totalSim > 0 ? `
                            <div class="comparativo-box ${statusSim}">
                                <div class="nota-box">
                                    <div class="nota-label">Sua Média</div>
                                    <div class="nota-valor">${mediaSimAluno.toFixed(1)}%</div>
                                </div>
                                <div class="vs-badge">VS</div>
                                <div class="nota-box">
                                    <div class="nota-label">Concorrência</div>
                                    <div class="nota-valor geral-valor">${mediaGeralSim.toFixed(1)}%</div>
                                </div>
                            </div>
                            ${statusSim === 'acima-media' ? 
                                '<div class="feedback-msg verde">🚀 Excelente! Você está acima da média. A vaga está próxima!</div>' : 
                                '<div class="feedback-msg laranja">⚠️ Atenção! Você está abaixo da média geral. Foco na revisão!</div>'
                            }
                        ` : `
                            <p style="color: #999; font-size: 0.9rem; text-align:center; margin-top: 20px;">Faça seu primeiro simulado para comparar sua nota.</p>
                        `}
                    </div>

                    <!-- Card Quizzes -->
                    <div class="card-desempenho">
                        <div class="card-titulo">
                            <span>⚡ Média em Quizzes</span>
                        </div>
                        ${totalQuiz > 0 ? `
                            <div class="comparativo-box ${statusQuiz}">
                                <div class="nota-box">
                                    <div class="nota-label">Sua Média</div>
                                    <div class="nota-valor">${mediaQuizAluno.toFixed(1)}%</div>
                                </div>
                                <div class="vs-badge">VS</div>
                                <div class="nota-box">
                                    <div class="nota-label">Concorrência</div>
                                    <div class="nota-valor geral-valor">${mediaGeralQuiz.toFixed(1)}%</div>
                                </div>
                            </div>
                            ${statusQuiz === 'acima-media' ? 
                                '<div class="feedback-msg verde">🔥 Parabéns! Seu desempenho nos testes rápidos supera a concorrência.</div>' : 
                                '<div class="feedback-msg laranja">⚠️ Cuidado! A concorrência está mais rápida e precisa nos quizzes.</div>'
                            }
                        ` : `
                            <p style="color: #999; font-size: 0.9rem; text-align:center; margin-top: 20px;">Faça seu primeiro quiz para comparar sua agilidade.</p>
                        `}
                    </div>
                </div>

                <div class="tabs-container">
                    <button class="tab-btn active" onclick="openTab('simulados')">📚 Meus Simulados</button>
                    <button class="tab-btn" onclick="openTab('quizzes')">⚡ Meus Quizzes</button>
                </div>

                <!-- Aba Simulados -->
                <div id="view-simulados" class="tab-content active">
                    ${totalSim === 0 ? `
                        <div class="empty-state">Você ainda não realizou nenhum simulado neste curso.</div>
                    ` : simulados.map(sim => {
                        const cor = getCorNota(sim.nota);
                        const erros = (sim.total_questoes || 0) - (sim.acertos || 0);
                        const tempo = sim.tempo_decorrido || '--:--:--';
                        return `
                            <div class="result-item" style="border-left-color: ${cor.text};">
                                <div class="res-info">
                                    <h3>${escapeHTML(sim.titulo)}</h3>
                                    <div class="res-meta">
                                        📅 Realizado em: <strong>${sim.data || '01/01/2026 12:00'}</strong><br>
                                        ⏱ Duração: <strong>${tempo}</strong><br>
                                        📊 Total: <strong>${sim.total_questoes || 0}</strong> |
                                        ✅ Acertos: <strong style="color:green">${sim.acertos || 0}</strong> |
                                        ❌ Erros: <strong style="color:red">${erros}</strong>
                                    </div>
                                </div>
                                <div class="res-score">
                                    <span class="score-badge" style="background:${cor.bg}; color:${cor.text};">
                                        Nota: ${Number(sim.nota).toFixed(1)}
                                    </span>
                                    <br>
                                    <a href="ver_correcao.php?id=${sim.id}&tipo=simulado" class="btn-details">Ver Correção</a>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>

                <!-- Aba Quizzes -->
                <div id="view-quizzes" class="tab-content">
                    ${totalQuiz === 0 ? `
                        <div class="empty-state">Você ainda não realizou nenhum quiz rápido neste curso.</div>
                    ` : quizzes.map(quiz => {
                        const cor = getCorNota(quiz.nota);
                        const erros = (quiz.total_questoes || 0) - (quiz.acertos || 0);
                        return `
                            <div class="result-item" style="border-left-color: ${cor.text};">
                                <div class="res-info">
                                    <h3>${escapeHTML(quiz.titulo)}</h3>
                                    <div class="res-meta">
                                        📅 Realizado em: <strong>${quiz.data || '01/01/2026 12:00'}</strong><br>
                                        📊 Total: <strong>${quiz.total_questoes || 0}</strong> |
                                        ✅ Acertos: <strong style="color:green">${quiz.acertos || 0}</strong> |
                                        ❌ Erros: <strong style="color:red">${erros}</strong>
                                    </div>
                                </div>
                                <div class="res-score">
                                    <span class="score-badge" style="background:${cor.bg}; color:${cor.text};">
                                        Nota: ${Number(quiz.nota).toFixed(1)}
                                    </span>
                                    <br>
                                    <a href="ver_correcao.php?id=${quiz.id}&tipo=quiz" class="btn-details">Ver Correção</a>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;

        document.body.appendChild(container);

        // Definir função openTab global para testes de interatividade
        window.openTab = function(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('view-simulados').classList.remove('active');
            document.getElementById('view-quizzes').classList.remove('active');

            // Simula event.currentTarget quando acionado via click
            if (window.event && window.event.currentTarget) {
                window.event.currentTarget.classList.add('active');
            } else {
                const btnTarget = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick')?.includes(tabName));
                if (btnTarget) btnTarget.classList.add('active');
            }
            
            const targetContent = document.getElementById('view-' + tabName);
            if (targetContent) targetContent.classList.add('active');
        };
    };

    beforeEach(() => {
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('1. Renderiza estrutura inicial e botão de fechar', () => {
        renderMeusResultados();
        expect(document.querySelector('h1').textContent).toContain('Meu Desempenho');
        const btnFechar = document.querySelector('.btn-voltar-red');
        expect(btnFechar).toBeTruthy();
        expect(btnFechar.getAttribute('href')).toBe('area_aluno.php');
        expect(btnFechar.textContent.trim()).toBe('Fechar');
    });

    it('2. Sistema de Abas (openTab) alterna corretamente entre simulados e quizzes', () => {
        renderMeusResultados();

        const btnSimulados = document.querySelector('button[onclick*="simulados"]');
        const btnQuizzes = document.querySelector('button[onclick*="quizzes"]');
        const viewSimulados = document.getElementById('view-simulados');
        const viewQuizzes = document.getElementById('view-quizzes');

        // Estado inicial: Simulados ativo
        expect(btnSimulados.classList.contains('active')).toBe(true);
        expect(btnQuizzes.classList.contains('active')).toBe(false);
        expect(viewSimulados.classList.contains('active')).toBe(true);
        expect(viewQuizzes.classList.contains('active')).toBe(false);

        // Clicar na aba Quizzes
        const clickEvent = { currentTarget: btnQuizzes };
        vi.stubGlobal('event', clickEvent);

        window.openTab('quizzes');

        expect(btnSimulados.classList.contains('active')).toBe(false);
        expect(btnQuizzes.classList.contains('active')).toBe(true);
        expect(viewSimulados.classList.contains('active')).toBe(false);
        expect(viewQuizzes.classList.contains('active')).toBe(true);

        // Voltar para Simulados
        clickEvent.currentTarget = btnSimulados;
        window.openTab('simulados');

        expect(btnSimulados.classList.contains('active')).toBe(true);
        expect(btnQuizzes.classList.contains('active')).toBe(false);
        expect(viewSimulados.classList.contains('active')).toBe(true);
        expect(viewQuizzes.classList.contains('active')).toBe(false);
    });

    it('3. Painel de Desempenho e Comparativo (acima-media / abaixo-media)', () => {
        // Aluno com nota 85 em simulado (concorrência 75) -> acima-media
        // Aluno com nota 40 em quiz (concorrência 70) -> abaixo-media
        const simulados = [{ id: 1, titulo: 'Simulado PM 01', nota: 85.0, acertos: 17, total_questoes: 20, tempo_decorrido: '01:30:00', data: '25/02/2026 14:00' }];
        const quizzes = [{ id: 2, titulo: 'Quiz Direito Penal', nota: 40.0, acertos: 4, total_questoes: 10, data: '24/02/2026 10:00' }];

        renderMeusResultados(simulados, quizzes, 75.0, 70.0);

        // Verifica card de simulado (acima da média)
        const simComparativoBox = document.querySelector('#view-simulados').previousElementSibling ? document.querySelectorAll('.comparativo-box')[0] : document.querySelectorAll('.comparativo-box')[0];
        expect(document.querySelectorAll('.comparativo-box')[0].classList.contains('acima-media')).toBe(true);
        expect(document.querySelector('.feedback-msg.verde')).toBeTruthy();
        expect(document.querySelector('.feedback-msg.verde').textContent).toContain('Excelente! Você está acima da média');

        // Verifica card de quiz (abaixo da média)
        expect(document.querySelectorAll('.comparativo-box')[1].classList.contains('abaixo-media')).toBe(true);
        expect(document.querySelector('.feedback-msg.laranja')).toBeTruthy();
        expect(document.querySelector('.feedback-msg.laranja').textContent).toContain('Cuidado! A concorrência está mais rápida');
    });

    it('4. Listagem de Itens (.result-item) de Simulados com badges e links corretos', () => {
        const simulados = [
            { id: 101, titulo: 'Simulado Geral PM-SP', nota: 85.0, acertos: 85, total_questoes: 100, tempo_decorrido: '02:15:00', data: '25/02/2026 15:30' },
            { id: 102, titulo: 'Simulado Red intermediário', nota: 62.5, acertos: 25, total_questoes: 40, tempo_decorrido: '00:50:00', data: '20/02/2026 09:00' },
            { id: 103, titulo: 'Simulado Iniciante', nota: 45.0, acertos: 9, total_questoes: 20, tempo_decorrido: '00:30:00', data: '15/02/2026 08:00' }
        ];

        renderMeusResultados(simulados, [], 70.0, 70.0);

        const items = document.querySelectorAll('#view-simulados .result-item');
        expect(items.length).toBe(3);

        // Item 1: Nota >= 70 (verde)
        expect(items[0].querySelector('h3').textContent).toBe('Simulado Geral PM-SP');
        expect(items[0].querySelector('.score-badge').textContent).toContain('Nota: 85.0');
        expect(items[0].querySelector('.score-badge').style.background).toBe('rgb(212, 237, 218)'); // #d4edda
        const link1 = items[0].querySelector('.btn-details');
        expect(link1.getAttribute('href')).toBe('ver_correcao.php?id=101&tipo=simulado');
        expect(link1.textContent.trim()).toBe('Ver Correção');

        // Item 2: Nota >= 50 e < 70 (amarelo)
        expect(items[1].querySelector('.score-badge').textContent).toContain('Nota: 62.5');
        expect(items[1].querySelector('.score-badge').style.background).toBe('rgb(255, 243, 205)'); // #fff3cd

        // Item 3: Nota < 50 (vermelho)
        expect(items[2].querySelector('.score-badge').textContent).toContain('Nota: 45.0');
        expect(items[2].querySelector('.score-badge').style.background).toBe('rgb(248, 215, 218)'); // #f8d7da
    });

    it('5. Listagem de Itens (.result-item) de Quizzes com links corretos', () => {
        const quizzes = [
            { id: 201, titulo: 'Quiz Processo Penal', nota: 90.0, acertos: 9, total_questoes: 10, data: '26/02/2026 11:00' }
        ];

        renderMeusResultados([], quizzes, 70.0, 70.0);

        // Mudar para aba de quizzes para inspecionar
        window.openTab('quizzes');

        const items = document.querySelectorAll('#view-quizzes .result-item');
        expect(items.length).toBe(1);
        expect(items[0].querySelector('h3').textContent).toBe('Quiz Processo Penal');
        expect(items[0].querySelector('.score-badge').textContent).toContain('Nota: 90.0');
        
        const linkQuiz = items[0].querySelector('.btn-details');
        expect(linkQuiz.getAttribute('href')).toBe('ver_correcao.php?id=201&tipo=quiz');
    });

    it('6. Empty States (.empty-state) quando não há simulados ou quizzes realizados', () => {
        renderMeusResultados([], [], 70.0, 70.0);

        // Aba Simulados vazia
        const emptySim = document.querySelector('#view-simulados .empty-state');
        expect(emptySim).toBeTruthy();
        expect(emptySim.textContent).toBe('Você ainda não realizou nenhum simulado neste curso.');

        // Aba Quizzes vazia
        window.openTab('quizzes');
        const emptyQuiz = document.querySelector('#view-quizzes .empty-state');
        expect(emptyQuiz).toBeTruthy();
        expect(emptyQuiz.textContent).toBe('Você ainda não realizou nenhum quiz rápido neste curso.');
    });
});
