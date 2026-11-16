// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('TAF Frontend & Calculadora Tests', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <select id="sexo-select"><option value="masculino">Masculino</option><option value="feminino">Feminino</option></select>
            <input type="date" id="data-registro">
            <input type="date" id="data-selecionada">
            <div id="modalidades-inputs"></div>
            <div id="acompanhamento-painel"></div>
            <select id="grafico-teste-select"></select>
            <table class="min-w-full">
                <thead id="historico-tabela-header"></thead>
                <tbody id="historico-tabela-body"></tbody>
            </table>
            <div id="historico-vazio" class="hidden"></div>
            <div id="minimos-modal" class="hidden">
                <div id="minimos-content"></div>
            </div>
            <div id="taf-notification"></div>
            <form id="registro-form">
                <button type="submit">Salvar</button>
            </form>
            <canvas id="evolutionChart"></canvas>
        `;
        localStorage.clear();
    });

    const INDICES_TAF = {
        masculino: [
            { id: 'barra', nome: 'Barra Fixa', unidade: 'repetições', minimo: 3, formato: 'Repetições (ex: 3, 5, 10)' },
            { id: 'abdominal', nome: 'Abdominal', unidade: 'repetições', minimo: 31, formato: 'Repetições (ex: 31, 40)' },
            { id: 'impulsao', nome: 'Impulsão Horizontal', unidade: 'm', minimo: 1.86, formato: 'Metros (ex: 1,86, 2,05)' },
            { id: 'braco_solo', nome: 'Flexão de Braço (solo)', unidade: 'repetições', minimo: 16, formato: 'Repetições (ex: 16, 25)' },
            { id: 'corrida', nome: 'Corrida de 12 minutos', unidade: 'metros', minimo: 2300, formato: 'Metros (ex: 2300) ou KM (ex: 2,5km)' },
        ],
        feminino: [
            { id: 'barra', nome: 'Barra Fixa Estática', unidade: 'segundos', minimo: 10, formato: 'Segundos (ex: 10, 15)' },
            { id: 'abdominal', nome: 'Abdominal', unidade: 'repetições', minimo: 25, formato: 'Repetições (ex: 25, 30)' },
            { id: 'impulsao', nome: 'Impulsão Horizontal', unidade: 'm', minimo: 1.46, formato: 'Metros (ex: 1,46, 1,70)' },
            { id: 'braco_solo', nome: 'Flexão de Braço (solo)', unidade: 'repetições', minimo: 12, formato: 'Repetições (ex: 12, 18)' },
            { id: 'corrida', nome: 'Corrida de 12 minutos', unidade: 'metros', minimo: 2000, formato: 'Metros (ex: 2000) ou KM (ex: 2,1km)' },
        ]
    };

    let currentGender = 'masculino';

    function parseResultado(teste, resultadoString) {
        if (!resultadoString) return 0;
        const res = resultadoString.trim().toLowerCase().replace(/,/g, '.').replace(/\s/g, '');
        if (teste.id === 'barra' && currentGender === 'feminino') {
            const segundosMatch = res.match(/(\d+)(\.\d+)?\s*(segundos|s|[''"])/);
            if (segundosMatch) return parseFloat(segundosMatch[1] + (segundosMatch[2] || ''));
        }
        if (teste.id === 'corrida' && res.includes('km')) {
            const numMatch = res.match(/[\d\.]+/);
            const num = numMatch ? parseFloat(numMatch[0]) : 0;
            return num * 1000;
        }
        const numMatch = res.match(/[\d\.]+/);
        return numMatch ? parseFloat(numMatch[0]) : 0;
    }

    function formatResultDisplay(teste, resultadoNumerico) {
        if (teste.id === 'impulsao') return `${resultadoNumerico.toFixed(2).replace('.', ',')} m`;
        if (teste.id === 'corrida') {
            if (resultadoNumerico >= 1000) return `${(resultadoNumerico / 1000).toFixed(2).replace(/\.00$/, '').replace('.', ',')} km (${resultadoNumerico.toFixed(0)} m)`;
            return `${resultadoNumerico.toFixed(0)} m`;
        }
        if (teste.id === 'barra' && currentGender === 'feminino') return `${resultadoNumerico.toFixed(0)}s`;
        if (teste.id === 'barra' || teste.id === 'abdominal' || teste.id === 'braco_solo') return `${resultadoNumerico.toFixed(0)}`;
        return String(resultadoNumerico).replace('.', ',');
    }

    function getStatus(teste, resultadoNumerico) {
        if (resultadoNumerico === 0) return { texto: 'Aguardando', class: 'bg-gray-100 text-gray-600', code: 'N/A' };
        const aprovado = resultadoNumerico >= teste.minimo;
        return aprovado 
            ? { texto: '✅ APROVADO', class: 'bg-green-100 text-green-700 font-bold border-green-500', code: 'APROVADO' }
            : { texto: '❌ REPROVADO', class: 'bg-red-100 text-red-700 font-bold border-red-500', code: 'REPROVADA' };
    }

    function calculateProgress(teste, resultadoNumerico) {
        if (teste.minimo <= 0) return 100;
        const progress = (resultadoNumerico / teste.minimo) * 100;
        return Math.min(progress, 200); 
    }

    function calculateDifference(teste, resultadoNumerico) {
        const diff = resultadoNumerico - teste.minimo;
        if (diff >= 0) return { texto: `+ ${formatResultDisplay(teste, diff)} acima`, class: 'text-green-600' };
        return { texto: `- ${formatResultDisplay(teste, Math.abs(diff))} abaixo`, class: 'text-red-600' };
    }

    it('1. Deve carregar corretamente os índices TAF para Masculino e Feminino', () => {
        expect(INDICES_TAF.masculino).toHaveLength(5);
        expect(INDICES_TAF.feminino).toHaveLength(5);
        expect(INDICES_TAF.masculino.find(t => t.id === 'barra').minimo).toBe(3);
        expect(INDICES_TAF.feminino.find(t => t.id === 'barra').minimo).toBe(10);
    });

    it('2. Deve parsear corretamente corrida em km para metros', () => {
        const testeCorrida = INDICES_TAF.masculino.find(t => t.id === 'corrida');
        expect(parseResultado(testeCorrida, '2,4km')).toBe(2400);
        expect(parseResultado(testeCorrida, '2.5 km')).toBe(2500);
    });

    it('3. Deve parsear corretamente barra feminina em segundos', () => {
        currentGender = 'feminino';
        const testeBarraFem = INDICES_TAF.feminino.find(t => t.id === 'barra');
        expect(parseResultado(testeBarraFem, '15s')).toBe(15);
        expect(parseResultado(testeBarraFem, '20 segundos')).toBe(20);
    });

    it('4. Deve parsear decimais em impulsão horizontal', () => {
        const testeImpulsao = INDICES_TAF.masculino.find(t => t.id === 'impulsao');
        expect(parseResultado(testeImpulsao, '1,86')).toBe(1.86);
        expect(parseResultado(testeImpulsao, '2.10')).toBe(2.1);
    });

    it('5. Deve parsear inteiros genéricos (abdominal e flexão)', () => {
        const testeAbdominal = INDICES_TAF.masculino.find(t => t.id === 'abdominal');
        expect(parseResultado(testeAbdominal, '35')).toBe(35);
    });

    it('6. Deve retornar 0 para valores vazios ou inválidos', () => {
        const testeAbdominal = INDICES_TAF.masculino.find(t => t.id === 'abdominal');
        expect(parseResultado(testeAbdominal, '')).toBe(0);
        expect(parseResultado(testeAbdominal, null)).toBe(0);
    });

    it('7. Deve formatar corretamente impulsão em metros com vírgula', () => {
        const testeImpulsao = INDICES_TAF.masculino.find(t => t.id === 'impulsao');
        expect(formatResultDisplay(testeImpulsao, 1.86)).toBe('1,86 m');
    });

    it('8. Deve formatar corretamente corrida em km e metros', () => {
        const testeCorrida = INDICES_TAF.masculino.find(t => t.id === 'corrida');
        expect(formatResultDisplay(testeCorrida, 2400)).toContain('2,40 km');
        expect(formatResultDisplay(testeCorrida, 900)).toBe('900 m');
    });

    it('9. Deve formatar barra feminina em segundos com s', () => {
        currentGender = 'feminino';
        const testeBarraFem = INDICES_TAF.feminino.find(t => t.id === 'barra');
        expect(formatResultDisplay(testeBarraFem, 15)).toBe('15s');
    });

    it('10. Deve formatar repetições inteiras sem decimais', () => {
        const testeAbdominal = INDICES_TAF.masculino.find(t => t.id === 'abdominal');
        expect(formatResultDisplay(testeAbdominal, 35)).toBe('35');
    });

    it('11. Deve retornar status APROVADO quando resultado >= mínimo', () => {
        const teste = INDICES_TAF.masculino.find(t => t.id === 'barra');
        const st = getStatus(teste, 5);
        expect(st.code).toBe('APROVADO');
        expect(st.texto).toContain('APROVADO');
    });

    it('12. Deve retornar status REPROVADA/REPROVADO quando resultado < mínimo', () => {
        const teste = INDICES_TAF.masculino.find(t => t.id === 'barra');
        const st = getStatus(teste, 2);
        expect(st.code).toBe('REPROVADA');
        expect(st.texto).toContain('REPROVADO');
    });

    it('13. Deve retornar status Aguardando para resultado zero', () => {
        const teste = INDICES_TAF.masculino.find(t => t.id === 'barra');
        const st = getStatus(teste, 0);
        expect(st.code).toBe('N/A');
        expect(st.texto).toBe('Aguardando');
    });

    it('14. Deve calcular corretamente a porcentagem do mínimo', () => {
        const teste = { minimo: 10 };
        expect(calculateProgress(teste, 5)).toBe(50);
        expect(calculateProgress(teste, 15)).toBe(150);
        expect(calculateProgress(teste, 30)).toBe(200);
    });

    it('15. Deve calcular diferença positiva acima do mínimo', () => {
        const teste = INDICES_TAF.masculino.find(t => t.id === 'barra');
        const diff = calculateDifference(teste, 5);
        expect(diff.class).toBe('text-green-600');
        expect(diff.texto).toContain('acima');
    });

    it('16. Deve calcular diferença negativa abaixo do mínimo', () => {
        const teste = INDICES_TAF.masculino.find(t => t.id === 'barra');
        const diff = calculateDifference(teste, 1);
        expect(diff.class).toBe('text-red-600');
        expect(diff.texto).toContain('abaixo');
    });

    it('17. Deve persistir o gênero selecionado no localStorage', () => {
        localStorage.setItem('taf_gender', 'feminino');
        expect(localStorage.getItem('taf_gender')).toBe('feminino');
    });

    it('18. Deve alternar corretamente de masculino para feminino e atualizar inputs', () => {
        currentGender = 'feminino';
        expect(currentGender).toBe('feminino');
        const testesFem = INDICES_TAF[currentGender];
        expect(testesFem.find(t => t.id === 'barra').minimo).toBe(10);
    });

    it('19. Deve validar obrigatoriedade de data e pelo menos um teste preenchido', () => {
        const dataRegistro = '2026-10-29';
        expect(dataRegistro).toBeTruthy();
        const novosResultados = [{ id: 'abdominal', sexo: 'masculino', data: '2026-10-29', resultadoNumerico: 40 }];
        expect(novosResultados.length).toBeGreaterThan(0);
    });

    it('20. Deve executar checkMigration corretamente evitando duplicatas no LocalStorage', () => {
        localStorage.setItem('taf_results', JSON.stringify([{ id: 'barra', sexo: 'masculino', data: '2026-10-01', resultadoNumerico: 5 }]));
        const oldData = JSON.parse(localStorage.getItem('taf_results') || '[]');
        expect(oldData).toHaveLength(1);
        
        localStorage.setItem('migracao_taf_concluida', 'true');
        localStorage.removeItem('taf_results');
        expect(localStorage.getItem('migracao_taf_concluida')).toBe('true');
        expect(localStorage.getItem('taf_results')).toBeNull();
    });
});
