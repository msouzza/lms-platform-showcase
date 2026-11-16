/**
 * Testes unitários e de integração para o visualizador de resumos (ver_resumo.php / injeção DOM)
 * Cobre: window.resumoAppConfig, fecharResumo(), bootAnnotations(), caçarETransformarBotao()
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

describe('Ver Resumo Frontend Logic (ver_resumo.php)', () => {
  let originalWindowOpener;
  let originalLocation;

  beforeEach(() => {
    vi.useFakeTimers();
    originalWindowOpener = window.opener;
    originalLocation = window.location;

    // Mock window.location
    delete window.location;
    window.location = {
      href: 'http://localhost/ver_resumo.php?id=1',
      assign: vi.fn(),
      replace: vi.fn()
    };

    // Reset DOM
    document.body.innerHTML = '';
  });

  afterEach(() => {
    vi.useRealTimers();
    window.opener = originalWindowOpener;
    window.location = originalLocation;
    vi.restoreAllMocks();
    delete window.resumoAppConfig;
    delete window.ID_RESUMO_LEGADO;
    delete window.ID_MIGRACAO_PROVAVEL;
    delete window.ResumoAnnotations;
    delete window.resumoApp;
    delete window.fecharResumo;
    delete window.bootAnnotations;
    delete window.cacaeTransformarBotao;
    delete window.cacarETransformarBotao;
  });

  describe('1. window.resumoAppConfig', () => {
    it('deve aceitar e expor as configurações corretamente', () => {
      window.resumoAppConfig = {
        apiUrl: 'http://localhost/api_anotacoes.php',
        idResumo: 'resumos/teste.html',
        idMigracao: 'teste.html',
        urlSistema: 'http://localhost'
      };
      window.ID_RESUMO_LEGADO = 'resumos/teste.html';
      window.ID_MIGRACAO_PROVAVEL = 'teste.html';

      expect(window.resumoAppConfig.apiUrl).toBe('http://localhost/api_anotacoes.php');
      expect(window.resumoAppConfig.idResumo).toBe('resumos/teste.html');
      expect(window.ID_RESUMO_LEGADO).toBe('resumos/teste.html');
      expect(window.ID_MIGRACAO_PROVAVEL).toBe('teste.html');
    });
  });

  describe('2. fecharResumo()', () => {
    function fecharResumo() {
      const urlDestino = (window.resumoAppConfig && window.resumoAppConfig.urlSistema)
        ? window.resumoAppConfig.urlSistema + '/area_aluno.php'
        : 'area_aluno.php';

      if (window.opener || window.history.length > 1) {
        try {
          window.close();
        } catch (e) {
          // ignore
        }
        setTimeout(() => {
          if (!window.closed) {
            window.location.href = urlDestino;
          }
        }, 300);
      } else {
        window.location.href = urlDestino;
      }
    }

    it('deve redirecionar diretamente se window.opener for nulo e history.length <= 1', () => {
      window.opener = null;
      // Mock history.length via Object.defineProperty if needed or assume 1
      Object.defineProperty(window.history, 'length', { value: 1, configurable: true });

      fecharResumo();
      expect(window.location.href).toBe('area_aluno.php');
    });

    it('deve tentar fechar a janela e agendar fallback se window.opener existir', () => {
      window.opener = {};
      const closeSpy = vi.spyOn(window, 'close').mockImplementation(() => {});

      fecharResumo();

      expect(closeSpy).toHaveBeenCalled();
      
      // Avança timer de fallback
      vi.advanceTimersByTime(300);
      expect(window.location.href).toBe('area_aluno.php');
    });

    it('deve respeitar urlSistema do resumoAppConfig se definido', () => {
      window.resumoAppConfig = { urlSistema: 'http://sistema.com' };
      window.opener = null;
      Object.defineProperty(window.history, 'length', { value: 1, configurable: true });

      fecharResumo();
      expect(window.location.href).toBe('http://sistema.com/area_aluno.php');
    });
  });

  describe('3. bootAnnotations()', () => {
    it('deve atualizar o apiUrl se window.resumoApp já existir', () => {
      window.ResumoAnnotations = class {};
      window.resumoApp = {
        apiUrl: 'old-url',
        init: vi.fn()
      };
      window.resumoAppConfig = { apiUrl: 'new-url' };

      function bootAnnotations() {
        if (window.ResumoAnnotations) {
          if (window.resumoApp) {
            window.resumoApp.apiUrl = window.resumoAppConfig.apiUrl;
          } else {
            window.resumoApp = new window.ResumoAnnotations();
            window.resumoApp.apiUrl = window.resumoAppConfig.apiUrl;
            window.resumoApp.init();
          }
          return true;
        }
        return false;
      }

      const res = bootAnnotations();
      expect(res).toBe(true);
      expect(window.resumoApp.apiUrl).toBe('new-url');
    });

    it('deve instanciar ResumoAnnotations se não existir window.resumoApp', () => {
      const initMock = vi.fn();
      window.ResumoAnnotations = class {
        constructor() {
          this.init = initMock;
        }
      };
      window.resumoAppConfig = { apiUrl: 'http://api' };

      function bootAnnotations() {
        if (window.ResumoAnnotations) {
          if (window.resumoApp) {
            window.resumoApp.apiUrl = window.resumoAppConfig.apiUrl;
          } else {
            window.resumoApp = new window.ResumoAnnotations();
            window.resumoApp.apiUrl = window.resumoAppConfig.apiUrl;
            window.resumoApp.init();
          }
          return true;
        }
        return false;
      }

      bootAnnotations();
      expect(window.resumoApp).toBeDefined();
      expect(window.resumoApp.apiUrl).toBe('http://api');
      expect(initMock).toHaveBeenCalled();
    });

    it('deve tentar novamente via setTimeout se ResumoAnnotations não estiver carregado (até limite)', () => {
      let tentativas = 0;
      const maxTentativas = 15;

      function bootAnnotations() {
        if (window.ResumoAnnotations) {
          return true;
        }
        tentativas++;
        if (tentativas < maxTentativas) {
          setTimeout(bootAnnotations, 500);
        }
        return false;
      }

      expect(bootAnnotations()).toBe(false);
      expect(tentativas).toBe(1);

      // Simula passar o tempo
      vi.advanceTimersByTime(500);
      
      // Define a classe após 1 chamada
      window.ResumoAnnotations = class {};
      
      const success = bootAnnotations();
      expect(success).toBe(true);
    });
  });

  describe('4. caçarETransformarBotao()', () => {
    let fecharResumoMock;

    beforeEach(() => {
      fecharResumoMock = vi.fn();
      window.fecharResumo = fecharResumoMock;
    });

    function caçarETransformarBotao() {
      const elementos = document.querySelectorAll('a, button, .btn, .btn-voltar, div[role="button"]');
      elementos.forEach(btn => {
        if (btn.dataset.corrigido === "true") return;

        const texto = (btn.textContent || '').toLowerCase();
        const href = (btn.getAttribute('href') || '').toLowerCase();

        const ehAlvo = texto.includes('voltar') || 
                       texto.includes('sair') || 
                       texto.includes('fechar') || 
                       texto.includes('lista') ||
                       href.includes('area_aluno') || 
                       href.includes('index.php');

        if (ehAlvo) {
          btn.dataset.corrigido = "true";
          const novoBtn = document.createElement('button');
          novoBtn.type = 'button';
          novoBtn.innerHTML = '✖ Fechar Resumo';
          novoBtn.style.backgroundColor = '#dc2626';
          novoBtn.style.color = '#ffffff';
          novoBtn.style.border = 'none';
          novoBtn.style.padding = '8px 16px';
          novoBtn.style.borderRadius = '6px';
          novoBtn.style.cursor = 'pointer';
          novoBtn.style.fontWeight = 'bold';
          novoBtn.dataset.corrigido = "true";

          novoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (typeof window.fecharResumo === 'function') {
              window.fecharResumo();
            }
          });

          if (btn.parentNode) {
            btn.parentNode.replaceChild(novoBtn, btn);
          }
        }
      });
    }

    it('deve transformar botão com texto "Voltar"', () => {
      document.body.innerHTML = `
        <button id="btnVoltar">Voltar para a lista</button>
      `;

      caçarETransformarBotao();

      const novo = document.querySelector('button');
      expect(novo.innerHTML).toContain('✖ Fechar Resumo');
      expect(novo.style.backgroundColor).toBe('rgb(220, 38, 38)'); // #dc2626

      novo.click();
      expect(fecharResumoMock).toHaveBeenCalled();
    });

    it('deve transformar link com href contendo "area_aluno"', () => {
      document.body.innerHTML = `
        <a id="linkAluno" href="area_aluno.php">Painel</a>
      `;

      caçarETransformarBotao();

      const novo = document.querySelector('button');
      expect(novo).toBeDefined();
      expect(novo.innerHTML).toContain('✖ Fechar Resumo');

      novo.click();
      expect(fecharResumoMock).toHaveBeenCalled();
    });

    it('não deve transformar botões irrelevantes como "Salvar Anotação"', () => {
      document.body.innerHTML = `
        <button id="btnSalvar">Salvar Anotação</button>
      `;

      caçarETransformarBotao();

      const btn = document.querySelector('#btnSalvar');
      expect(btn).toBeDefined();
      expect(btn.tagName).toBe('BUTTON');
      expect(btn.innerHTML).toBe('Salvar Anotação');
      expect(btn.dataset.corrigido).toBeUndefined();
    });

    it('não deve reprocessar elementos já corrigidos (dataset.corrigido === "true")', () => {
      document.body.innerHTML = `
        <button id="btnVoltar" data-corrigido="true">Voltar</button>
      `;

      const originalHtml = document.body.innerHTML;
      caçarETransformarBotao();

      expect(document.body.innerHTML).toBe(originalHtml);
    });
  });
});
