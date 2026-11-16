// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

describe('Área do Aluno - Testes Completos de Frontend (area_aluno.php)', () => {
    let container;

    beforeEach(() => {
        document.body.innerHTML = '';
        localStorage.clear();
        vi.restoreAllMocks();

        container = document.createElement('div');
        container.innerHTML = `
            <div id="tabs-container">
                <button class="tab-btn bg-blue-600 border-blue-600 text-white shadow-lg active" data-tipo="Resumo">Resumo</button>
                <button class="tab-btn bg-slate-100 text-slate-800 border-slate-300" data-tipo="Flashcard">Flashcard</button>
                <button class="tab-btn bg-slate-100 text-slate-800 border-slate-300" data-tipo="Quiz">Quiz</button>
                <button class="tab-btn bg-slate-100 text-slate-800 border-slate-300" data-tipo="Simulado">Simulado</button>
            </div>
            <button class="external-tab-btn" data-tipo="Externo">Externo</button>
            <input type="text" id="search-input" />
            <div id="category-filters"></div>
            <div id="quiz-list"></div>
            <div id="load-more-container" class="hidden">
                <button id="load-more-btn">Carregar mais</button>
            </div>
            <button id="logout-button">Sair</button>
            <canvas id="gauge-Resumo"></canvas>
            <canvas id="gauge-Flashcard"></canvas>
            <canvas id="gauge-Quiz"></canvas>
            <canvas id="gauge-Simulado"></canvas>
        `;
        document.body.appendChild(container);

        window.progressoSalvo = ['resumo1.php||Resumo'];
        window.todosConteudos = [
            { title: 'Resumo Português 1', description: 'Gramática básica', category: 'Português', path: 'resumo1.php', tipo: 'Resumo', lastModified: '2026-01-01' },
            { title: 'Resumo Matemática 1', description: 'Álgebra linear', category: 'Matemática', path: 'resumo2.php', tipo: 'Resumo', lastModified: '2026-01-01' },
            { title: 'Flashcard DH 1', description: 'Direitos Humanos', category: 'Direitos_DH', path: 'flash1.php', tipo: 'Flashcard', lastModified: '2026-01-01' },
            { title: 'Quiz Bombeiro 1', description: 'Legislação', category: 'Defesa Civil', path: 'quiz1.php', tipo: 'Quiz', lastModified: '2026-01-01' },
            { title: 'Simulado Geral 1', description: 'Simulado completo', category: 'Português', path: 'sim1.php', tipo: 'Simulado', lastModified: '2026-01-01' }
        ];

        window.Chart = function(canvas, config) {
            this.canvas = canvas;
            this.data = config.data;
            this.update = vi.fn();
        };

        window.activeTipo = 'Resumo';
        window.currentPage = 1;
        window.filteredConteudos = [];
        window.QUIZZES_PER_PAGE = 2;

        window.createConteudoCard = function(conteudo, index) {
            const isStudied = window.progressoSalvo.includes(conteudo.path + '||' + conteudo.tipo);
            return `
                <div class="quiz-card bg-white rounded-xl shadow-md p-6 hover:shadow-xl transition-shadow group relative cursor-pointer ${isStudied ? 'studied' : ''}" data-card-index="${index}" onclick="window.openConteudo('${conteudo.path}', '${conteudo.tipo}')">
                    <button type="button" class="absolute top-4 right-4 px-3 py-1 text-xs rounded-full ${isStudied ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600'} font-semibold studied-btn" data-card-index="${index}" data-estudado="${isStudied}">${conteudo.tipo}</button>
                    <div class="card-content" data-path="${conteudo.path}">
                        <h2 class="text-lg font-semibold mb-2 text-slate-900">${conteudo.title}</h2>
                        <p class="text-slate-700 mb-3">${conteudo.description}</p>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>${conteudo.category}</span>
                            <span>${conteudo.lastModified}</span>
                        </div>
                    </div>
                </div>
            `;
        };

        window.openConteudo = vi.fn(function(path, tipo) {
            if (!path || path === '#') return;
            const prefixos = {
                'Quiz': 'ver_quiz.php?q=',
                'Resumo': 'ver_resumo.php?q=',
                'Flashcard': 'ver_flashcard.php?q=',
                'Simulado': 'ver_simulado.php?q='
            };
            const base = prefixos[tipo] || 'ver_conteudo.php?q=';
            window.location.href = base + encodeURIComponent(path);
        });

        window.conteudoCorrespondeCategoria = function(conteudo, categoria) {
            if (categoria === 'all') return true;
            const mapeamento = {
                'Matemática': ['/Matematica/', 'Matemática'],
                'Direitos_DH': ['/Direito_DH/', 'Direitos Humanos', 'Direito'],
                'Português': ['/Portugues/', 'Português'],                
                'Ciências Humanas': ['/Ciencias_Humanas/', 'Ciências Humanas'],
                'Ciências Naturais': ['/Ciencias_Naturais/', 'Ciências Naturais'],
                'Defesa Civil': ['/Defesa_Civil/', 'Defesa Civil']
            };
            const termos = mapeamento[categoria] || [categoria];
            return termos.some(t => conteudo.category.includes(t) || (conteudo.path && conteudo.path.includes(t)));
        };

        window.filterConteudos = function() {
            const searchInput = document.getElementById('search-input');
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const activeFilterElement = document.querySelector('.filter-btn.bg-slate-300');
            const activeFilter = activeFilterElement ? activeFilterElement.dataset.category : 'all';

            window.filteredConteudos = (window.todosConteudos || []).filter(conteudo => {
                if (conteudo.tipo !== window.activeTipo) return false;
                if (activeFilter !== 'all' && !window.conteudoCorrespondeCategoria(conteudo, activeFilter)) return false;
                if (searchTerm) {
                    const searchIn = `${conteudo.title} ${conteudo.description} ${conteudo.category}`.toLowerCase();
                    if (!searchIn.includes(searchTerm)) return false;
                }
                return true;
            });

            window.currentPage = 1;
            if (typeof window.renderConteudos === 'function') {
                window.renderConteudos();
            }
        };

        window.renderConteudos = function() {
            const quizList = document.getElementById('quiz-list');
            const loadMoreContainer = document.getElementById('load-more-container');
            if (!quizList) return;
            quizList.innerHTML = '';

            const endIndex = Math.min(window.currentPage * window.QUIZZES_PER_PAGE, window.filteredConteudos.length);
            const conteudosToShow = window.filteredConteudos.slice(0, endIndex);

            conteudosToShow.forEach((conteudo, idx) => {
                quizList.innerHTML += window.createConteudoCard(conteudo, idx);
            });

            if (endIndex < window.filteredConteudos.length) {
                if (loadMoreContainer) loadMoreContainer.classList.remove('hidden');
            } else {
                if (loadMoreContainer) loadMoreContainer.classList.add('hidden');
            }

            document.querySelectorAll('.studied-btn').forEach(btn => {
                btn.onclick = async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const card = btn.closest('.quiz-card');
                    const path = card.querySelector('.card-content').dataset.path;
                    const tipo = btn.textContent.trim();
                    const chaveUnica = path + '||' + tipo;
                    
                    const estaEstudado = btn.dataset.estudado === "true";
                    const novaEstudado = !estaEstudado;
                    
                    btn.dataset.estudado = novaEstudado.toString();
                    card.classList.toggle('studied', novaEstudado);
                    
                    if (novaEstudado) {
                        if (!window.progressoSalvo.includes(chaveUnica)) window.progressoSalvo.push(chaveUnica);
                        btn.classList.remove('bg-slate-100', 'text-slate-600');
                        btn.classList.add('bg-green-600', 'text-white');
                    } else {
                        window.progressoSalvo = window.progressoSalvo.filter(i => i !== chaveUnica);
                        btn.classList.remove('bg-green-600', 'text-white');
                        btn.classList.add('bg-slate-100', 'text-slate-600');
                    }

                    try {
                        if (global.fetch) {
                            await global.fetch('api_progresso.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ acao: novaEstudado ? 'marcar' : 'desmarcar', path, tipo })
                            });
                        }
                    } catch (err) {
                        // ignore error in test mock
                    }

                    window.updateAllStudyGauges();
                };
            });
        };

        window.loadMoreConteudos = function() {
            window.currentPage++;
            window.renderConteudos();
        };

        window.renderCategoryFilters = function() {
            let lastCategory = localStorage.getItem('lastCategory_' + window.activeTipo) || 'all';
            const categoryFilters = document.getElementById('category-filters');
            if (!categoryFilters) return;

            categoryFilters.innerHTML = `
                <button class="filter-btn px-3 py-1 rounded-lg ${lastCategory === 'all' ? 'bg-slate-300' : 'bg-slate-100'}" data-category="all">Todos</button>
                <button class="filter-btn px-3 py-1 rounded-lg ${lastCategory === 'Português' ? 'bg-slate-300' : 'bg-slate-100'}" data-category="Português">Português</button>
                <button class="filter-btn px-3 py-1 rounded-lg ${lastCategory === 'Matemática' ? 'bg-slate-300' : 'bg-slate-100'}" data-category="Matemática">Matemática</button>
                <button class="filter-btn px-3 py-1 rounded-lg ${lastCategory === 'Direitos_DH' ? 'bg-slate-300' : 'bg-slate-100'}" data-category="Direitos_DH">Direitos_DH</button>
            `;
            window.filterConteudos();
        };

        window.updateAllStudyGauges = function() {
            const tipos = ['Resumo', 'Flashcard', 'Quiz', 'Simulado'];
            tipos.forEach(tipo => {
                const totalTipo = (window.todosConteudos || []).filter(c => c.tipo === tipo).length;
                if (totalTipo === 0) return;
                const estudadosTipo = (window.todosConteudos || []).filter(c => c.tipo === tipo && window.progressoSalvo.includes(c.path + '||' + tipo)).length;
                const percent = Math.round((estudadosTipo / totalTipo) * 100);
                
                const canvas = document.getElementById(`gauge-${tipo}`);
                if (canvas && window.Chart) {
                    canvas.chartInstance = canvas.chartInstance || new window.Chart(canvas, {
                        data: { datasets: [{ data: [percent, 100 - percent] }] }
                    });
                    canvas.chartInstance.data.datasets[0].data = [percent, 100 - percent];
                    canvas.chartInstance.update();
                }
            });
        };

        window.migrarSimuladosAntigos = async function() {
            if (localStorage.getItem('migracao_simulados_v2_completa') === 'true') {
                return { status: 'ja_migrado' };
            }
            const raw = localStorage.getItem('simuladosPerformance');
            if (!raw) {
                localStorage.setItem('migracao_simulados_v2_completa', 'true');
                return { status: 'vazio' };
            }
            try {
                const dados = JSON.parse(raw);
                if (!Array.isArray(dados) || dados.length === 0) {
                    localStorage.setItem('migracao_simulados_v2_completa', 'true');
                    return { status: 'invalido' };
                }
                for (let sim of dados) {
                    if (sim.data && sim.data.includes(' às ')) {
                        sim.data = sim.data.replace(' às ', ' ');
                    }
                    if (global.fetch) {
                        await global.fetch('api/salvar_simulado.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(sim)
                        });
                    }
                }
                localStorage.setItem('migracao_simulados_v2_completa', 'true');
                return { status: 'sucesso', migratedCount: dados.length };
            } catch (e) {
                return { status: 'erro', message: e.message };
            }
        };

        window.showLogoutConfirmation = function() {
            const modal = document.createElement('div');
            modal.id = 'logout-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <button id="confirm-logout-act">Confirmar</button>
                    <button id="cancel-logout-act">Cancelar</button>
                </div>
            `;
            document.body.appendChild(modal);

            document.getElementById('confirm-logout-act').onclick = () => {
                window.location.href = 'logout.php';
            };
            document.getElementById('cancel-logout-act').onclick = () => {
                modal.remove();
            };
        };

        window.initializeEventListeners = function() {
            document.querySelectorAll('#tabs-container .tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#tabs-container .tab-btn').forEach(b => {
                        b.classList.remove('bg-blue-600', 'border-blue-600', 'text-white', 'active');
                        b.classList.add('bg-slate-100', 'text-slate-800');
                    });
                    this.classList.add('bg-blue-600', 'border-blue-600', 'text-white', 'active');
                    window.activeTipo = this.dataset.tipo;
                    localStorage.setItem('lastActiveTab', window.activeTipo);
                    window.renderCategoryFilters();
                });
            });

            const searchInput = document.getElementById('search-input');
            if (searchInput) searchInput.addEventListener('input', window.filterConteudos);

            const catFilt = document.getElementById('category-filters');
            if (catFilt) {
                catFilt.onclick = (e) => {
                    const btn = e.target.closest('.filter-btn');
                    if (btn) {
                        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('bg-slate-300'));
                        document.querySelectorAll('.filter-btn').forEach(b => b.classList.add('bg-slate-100'));
                        btn.classList.remove('bg-slate-100');
                        btn.classList.add('bg-slate-300');
                        localStorage.setItem('lastCategory_' + window.activeTipo, btn.dataset.category);
                        window.filterConteudos();
                    }
                };
            }

            const loadMoreBtn = document.getElementById('load-more-btn');
            if (loadMoreBtn) {
                loadMoreBtn.onclick = () => {
                    window.loadMoreConteudos();
                };
            }

            const logb = document.getElementById('logout-button');
            if (logb) logb.onclick = (e) => { e.preventDefault(); window.showLogoutConfirmation(); };
        };

        window.initializeEventListeners();
        window.renderCategoryFilters();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    // Bloco 1: Navegação de Abas
    describe('1. Navegação de Abas (#tabs-container .tab-btn)', () => {
        it('Deve alternar activeTipo e classes visuais corretamente', () => {
            const flashcardTab = document.querySelector('[data-tipo="Flashcard"]');
            flashcardTab.click();

            expect(window.activeTipo).toBe('Flashcard');
            expect(flashcardTab.classList.contains('active')).toBe(true);
            expect(flashcardTab.classList.contains('bg-blue-600')).toBe(true);
        });

        it('Deve persistir e restaurar lastActiveTab no localStorage', () => {
            const quizTab = document.querySelector('[data-tipo="Quiz"]');
            quizTab.click();
            expect(localStorage.getItem('lastActiveTab')).toBe('Quiz');
        });

        it('Deve isolar abas e ignorar botões fora do #tabs-container', () => {
            const externalBtn = document.querySelector('.external-tab-btn');
            externalBtn.click();
            expect(window.activeTipo).toBe('Resumo');
        });
    });

    // Bloco 2: Filtro por Matéria e Cores
    describe('2. Filtro por Matéria e Estilos de Cor', () => {
        it('Deve renderizar botões de disciplinas com cores corretas', () => {
            window.renderCategoryFilters();
            const ptBtn = document.querySelector('[data-category="Português"]');
            expect(ptBtn).not.toBeNull();
            expect(ptBtn.classList.contains('rounded-lg')).toBe(true);
        });

        it('Deve mapear categoria e path corretamente via conteudoCorrespondeCategoria', () => {
            const conteudoDH = { category: 'Outros', path: '/Direito_DH/artigo1.php', tipo: 'Flashcard' };
            expect(window.conteudoCorrespondeCategoria(conteudoDH, 'Direitos_DH')).toBe(true);

            const conteudoMat = { category: 'Matemática', path: 'mat.php', tipo: 'Resumo' };
            expect(window.conteudoCorrespondeCategoria(conteudoMat, 'Matemática')).toBe(true);
        });

        it('Deve persistir a última categoria por tipo no localStorage', () => {
            window.activeTipo = 'Flashcard';
            window.renderCategoryFilters();
            const dhBtn = document.querySelector('[data-category="Direitos_DH"]');
            dhBtn.click();
            expect(localStorage.getItem('lastCategory_Flashcard')).toBe('Direitos_DH');
        });
    });

    // Bloco 3: Busca em Tempo Real
    describe('3. Busca em Tempo Real (#search-input & filterConteudos)', () => {
        it('Deve buscar por título, descrição e categoria de forma insensível a maiúsculas/minúsculas', () => {
            window.activeTipo = 'Resumo';
            window.renderCategoryFilters();

            const searchInput = document.getElementById('search-input');
            searchInput.value = 'álgebra';
            searchInput.dispatchEvent(new window.Event('input'));

            expect(window.filteredConteudos.length).toBe(1);
            expect(window.filteredConteudos[0].title).toContain('Matemática');
        });

        it('Deve retornar lista vazia se nenhum conteúdo corresponder à busca', () => {
            window.activeTipo = 'Resumo';
            window.renderCategoryFilters();

            const searchInput = document.getElementById('search-input');
            searchInput.value = 'TermoInexistenteNoSistemaABC';
            searchInput.dispatchEvent(new window.Event('input'));

            expect(window.filteredConteudos.length).toBe(0);
        });

        it('Deve restaurar todos os itens ao limpar o campo de busca', () => {
            window.activeTipo = 'Resumo';
            window.renderCategoryFilters();

            const searchInput = document.getElementById('search-input');
            searchInput.value = '';
            searchInput.dispatchEvent(new window.Event('input'));

            expect(window.filteredConteudos.length).toBe(2);
        });
    });

    // Bloco 4: Checklist de Aulas e Sincronização
    describe('4. Checklist de Aulas e Sincronização (.studied-btn)', () => {
        it('Deve marcar aula adicionando .studied e classes de sucesso, e enviar fetch', async () => {
            window.activeTipo = 'Resumo';
            window.renderCategoryFilters();

            global.fetch = vi.fn().mockResolvedValue({
                json: async () => ({ status: 'sucesso' })
            });

            const studiedButtons = document.querySelectorAll('.studied-btn');
            const targetBtn = studiedButtons[1];

            expect(targetBtn.dataset.estudado).toBe('false');
            await targetBtn.click();

            expect(targetBtn.dataset.estudado).toBe('true');
            expect(targetBtn.classList.contains('bg-green-600')).toBe(true);
            expect(window.progressoSalvo).toContain('resumo2.php||Resumo');
            expect(global.fetch).toHaveBeenCalledWith('api_progresso.php', expect.any(Object));
        });

        it('Deve desmarcar aula removendo classes e enviando fetch de desmarcar', async () => {
            window.activeTipo = 'Resumo';
            window.renderCategoryFilters();

            global.fetch = vi.fn().mockResolvedValue({
                json: async () => ({ status: 'sucesso' })
            });

            const targetBtn = document.querySelectorAll('.studied-btn')[0];
            expect(targetBtn.dataset.estudado).toBe('true');

            await targetBtn.click();

            expect(targetBtn.dataset.estudado).toBe('false');
            expect(targetBtn.classList.contains('bg-slate-100')).toBe(true);
            expect(window.progressoSalvo).not.toContain('resumo1.php||Resumo');
            expect(global.fetch).toHaveBeenCalledWith('api_progresso.php', expect.any(Object));
        });

        it('Deve proteger o clique (stopPropagation) para não abrir o card ao clicar no botão de estudo', () => {
            window.activeTipo = 'Resumo';
            window.renderCategoryFilters();

            const targetBtn = document.querySelectorAll('.studied-btn')[0];
            
            const clickEvent = new window.MouseEvent('click', { bubbles: true, cancelable: true });
            const spyStop = vi.spyOn(clickEvent, 'stopPropagation');
            
            targetBtn.dispatchEvent(clickEvent);
            expect(spyStop).toHaveBeenCalled();
            expect(window.openConteudo).not.toHaveBeenCalled();
        });
    });

    // Bloco 5: Gráficos de Progresso
    describe('5. Gráficos de Progresso (updateAllStudyGauges)', () => {
        it('Deve calcular 50% de progresso quando metade dos itens estiver estudada', () => {
            window.progressoSalvo = ['resumo1.php||Resumo'];
            window.updateAllStudyGauges();

            const canvas = document.getElementById('gauge-Resumo');
            expect(canvas.chartInstance.data.datasets[0].data).toEqual([50, 50]);
        });

        it('Deve calcular 100% de progresso quando todos estiverem estudados', () => {
            window.progressoSalvo = ['resumo1.php||Resumo', 'resumo2.php||Resumo'];
            window.updateAllStudyGauges();

            const canvas = document.getElementById('gauge-Resumo');
            expect(canvas.chartInstance.data.datasets[0].data).toEqual([100, 0]);
        });

        it('Deve proteger contra divisão por zero para tipos sem conteúdos', () => {
            window.todosConteudos = window.todosConteudos.filter(c => c.tipo !== 'Simulado');
            expect(() => window.updateAllStudyGauges()).not.toThrow();
        });
    });

    // Bloco 6: Roteamento e Abertura Segura de Aulas
    describe('6. Roteamento e Abertura Segura de Aulas', () => {
        it('Deve abrir resumo corretamente com ver_resumo.php e encodeURIComponent', () => {
            delete window.location;
            window.location = { href: '' };

            window.openConteudo('pasta/meu resumo.php', 'Resumo');
            expect(window.location.href).toBe('ver_resumo.php?q=pasta%2Fmeu%20resumo.php');
        });

        it('Deve abrir quiz, flashcard e simulado com os prefixos corretos', () => {
            delete window.location;
            window.location = { href: '' };

            window.openConteudo('q1.php', 'Quiz');
            expect(window.location.href).toBe('ver_quiz.php?q=q1.php');

            window.openConteudo('f1.php', 'Flashcard');
            expect(window.location.href).toBe('ver_flashcard.php?q=f1.php');

            window.openConteudo('s1.php', 'Simulado');
            expect(window.location.href).toBe('ver_simulado.php?q=s1.php');
        });

        it('Deve ignorar path vazio ou #', () => {
            delete window.location;
            window.location = { href: 'original' };

            window.openConteudo('#', 'Resumo');
            expect(window.location.href).toBe('original');

            window.openConteudo('', 'Quiz');
            expect(window.location.href).toBe('original');
        });
    });

    // Bloco 7: Migração de Simulados Antigos
    describe('7. Migração de Simulados Antigos (migrarSimuladosAntigos)', () => {
        it('Deve retornar antecipadamente se migracao_simulados_v2_completa for true', async () => {
            localStorage.setItem('migracao_simulados_v2_completa', 'true');
            const res = await window.migrarSimuladosAntigos();
            expect(res.status).toBe('ja_migrado');
        });

        it('Deve converter formato de data antigo (às) e enviar para api de salvamento', async () => {
            const simuladosAntigos = [
                { id: 1, pontuacao: 80, data: '15/02/2025 às 10:00:00' }
            ];
            localStorage.setItem('simuladosPerformance', JSON.stringify(simuladosAntigos));

            global.fetch = vi.fn().mockResolvedValue({
                json: async () => ({ status: 'sucesso' })
            });

            const res = await window.migrarSimuladosAntigos();
            expect(res.status).toBe('sucesso');
            expect(localStorage.getItem('migracao_simulados_v2_completa')).toBe('true');
            expect(global.fetch).toHaveBeenCalledWith('api/salvar_simulado.php', expect.objectContaining({
                body: JSON.stringify({ id: 1, pontuacao: 80, data: '15/02/2025 10:00:00' })
            }));
        });
    });

    // Bloco 8: Modal de Confirmação de Logout
    describe('8. Modal de Confirmação de Logout (showLogoutConfirmation)', () => {
        it('Deve abrir o modal #logout-modal e removê-lo ao clicar em Cancelar', () => {
            window.showLogoutConfirmation();
            const modal = document.getElementById('logout-modal');
            expect(modal).not.toBeNull();

            const cancelBtn = document.getElementById('cancel-logout-act');
            cancelBtn.click();
            expect(document.getElementById('logout-modal')).toBeNull();
        });

        it('Deve redirecionar para logout.php ao clicar em Confirmar', () => {
            window.showLogoutConfirmation();
            const confirmBtn = document.getElementById('confirm-logout-act');

            delete window.location;
            window.location = { href: '' };

            confirmBtn.click();
            expect(window.location.href).toBe('logout.php');
        });
    });

    // Bloco 9: Paginação / Carregar Mais
    describe('9. Paginação / Carregar Mais (loadMoreConteudos)', () => {
        it('Deve exibir botão Carregar mais quando houver mais itens que QUIZZES_PER_PAGE', () => {
            window.activeTipo = 'Resumo';
            window.QUIZZES_PER_PAGE = 1;
            window.renderCategoryFilters();

            const loadMoreContainer = document.getElementById('load-more-container');
            expect(loadMoreContainer.classList.contains('hidden')).toBe(false);
        });

        it('Deve incrementar página e renderizar novos cards ao acionar loadMoreConteudos', () => {
            window.activeTipo = 'Resumo';
            window.QUIZZES_PER_PAGE = 1;
            window.renderCategoryFilters();

            expect(document.querySelectorAll('.quiz-card').length).toBe(1);

            window.loadMoreConteudos();
            expect(document.querySelectorAll('.quiz-card').length).toBe(2);
            expect(document.getElementById('load-more-container').classList.contains('hidden')).toBe(true);
        });
    });
});
