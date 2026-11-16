import { describe, it, expect, beforeEach } from 'vitest';

// Implementação pura da lógica e renderização do Ranking Visual (conforme especificações de ranking_visual.php)
export function getBadge(pontos) {
    if (pontos >= 1000) return "🎯 Sniper";
    if (pontos >= 800) return "⚡ Força Tática";
    if (pontos >= 500) return "🛡️ Operacional";
    return "📚 Cadete";
}

export function renderRanking(alunos, containerPodium, containerList) {
    if (!containerPodium || !containerList) return;

    containerPodium.innerHTML = '';
    containerList.innerHTML = '';

    // Ordenar alunos por pontuação decrescente e garantir posições relativas
    const sortedAlunos = [...alunos].sort((a, b) => b.pontos - a.pontos);

    sortedAlunos.forEach((aluno, index) => {
        const posicao = index + 1;
        
        if (posicao <= 3) {
            const card = document.createElement('div');
            card.className = `podium-card ${posicao === 1 ? 'first' : posicao === 2 ? 'second' : 'third'}`;
            
            const medalha = posicao === 1 ? '🥇' : posicao === 2 ? '🥈' : '🥉';
            
            card.innerHTML = `
                <div class="podium-medal">${medalha}</div>
                <div class="podium-name">${escapeHTML(aluno.nome)}</div>
                <div class="row-badge">${getBadge(aluno.pontos)}</div>
                <div class="podium-pts">${aluno.pontos} pts</div>
            `;
            containerPodium.appendChild(card);
        } else {
            const row = document.createElement('div');
            row.className = 'ranking-row';
            
            row.innerHTML = `
                <div class="pos-number">${posicao}º</div>
                <div class="row-name">${escapeHTML(aluno.nome)}</div>
                <div class="row-badge">${getBadge(aluno.pontos)}</div>
                <div class="row-pts">${aluno.pontos} pts</div>
            `;
            containerList.appendChild(row);
        }
    });
}

function escapeHTML(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

describe('Ranking Visual Frontend Test Suite', () => {
    let podiumContainer;
    let listContainer;

    beforeEach(() => {
        document.body.innerHTML = `
            <div id="podium-container"></div>
            <div id="list-container"></div>
        `;
        podiumContainer = document.getElementById('podium-container');
        listContainer = document.getElementById('list-container');
    });

    describe('1. Função getBadge(pontos) e Valores de Fronteira', () => {
        it('deve retornar "🎯 Sniper" para pontos >= 1000', () => {
            expect(getBadge(1000)).toBe('🎯 Sniper');
            expect(getBadge(1500)).toBe('🎯 Sniper');
        });

        it('deve lidar corretamente com o limite inferior de Sniper (999)', () => {
            expect(getBadge(999)).toBe('⚡ Força Tática');
        });

        it('deve retornar "⚡ Força Tática" para 800 <= pontos < 1000', () => {
            expect(getBadge(800)).toBe('⚡ Força Tática');
            expect(getBadge(950)).toBe('⚡ Força Tática');
        });

        it('deve lidar corretamente com o limite inferior de Força Tática (799)', () => {
            expect(getBadge(799)).toBe('🛡️ Operacional');
        });

        it('deve retornar "🛡️ Operacional" para 500 <= pontos < 800', () => {
            expect(getBadge(500)).toBe('🛡️ Operacional');
            expect(getBadge(750)).toBe('🛡️ Operacional');
        });

        it('deve lidar corretamente com o limite inferior de Operacional (499)', () => {
            expect(getBadge(499)).toBe('📚 Cadete');
        });

        it('deve retornar "📚 Cadete" para pontos < 500', () => {
            expect(getBadge(0)).toBe('📚 Cadete');
            expect(getBadge(250)).toBe('📚 Cadete');
        });
    });

    describe('2. Pódio (Top 3 em #podium-container)', () => {
        it('deve renderizar corretamente o pódio com 1º, 2º e 3º lugares com classes e medalhas corretas', () => {
            const alunos = [
                { nome: 'Carlos Silva', pontos: 1200 },
                { nome: 'Ana Souza', pontos: 900 },
                { nome: 'Bruno Lima', pontos: 600 }
            ];

            renderRanking(alunos, podiumContainer, listContainer);

            const cards = podiumContainer.querySelectorAll('.podium-card');
            expect(cards.length).toBe(3);

            // 1º Lugar (.first)
            expect(cards[0].classList.contains('first')).toBe(true);
            expect(cards[0].querySelector('.podium-medal').textContent).toBe('🥇');
            expect(cards[0].querySelector('.podium-name').textContent).toBe('Carlos Silva');
            expect(cards[0].querySelector('.row-badge').textContent).toBe('🎯 Sniper');
            expect(cards[0].querySelector('.podium-pts').textContent).toBe('1200 pts');

            // 2º Lugar (.second)
            expect(cards[1].classList.contains('second')).toBe(true);
            expect(cards[1].querySelector('.podium-medal').textContent).toBe('🥈');
            expect(cards[1].querySelector('.podium-name').textContent).toBe('Ana Souza');
            expect(cards[1].querySelector('.row-badge').textContent).toBe('⚡ Força Tática');

            // 3º Lugar (.third)
            expect(cards[2].classList.contains('third')).toBe(true);
            expect(cards[2].querySelector('.podium-medal').textContent).toBe('🥉');
            expect(cards[2].querySelector('.podium-name').textContent).toBe('Bruno Lima');
            expect(cards[2].querySelector('.row-badge').textContent).toBe('🛡️ Operacional');
        });
    });

    describe('3. Lista Geral (Do 4º colocado em diante em #list-container)', () => {
        it('deve renderizar alunos a partir da 4ª posição na lista geral com sufixo º e pts', () => {
            const alunos = [
                { nome: 'Primeiro', pontos: 2000 },
                { nome: 'Segundo', pontos: 1800 },
                { nome: 'Terceiro', pontos: 1500 },
                { nome: 'Quarto Aluno', pontos: 400 },
                { nome: 'Quinto Aluno', pontos: 200 }
            ];

            renderRanking(alunos, podiumContainer, listContainer);

            const rows = listContainer.querySelectorAll('.ranking-row');
            expect(rows.length).toBe(2);

            expect(rows[0].querySelector('.pos-number').textContent).toBe('4º');
            expect(rows[0].querySelector('.row-name').textContent).toBe('Quarto Aluno');
            expect(rows[0].querySelector('.row-badge').textContent).toBe('📚 Cadete');
            expect(rows[0].querySelector('.row-pts').textContent).toBe('400 pts');

            expect(rows[1].querySelector('.pos-number').textContent).toBe('5º');
            expect(rows[1].querySelector('.row-name').textContent).toBe('Quinto Aluno');
        });
    });

    describe('4. Casos de Borda e Dinamismo', () => {
        it('deve lidar com lista vazia (0 alunos) sem quebrar e mantendo containers vazios', () => {
            renderRanking([], podiumContainer, listContainer);
            expect(podiumContainer.children.length).toBe(0);
            expect(listContainer.children.length).toBe(0);
        });

        it('deve lidar com menos de 3 alunos (ex: 2 alunos) renderizando apenas os cards correspondentes', () => {
            const alunos = [
                { nome: 'Líder Único', pontos: 1100 },
                { nome: 'Segundo Lugar', pontos: 850 }
            ];

            renderRanking(alunos, podiumContainer, listContainer);
            expect(podiumContainer.querySelectorAll('.podium-card').length).toBe(2);
            expect(listContainer.children.length).toBe(0);
        });

        it('deve lidar com lista grande (> 10 alunos), distribuindo top 3 no pódio e restante na lista', () => {
            const alunos = Array.from({ length: 15 }, (_, i) => ({
                nome: `Aluno ${i + 1}`,
                pontos: 1500 - i * 50
            }));

            renderRanking(alunos, podiumContainer, listContainer);

            expect(podiumContainer.querySelectorAll('.podium-card').length).toBe(3);
            expect(listContainer.querySelectorAll('.ranking-row').length).toBe(12);
            expect(listContainer.querySelector('.pos-number').textContent).toBe('4º');
        });

        it('deve sanitizar caracteres especiais no nome do aluno para evitar XSS/injeção HTML', () => {
            const alunos = [
                { nome: '<script>alert("xss")</script>', pontos: 1500 }
            ];

            renderRanking(alunos, podiumContainer, listContainer);

            const nameEl = podiumContainer.querySelector('.podium-name');
            expect(nameEl.innerHTML).not.toContain('<script>');
            expect(nameEl.textContent).toBe('<script>alert("xss")</script>');
        });
    });
});
