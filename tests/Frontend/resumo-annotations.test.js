// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import fs from 'fs';
import path from 'path';

describe('Frontend - ResumoAnnotations (Destaques e Grifos no HTML)', () => {
  let ResumoAnnotationsClass;

  beforeEach(() => {
    document.body.innerHTML = '';
    window.location.search = '?q=Direito_DH/resumo_01';
    window.location.pathname = '/components/resumos/Direito_DH/resumo_01.html';
    window.confirm = vi.fn(() => true);
    window.prompt = vi.fn(() => 'Minha anotação de teste');
    window.alert = vi.fn();
    localStorage.clear();

    global.fetch = vi.fn((url, options) => {
      if (options && options.method === 'POST') {
        const body = JSON.parse(options.body);
        return Promise.resolve({
          json: () => Promise.resolve({ success: true, id: body.dados?.temp_id || 12345 })
        });
      }
      return Promise.resolve({
        json: () => Promise.resolve({ highlights: [], annotations: [] }),
      });
    });

    const scriptPath = path.resolve(__dirname, '../../components/resumos/resumo-annotations.js');
    const scriptCode = fs.readFileSync(scriptPath, 'utf-8');

    delete window.ResumoAnnotations;
    delete window.resumoApp;
    new Function(scriptCode)();

    ResumoAnnotationsClass = window.ResumoAnnotations;
  });

  // ==========================================
  // BLOCO 1: Inicialização, URLs e Toolbar
  // ==========================================
  it('1.1. Deve criar a Toolbar flutuante com todos os botões de ação', () => {
    const app = new ResumoAnnotationsClass();
    app.createToolbar();

    const toolbar = document.querySelector('.study-toolbar');
    expect(toolbar).not.toBeNull();
    expect(document.getElementById('highlight-btn')).not.toBeNull();
    expect(document.getElementById('annotate-btn')).not.toBeNull();
    expect(document.getElementById('clear-highlights-btn')).not.toBeNull();
    expect(document.getElementById('show-annotations-btn')).not.toBeNull();
    expect(document.getElementById('total-count')).not.toBeNull();
  });

  it('1.2. Deve resolver corretamente o resumoId via ID_RESUMO_LEGADO, query param q ou aula', () => {
    window.ID_RESUMO_LEGADO = 'legado_123';
    let app = new ResumoAnnotationsClass();
    expect(app.getResumoId()).toBe('legado_123');

    delete window.ID_RESUMO_LEGADO;
    // JSDOM URLSearchParams needs window.location.href or window.location = new URL(...)
    const originalLocation = window.location;
    delete window.location;
    window.location = { search: '?q=aula_05', pathname: '/test.html', origin: 'http://localhost' };
    app = new ResumoAnnotationsClass();
    expect(app.getResumoId()).toBe('resumo_aula_05');
    window.location = originalLocation;
  });

  it('1.3. Deve resolver apiUrl via resumoAppConfig ou fallback dinâmico', () => {
    window.resumoAppConfig = { apiUrl: 'https://api.custom.com/endpoint.php' };
    let app = new ResumoAnnotationsClass();
    expect(app.apiUrl).toBe('https://api.custom.com/endpoint.php');

    delete window.resumoAppConfig;
    app = new ResumoAnnotationsClass();
    expect(app.apiUrl).toContain('/api/api_anotacoes.php');
  });

  it('1.4. Deve injetar as regras de CSS #resumo-styles no head', () => {
    const app = new ResumoAnnotationsClass();
    app.injectStyles();
    const styleTag = document.getElementById('resumo-styles');
    expect(styleTag).not.toBeNull();
    expect(styleTag.textContent).toContain('.resumo-highlight');
  });

  // ==========================================
  // BLOCO 2: Sanitização, Normalização e Emojis
  // ==========================================
  it('2.1. normalizeString deve remover markdown, quebras de linha e colapsar espaços', () => {
    const app = new ResumoAnnotationsClass();
    const dirty = '**Art. 1º** #Direito_\nConstitucional   do   Brasil';
    expect(app.normalizeString(dirty)).toBe('Art. 1º Direito Constitucional do Brasil');
  });

  it('2.2. trimSurroundingIcons deve remover emojis isolados nas pontas sem afetar o texto legal', () => {
    const app = new ResumoAnnotationsClass();
    expect(app.trimSurroundingIcons('📌 TÓPICO 04 — Pactos Internacionais')).toBe('TÓPICO 04 — Pactos Internacionais');
    expect(app.trimSurroundingIcons('🎯 📝 Art. 5º da CF/88 — •')).toBe('Art. 5º da CF/88');
  });

  it('2.3. _cleanForMatch deve sanitizar mantendo letras, números e espaços minúsculos', () => {
    const app = new ResumoAnnotationsClass();
    expect(app._cleanForMatch('  Texto com   Espaços\nMultiplos! 123  ')).toBe('texto com espaços multiplos 123');
  });

  // ==========================================
  // BLOCO 3: Grifos e Destaques (Highlights)
  // ==========================================
  it('3.1. performHighlight deve grifar seleção do usuário criando <mark class="resumo-highlight">', () => {
    const app = new ResumoAnnotationsClass();
    app.createToolbar();
    document.body.innerHTML += `<div id="c"><p id="p">A República Federativa do Brasil.</p></div>`;
    const textNode = document.getElementById('p').firstChild;
    const range = document.createRange();
    range.setStart(textNode, 2);
    range.setEnd(textNode, 21);

    window.getSelection = vi.fn(() => ({
      toString: () => 'República Federativa',
      getRangeAt: () => range,
      removeAllRanges: vi.fn(),
    }));

    app.performHighlight();
    const mark = document.querySelector('mark.resumo-highlight');
    expect(mark).not.toBeNull();
    expect(mark.textContent).toBe('República Federativa');
    expect(app.highlights.length).toBe(1);
  });

  it('3.2. performHighlight deve exibir notificação se a seleção estiver vazia', () => {
    const app = new ResumoAnnotationsClass();
    window.getSelection = vi.fn(() => ({ toString: () => '   ' }));
    const spy = vi.spyOn(app, 'showNotification');
    app.performHighlight();
    expect(spy).toHaveBeenCalledWith('Selecione texto!');
  });

  it('3.3. removeHighlightByDbId deve remover a marcação visualmente e atualizar banco', async () => {
    const app = new ResumoAnnotationsClass();
    document.body.innerHTML += `<p>Teste <mark class="resumo-highlight" data-db-id="777">grifo alvo</mark> final.</p>`;
    app.highlights = [{ db_id: 777, text: 'grifo alvo' }];
    const deleteSpy = vi.spyOn(app, 'deleteItem');

    await app.removeHighlightByDbId(777);
    expect(document.querySelector('mark[data-db-id="777"]')).toBeNull();
    expect(app.highlights.length).toBe(0);
    expect(deleteSpy).toHaveBeenCalledWith(777);
  });

  // ==========================================
  // BLOCO 4: Restauração e findAcrossNodes
  // ==========================================
  it('4.1. findAcrossNodes deve restaurar destaque simples ou atravessando múltiplos nós', () => {
    const app = new ResumoAnnotationsClass();
    document.body.innerHTML += `<div id="div1"><p class="p1">A segurança pública é dever do Estado.</p></div>`;
    const p = document.querySelector('.p1');
    const data = { text: 'segurança pública', db_id: 888 };
    const found = app.findAcrossNodes(p, 'segurança pública', data);
    expect(found).toBe(true);
    const mark = document.querySelector('mark.resumo-highlight');
    expect(mark).not.toBeNull();
    expect(mark.getAttribute('data-db-id')).toBe('888');
  });

  it('4.2. findAcrossNodes deve restaurar perfeitamente o tópico com pin (📌 TÓPICO 04)', () => {
    const app = new ResumoAnnotationsClass();
    document.body.innerHTML += `<h2 class="t">📌 TÓPICO 04 — Pactos Internacionais</h2>`;
    const h2 = document.querySelector('.t');
    const item = { text: '📌 TÓPICO 04 — Pactos Internacionais', db_id: 555 };
    const found = app.findAcrossNodes(h2, item.text, item);
    expect(found).toBe(true);
    expect(h2.querySelector('mark').textContent).toBe('TÓPICO 04 — Pactos Internacionais');
  });

  // ==========================================
  // BLOCO 5: Anotações de Texto (Notes)
  // ==========================================
  it('5.1. addAnnotation deve criar anotação via prompt e enviar para API', async () => {
    const app = new ResumoAnnotationsClass();
    window.prompt = vi.fn(() => 'Nota importante sobre prazos');
    await app.addAnnotation();
    expect(app.annotations.length).toBe(1);
    expect(app.annotations[0].text).toBe('Nota importante sobre prazos');
  });

  it('5.2. removeAnnotation deve remover anotação do array e atualizar interface', async () => {
    const app = new ResumoAnnotationsClass();
    app.annotations = [{ db_id: 101, text: 'Nota teste' }];
    await app.removeAnnotation(101);
    expect(app.annotations.length).toBe(0);
  });

  // ==========================================
  // BLOCO 6: Painel de Estudos e Navegação
  // ==========================================
  it('6.1. showAnnotations deve criar e alternar o painel #annotations-panel', () => {
    const app = new ResumoAnnotationsClass();
    app.annotations = [{ db_id: 1, text: 'Anotação 1' }];
    app.showAnnotations();
    const panel = document.getElementById('annotations-panel');
    expect(panel).not.toBeNull();

    // Chamada seguinte deve fechar (remover) o painel
    app.showAnnotations();
    expect(document.getElementById('annotations-panel')).toBeNull();
  });

  it('6.2. scrollToHighlight deve localizar e animar o elemento destacado', () => {
    const app = new ResumoAnnotationsClass();
    document.body.innerHTML += `<mark class="resumo-highlight" data-db-id="444">Destacado</mark>`;
    const mark = document.querySelector('mark');
    mark.scrollIntoView = vi.fn();

    app.scrollToHighlight(444);
    expect(mark.scrollIntoView).toHaveBeenCalled();
    expect(mark.style.transform).toBe('scale(1.1)');
  });

  // ==========================================
  // BLOCO 7: Tooltip Flutuante de Seleção
  // ==========================================
  it('7.1. showSelectionTooltip deve renderizar o tooltip flutuante ao selecionar texto', () => {
    const app = new ResumoAnnotationsClass();
    const range = { getBoundingClientRect: () => ({ top: 100, left: 100, width: 200, height: 20 }) };
    window.getSelection = vi.fn(() => ({
      toString: () => 'Texto selecionado',
      getRangeAt: () => range,
    }));

    app.showSelectionTooltip();
    const tooltip = document.getElementById('selection-tooltip');
    expect(tooltip).not.toBeNull();
    app.hideSelectionTooltip();
    expect(document.getElementById('selection-tooltip')).toBeNull();
  });

  // ==========================================
  // BLOCO 8: Limpeza Total (clearHighlights)
  // ==========================================
  it('8.1. clearHighlights deve remover todas as marcações e zerar contadores', async () => {
    const app = new ResumoAnnotationsClass();
    document.body.innerHTML += `<p>Texto <mark class="resumo-highlight">grifo</mark></p>`;
    app.highlights = [{ db_id: 1, text: 'grifo' }];
    app.annotations = [{ db_id: 2, text: 'nota' }];

    await app.clearHighlights();
    expect(document.querySelectorAll('.resumo-highlight').length).toBe(0);
    expect(app.highlights.length).toBe(0);
    expect(app.annotations.length).toBe(0);
  });

  // ==========================================
  // BLOCO 9: Migração Automática do LocalStorage
  // ==========================================
  it('9.1. migrarDoLocalStorage deve processar chaves highlights- base64 e migrar para API', async () => {
    const app = new ResumoAnnotationsClass();
    const sampleData = JSON.stringify([{ id: 1, text: 'Grifo legado LS' }]);
    const b64Key = 'highlights-' + btoa('/components/resumos/Direito_DH/resumo_01.html');
    localStorage.setItem(b64Key, sampleData);

    await app.migrarDoLocalStorage();

    expect(localStorage.getItem(b64Key + '_MIGRADO')).toBe(sampleData);
    expect(localStorage.getItem(b64Key)).toBeNull();
  });

  it('9.2. updateCounts deve atualizar o elemento span total-count corretamente', () => {
    const app = new ResumoAnnotationsClass();
    app.createToolbar();
    app.highlights = [{ text: 'h1' }, { text: 'h2' }];
    app.annotations = [{ text: 'a1' }];
    app.updateCounts();

    const span = document.getElementById('total-count');
    expect(span.textContent).toBe('3');
  });

  it('9.3. getUniqueSelector deve retornar ID ou tag do elemento ancestral', () => {
    const app = new ResumoAnnotationsClass();
    const div = document.createElement('div');
    div.id = 'meu-id';
    expect(app.getUniqueSelector(div)).toBe('#meu-id');

    const span = document.createElement('span');
    expect(app.getUniqueSelector(span)).toBe('SPAN');
  });
});
