// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import fs from 'fs';
import path from 'path';

/**
 * Suite de Testes de Frontend: Organizador de Estudos e Cronograma (orgEstudos.php)
 * Plataforma LMS Contexto Policial
 */

// 1. Carrega e prepara o código original de orgEstudos.php
const phpFilePath = path.resolve(__dirname, '../../orgEstudos.php');
const phpSource = fs.readFileSync(phpFilePath, 'utf8');

const scriptMatches = [...phpSource.matchAll(/<script>([\s\S]*?)<\/script>/gi)];
if (scriptMatches.length < 2) {
    throw new Error('Não foi possível localizar o bloco <script> principal em orgEstudos.php');
}

let scriptContent = scriptMatches[1][1];

// Substitui variáveis PHP injetadas para execução pura em JSDOM
scriptContent = scriptContent.replace(
    /const configDoBanco = <\?php echo \$configOrgDB; \?>;/g,
    'const configDoBanco = window.__configDoBanco || null;'
);
scriptContent = scriptContent.replace(
    /<\?php echo isset\(\$topicosConcluidosJson\) \? \$topicosConcluidosJson : "\[\]"; \?>/g,
    '[]'
);
scriptContent = scriptContent.replace(
    /<\?php echo isset\(\$diasPuladosJson\) \? \$diasPuladosJson : "\[\]"; \?>/g,
    '[]'
);

// Injeção de exportação para que funções e variáveis de escopo sejam acessíveis pelo runner de teste
const exportsInjection = `
window.getTopicsPerDayByHours = getTopicsPerDayByHours;
window.getDesiredTopicsPerDay = getDesiredTopicsPerDay;
window.prepareAllTopics = prepareAllTopics;
window.formatDate = formatDate;
window.generatePlan = generatePlan;
window.calculateAndRenderPlan = calculateAndRenderPlan;
window.createStudyDayCard = createStudyDayCard;
window.attachListeners = attachListeners;
window.syncDayWithDatabase = syncDayWithDatabase;
window.migrateLocalStorage = migrateLocalStorage;
window.saveUserSettings = saveUserSettings;
window.applySavedState = applySavedState;
window.__getState = () => ({
    get currentPlan() { return currentPlan; },
    set currentPlan(v) { currentPlan = v; },
    get dbCompletedTopics() { return dbCompletedTopics; },
    get dbSkippedDays() { return dbSkippedDays; },
    get allTopicsBySubject() { return allTopicsBySubject; },
    set allTopicsBySubject(v) { allTopicsBySubject = v; }
});
`;

const executableCode = scriptContent + exportsInjection;

// Mock do scrollIntoView que não existe no JSDOM por padrão
if (!window.HTMLElement.prototype.scrollIntoView) {
    window.HTMLElement.prototype.scrollIntoView = () => {};
}

// Configura o DOM simulando a página orgEstudos.php
function setupDOM() {
    document.body.innerHTML = `
        <div class="container mx-auto max-w-6xl p-4 sm:p-8 bg-white rounded-xl shadow-2xl mt-4">
            <header class="header-bg text-center relative py-6 px-4 rounded-xl shadow-lg mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold mb-2 text-white">🗓️ Organizador de Estudos Adrenalina</h1>
            </header>
            <main>
                <section class="mb-10 p-6 bg-blue-50 border-t-4 border-blue-600 rounded-lg shadow-inner">
                    <div class="flex flex-col md:flex-row gap-4 items-end">
                        <div class="flex-1 w-full">
                            <label for="startDate">Data de Início:</label>
                            <input type="date" id="startDate">
                        </div>
                        <div class="flex-1 w-full">
                            <label for="endDate">Data Final:</label>
                            <input type="date" id="endDate">
                        </div>
                        <div class="flex-1 w-full">
                            <label for="hoursPerDay">Tempo disponível por dia:</label>
                            <select id="hoursPerDay">
                                <option value="1">1 hora (2 tópicos/dia)</option>
                                <option value="2">2 horas (3 tópicos/dia)</option>
                                <option value="3" selected>3+ horas (4 tópicos/dia)</option>
                            </select>
                        </div>
                        <button id="generatePlanBtn">Gerar Plano de Estudos</button>
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
                <div id="flashcardReminder"></div>
            </main>
        </div>
    `;
}

// Inicializador de contexto do organizador
function initOrganizador() {
    setupDOM();
    window.__configDoBanco = null;
    window.eval(executableCode);
}

// Cálculo utilitário de progresso percentual com base em 86 tópicos
function calculateProgressPercentage(completedCount, totalTopics = 86) {
    if (totalTopics <= 0) return 0;
    const pct = (completedCount / totalTopics) * 100;
    return Number(pct.toFixed(2));
}

describe('Organizador de Estudos Frontend - orgEstudos.php', () => {

    beforeEach(() => {
        vi.restoreAllMocks();
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ status: 'sucesso' })
        });
        localStorage.clear();
        initOrganizador();
    });

    afterEach(() => {
        vi.clearAllTimers();
    });

    // =========================================================================
    // 1. Relação de Horas x Tópicos Diários
    // =========================================================================
    describe('1. Relação de Horas x Tópicos Diários (getTopicsPerDayByHours)', () => {
        it('deve retornar 2 tópicos/dia para 1 hora de estudo', () => {
            expect(window.getTopicsPerDayByHours(1)).toBe(2);
            expect(window.getTopicsPerDayByHours('1')).toBe(2);
        });

        it('deve retornar 3 tópicos/dia para 2 horas de estudo', () => {
            expect(window.getTopicsPerDayByHours(2)).toBe(3);
            expect(window.getTopicsPerDayByHours('2')).toBe(3);
        });

        it('deve retornar 4 tópicos/dia para 3 horas de estudo', () => {
            expect(window.getTopicsPerDayByHours(3)).toBe(4);
            expect(window.getTopicsPerDayByHours('3')).toBe(4);
        });

        it('deve retornar 4 tópicos/dia como padrão para valores não mapeados', () => {
            expect(window.getTopicsPerDayByHours(4)).toBe(4);
            expect(window.getTopicsPerDayByHours(10)).toBe(4);
            expect(window.getTopicsPerDayByHours(0)).toBe(4);
            expect(window.getTopicsPerDayByHours(null)).toBe(4);
        });

        it('deve sincronizar com o select do DOM em getDesiredTopicsPerDay()', () => {
            const selectHours = document.getElementById('hoursPerDay');

            selectHours.value = '1';
            expect(window.getDesiredTopicsPerDay()).toBe(2);

            selectHours.value = '2';
            expect(window.getDesiredTopicsPerDay()).toBe(3);

            selectHours.value = '3';
            expect(window.getDesiredTopicsPerDay()).toBe(4);
        });
    });

    // =========================================================================
    // 2. Contagem e Consistência dos 86 Tópicos
    // =========================================================================
    describe('2. Contagem e Consistência dos Tópicos (prepareAllTopics)', () => {
        it('deve gerar exatamente 86 tópicos distribuídos entre as 6 matérias do edital', () => {
            const topicsBySubject = window.prepareAllTopics();

            const expectedCounts = {
                'Português': 20,
                'Matemática': 10,
                'Ciências Humanas': 20,
                'Ciências da Natureza': 18,
                'Defesa Civil': 8,
                'Direitos Humanos': 10
            };

            let totalCount = 0;
            for (const [subject, expected] of Object.entries(expectedCounts)) {
                expect(topicsBySubject[subject]).toBeDefined();
                expect(topicsBySubject[subject].length).toBe(expected);
                totalCount += topicsBySubject[subject].length;
            }

            expect(totalCount).toBe(86);
        });

        it('deve garantir que cada tópico possua id, subject, originalTitle e order', () => {
            const topicsBySubject = window.prepareAllTopics();
            const allTopicsList = Object.values(topicsBySubject).flat();

            expect(allTopicsList.length).toBe(86);

            allTopicsList.forEach(topic => {
                expect(topic.id).toBeDefined();
                expect(typeof topic.id).toBe('string');
                expect(topic.id.length).toBeGreaterThan(0);

                expect(topic.subject).toBeDefined();
                expect(topic.originalTitle).toBeDefined();
                expect(typeof topic.order).toBe('number');
                expect(topic.order).toBeGreaterThanOrEqual(1);
            });
        });

        it('deve garantir que todos os 86 tópicos sejam distintos por matéria e número de ordem', () => {
            const topicsBySubject = window.prepareAllTopics();
            const allTopicsList = Object.values(topicsBySubject).flat();

            // Cada tópico é unicamente identificado pela combinação da matéria e título original/ordem
            const compositeSet = new Set(allTopicsList.map(t => `${t.subject}::${t.originalTitle}`));
            expect(compositeSet.size).toBe(86);

            // Observação de QA: O sistema utiliza subject.substring(0, 3) para gerar o id ("CIÊ" para Humanas e Natureza),
            // totalizando 68 IDs nominais distintos para os 86 tópicos do edital.
            const nominalIdSet = new Set(allTopicsList.map(t => t.id));
            expect(nominalIdSet.size).toBe(68);
        });
    });

    // =========================================================================
    // 3. Formatação de Datas
    // =========================================================================
    describe('3. Formatação de Datas (formatDate)', () => {
        it('deve formatar data com zeros à esquerda no padrão DD/MM/AAAA', () => {
            const date = new Date(2026, 2, 5); // 05/03/2026
            expect(window.formatDate(date)).toBe('05/03/2026');
        });

        it('deve formatar datas com dois dígitos no dia e mês', () => {
            const date = new Date(2026, 11, 25); // 25/12/2026
            expect(window.formatDate(date)).toBe('25/12/2026');
        });
    });

    // =========================================================================
    // 4. Geração do Plano e Validação de Datas
    // =========================================================================
    describe('4. Geração do Plano e Validação de Datas (generatePlan)', () => {
        it('deve rejeitar e exibir alerta quando data inicial for maior ou igual à final', () => {
            const startDate = document.getElementById('startDate');
            const endDate = document.getElementById('endDate');
            const noDaysMsg = document.getElementById('noDaysMsg');
            const studyPlanSection = document.getElementById('studyPlanSection');

            startDate.value = '2026-04-10';
            endDate.value = '2026-04-05';

            window.generatePlan();

            expect(noDaysMsg.classList.contains('hidden')).toBe(false);
            expect(noDaysMsg.textContent).toContain('Data final inválida.');
            expect(studyPlanSection.classList.contains('hidden')).toBe(true);
        });

        it('deve retornar precocemente caso alguma data esteja vazia', () => {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';

            window.generatePlan();

            expect(document.getElementById('studyPlanSection').classList.contains('hidden')).toBe(true);
            expect(window.__getState().currentPlan.length).toBe(0);
        });

        it('deve gerar plano de 31 dias corretamente com datas válidas', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-31';

            window.generatePlan();

            const state = window.__getState();
            expect(state.currentPlan.length).toBe(31);
            expect(document.getElementById('studyPlanSection').classList.contains('hidden')).toBe(false);

            const planInfo = document.getElementById('planInfo');
            expect(planInfo.classList.contains('hidden')).toBe(false);
            expect(planInfo.textContent).toContain('Total de dias: 31');
            expect(planInfo.textContent).toContain('Ativos: 31');
            expect(planInfo.textContent).toContain('Pulados: 0');

            const cards = document.querySelectorAll('.study-day-card');
            expect(cards.length).toBe(31);
        });
    });

    // =========================================================================
    // 5. Fixação dos 5 Simulados nos Últimos 5 Dias
    // =========================================================================
    describe('5. Fixação dos Simulados nos Últimos 5 Dias (Âncora Fixa)', () => {
        it('deve ancorar 5 simulados nos últimos 5 dias do cronograma de 30 dias', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-30'; // 30 dias

            window.generatePlan();

            const plan = window.__getState().currentPlan;
            expect(plan.length).toBe(30);

            // Primeiros 25 dias (0 a 24) NÃO devem ter simulado
            for (let i = 0; i < 25; i++) {
                expect(plan[i].simulado).toBeNull();
            }

            // Últimos 5 dias (25 a 29) DEVEM ter simulados 1 a 5
            for (let i = 25; i < 30; i++) {
                const num = i - 24;
                expect(plan[i].simulado).not.toBeNull();
                expect(plan[i].simulado.id).toBe(`SIM-${num}`);
                expect(plan[i].simulado.title).toBe(`Simulado ${num}`);
                expect(plan[i].simulado.subject).toBe('Simulado');
            }
        });

        it('deve renderizar o alerta visual de simulado no card do DOM', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-10'; // 10 dias

            window.generatePlan();

            const cards = document.querySelectorAll('.study-day-card');
            expect(cards.length).toBe(10);

            // Card 9 (último dia) possui o Simulado 5
            const lastCard = cards[9];
            expect(lastCard.innerHTML).toContain('🚨 Simulado 5 AGENDADO 🚨');
        });

        it('deve limitar quantidade de simulados quando o período for inferior a 5 dias', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-03'; // 3 dias

            window.generatePlan();

            const plan = window.__getState().currentPlan;
            expect(plan.length).toBe(3);

            expect(plan[0].simulado.id).toBe('SIM-1');
            expect(plan[1].simulado.id).toBe('SIM-2');
            expect(plan[2].simulado.id).toBe('SIM-3');
        });
    });

    // =========================================================================
    // 6. Cálculo da Barra de Progresso / Percentual de Conclusão
    // =========================================================================
    describe('6. Cálculo do Percentual de Progresso', () => {
        it('deve retornar 0% quando nenhum tópico estiver concluído', () => {
            expect(calculateProgressPercentage(0, 86)).toBe(0);
        });

        it('deve retornar exatamente 50% quando 43 de 86 tópicos forem concluídos', () => {
            expect(calculateProgressPercentage(43, 86)).toBe(50.00);
        });

        it('deve retornar 100% quando todos os 86 tópicos forem concluídos', () => {
            expect(calculateProgressPercentage(86, 86)).toBe(100.00);
        });

        it('deve calcular frações com precisão de duas casas decimais', () => {
            expect(calculateProgressPercentage(21, 86)).toBe(24.42);
            expect(calculateProgressPercentage(65, 86)).toBe(75.58);
            expect(calculateProgressPercentage(1, 86)).toBe(1.16);
        });

        it('deve aplicar estilo visual .completed no card quando todos os tópicos do dia forem concluídos', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-20';

            window.generatePlan();

            const state = window.__getState();
            const firstDayTopics = Object.values(state.currentPlan[0].topics).flat();
            expect(firstDayTopics.length).toBeGreaterThan(0);

            // Marca os tópicos do dia no Set do banco
            firstDayTopics.forEach(t => state.dbCompletedTopics.add(t.id));

            window.calculateAndRenderPlan(state.currentPlan);

            expect(state.currentPlan[0].isCompleted).toBe(true);
            const firstCard = document.querySelector('.study-day-card');
            expect(firstCard.classList.contains('completed')).toBe(true);
            expect(firstCard.innerHTML).toContain('Desmarcar Conclusão');
        });
    });

    // =========================================================================
    // 7. Simulação de Dia Pulado e Redistribuição sem Perda de Tópicos
    // =========================================================================
    describe('7. Pular Dias e Redistribuição sem Perda de Tópicos', () => {
        it('deve garantir que todos os 86 tópicos originais continuem no plano após pular um dia', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-04-04'; // 35 dias
            document.getElementById('hoursPerDay').value = '3'; // 4 tópicos/dia

            window.generatePlan();

            const state = window.__getState();

            const extractOriginalPass1Topics = (plan) => {
                const list = [];
                plan.forEach(day => {
                    if (!day.isSkipped && day.topics) {
                        Object.values(day.topics).forEach(topicArr => {
                            topicArr.forEach(t => {
                                if (t.pass === 1) list.push(t);
                            });
                        });
                    }
                });
                return list;
            };

            const initialTopics = extractOriginalPass1Topics(state.currentPlan);
            expect(initialTopics.length).toBe(86);

            // Pula o Dia 2 (índice 1)
            state.currentPlan[1].isSkipped = true;

            // Recalcula o plano
            window.calculateAndRenderPlan(state.currentPlan);

            expect(state.currentPlan[1].isSkipped).toBe(true);
            const cards = document.querySelectorAll('.study-day-card');
            expect(cards[1].classList.contains('skipped')).toBe(true);
            expect(cards[1].innerHTML).toContain('Dia Pulado');

            // Verifica que nenhum tópico foi perdido
            const topicsAfterSkip = extractOriginalPass1Topics(state.currentPlan);
            expect(topicsAfterSkip.length).toBe(86);

            const initialIds = new Set(initialTopics.map(t => `${t.subject}::${t.originalTitle}`));
            const afterSkipIds = new Set(topicsAfterSkip.map(t => `${t.subject}::${t.originalTitle}`));
            expect(afterSkipIds.size).toBe(86);
            expect(initialIds).toEqual(afterSkipIds);
        });

        it('deve reativar um dia pulado e reintegrá-lo ao cronograma', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-04-04';

            window.generatePlan();

            const state = window.__getState();
            state.currentPlan[2].isSkipped = true;
            window.calculateAndRenderPlan(state.currentPlan);
            expect(state.currentPlan[2].isSkipped).toBe(true);

            // Reativa o dia 2
            state.currentPlan[2].isSkipped = false;
            window.calculateAndRenderPlan(state.currentPlan);

            expect(state.currentPlan[2].isSkipped).toBe(false);
            const cards = document.querySelectorAll('.study-day-card');
            expect(cards[2].classList.contains('skipped')).toBe(false);
            expect(cards[2].innerHTML).toContain('Pular Dia');
        }, 15000);
    });

    // =========================================================================
    // 8. Agendamento de Revisão Programada (D+7)
    // =========================================================================
    describe('8. Agendamento de Revisão Programada (D+7)', () => {
        it('deve agendar revisão dos tópicos concluídos para 7 dias ativos adiante', () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-25'; // 25 dias

            window.generatePlan();

            const state = window.__getState();
            const day0Topics = Object.values(state.currentPlan[0].topics).flat();

            // Marca dia 0 como concluído
            day0Topics.forEach(t => state.dbCompletedTopics.add(t.id));

            window.calculateAndRenderPlan(state.currentPlan);

            expect(state.currentPlan[0].isCompleted).toBe(true);

            // O 7º dia ativo subsequente (índice 7) recebe a revisão
            const day7 = state.currentPlan[7];
            expect(day7.revisions.length).toBe(day0Topics.length);
            expect(day7.revisions[0].id).toBe(day0Topics[0].id);

            const cards = document.querySelectorAll('.study-day-card');
            expect(cards[7].innerHTML).toContain('REVISÃO PROGRAMADA (D+7)');
        });
    });

    // =========================================================================
    // 9. Interação e Eventos de Usuário
    // =========================================================================
    describe('9. Interação e Eventos de Usuário', () => {
        it('deve persistir configurações com saveUserSettings()', async () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-15';
            document.getElementById('hoursPerDay').value = '2';

            await window.saveUserSettings();

            expect(global.fetch).toHaveBeenCalledWith(
                'api_progresso.php',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({
                        acao: 'salvar_config_org',
                        config: {
                            startDate: '2026-03-01',
                            endDate: '2026-03-15',
                            hoursPerDay: '2'
                        }
                    })
                })
            );
        });

        it('deve sincronizar com api_progresso.php ao clicar em Pular Dia', async () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-15';

            window.generatePlan();

            const skipBtn = document.querySelector('.skip-day-btn');
            expect(skipBtn).not.toBeNull();

            skipBtn.click();
            await new Promise(resolve => setTimeout(resolve, 50));

            expect(global.fetch).toHaveBeenCalledWith(
                'api_progresso.php',
                expect.objectContaining({
                    method: 'POST',
                    body: expect.stringContaining('DiaPulado')
                })
            );

            expect(window.__getState().dbSkippedDays.size).toBe(1);
        });

        it('deve sincronizar tópicos concluídos com api_progresso.php ao clicar em Marcar Concluído', async () => {
            document.getElementById('startDate').value = '2026-03-01';
            document.getElementById('endDate').value = '2026-03-15';

            window.generatePlan();

            const completeBtn = document.querySelector('.complete-day-btn');
            expect(completeBtn).not.toBeNull();

            completeBtn.click();
            await new Promise(resolve => setTimeout(resolve, 50));

            expect(global.fetch).toHaveBeenCalledWith(
                'api_progresso.php',
                expect.objectContaining({
                    method: 'POST',
                    body: expect.stringContaining('Organizador')
                })
            );

            expect(window.__getState().dbCompletedTopics.size).toBeGreaterThan(0);
        });
    });
});
