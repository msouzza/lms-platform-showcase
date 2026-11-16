// ARQUIVO: components/resumos/resumo-annotations.js
// VERSÃO: VIBRANIUM 5.4 (Migração Fiel ao Formato Original do LocalStorage)

(function () {
  if (typeof window.ResumoAnnotations !== "undefined") return;

  class ResumoAnnotations {
    constructor() {
    this.resumoId = this.getResumoId();
    this.highlights = [];
    this.annotations = [];

    if (window.resumoAppConfig && window.resumoAppConfig.apiUrl) {
        this.apiUrl = window.resumoAppConfig.apiUrl;
    } else {
        const loc = window.location;
        const pathParts = loc.pathname.split("/");
        const base = loc.hostname === "localhost" ? "/" + pathParts[1] : "";
        this.apiUrl = loc.origin + base + "/api/api_anotacoes.php";
    }

    console.log("✅ API Ativa:", this.apiUrl);
}

    createToolbar() {
      if (!document.body) {
        console.warn("⚠️ Tentativa de criar Toolbar antes do Body. Abortando...");
        return;
      }

      if (document.querySelector(".study-toolbar")) return;

      this.toolbar = document.createElement("div");
      this.toolbar.className = "study-toolbar";
      this.toolbar.innerHTML = `
                  <div style="background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); padding: 12px; display: flex; flex-direction: column; gap: 6px; border: 2px solid #e5e7eb; min-width: 140px;">
                      <button id="highlight-btn" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; border: 2px solid #f59e0b; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; width: 100%; text-align: left;">🖍️ Marcar</button>
                      <button id="annotate-btn" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: 2px solid #2563eb; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; width: 100%; text-align: left;">📝 Anotar</button>
                      <button id="clear-highlights-btn" style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: 2px solid #dc2626; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; width: 100%; text-align: left;">🗑️ Limpar</button>
                      <button id="show-annotations-btn" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: 2px solid #059669; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; width: 100%; text-align: left;">👁️ Ver (<span id="total-count">0</span>)</button>
                  </div>
              `;
      this.toolbar.style.cssText = `position: fixed; top: 80px; right: 20px; z-index: 9999; display: block;`;

      try {
        document.body.appendChild(this.toolbar);
      } catch (e) {
        console.error("Erro ao anexar toolbar:", e);
        return;
      }

      document.getElementById("highlight-btn").onclick = () => this.performHighlight();
      document.getElementById("annotate-btn").onclick = () => this.addAnnotation();
      document.getElementById("clear-highlights-btn").onclick = () => this.clearHighlights();
      document.getElementById("show-annotations-btn").onclick = () => this.showAnnotations();
    }

    init() {
      if (!document.body || document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => this.init());
        return;
      }
      this.createToolbar();
      this.setupTextSelection();
      this.injectStyles();
      this.migrarDoLocalStorage();
      setTimeout(() => {
        this.loadData();
      }, 500);
    }

    getResumoId() {
      if (window.ID_RESUMO_LEGADO) return window.ID_RESUMO_LEGADO;
      const params = new URLSearchParams(window.location.search);
      const ref = params.get("q") || params.get("aula");
      return ref
        ? "resumo_" + ref
        : btoa(window.location.pathname).replace(/[^a-zA-Z0-9]/g, "");
    }

    normalizeString(str) {
      if (!str) return "";
      return str
        .replace(/[\*\#\_]/g, "")
        .replace(/\r?\n|\r/g, " ")
        .replace(/\s+/g, " ")
        .trim();
    }

    trimSurroundingIcons(str) {
      if (!str) return "";
      return str
        .replace(/^[\p{Extended_Pictographic}\s—–•·|:\-]+/gu, "")
        .replace(/[\p{Extended_Pictographic}\s—–•·|:\-]+$/gu, "")
        .trim();
    }

    restoreHighlights() {
    const doRestore = () => {
        this.highlights.forEach((h) => {
            let sucesso = false;
            if (h.selector && h.selector !== "BODY") {
                const elementos = document.querySelectorAll(h.selector);
                for (let el of elementos) {
                    if (this.findAcrossNodes(el, h.text, h)) {
                        sucesso = true;
                        break;
                    }
                }
            }
            if (!sucesso) {
                this.findAcrossNodes(document.body, h.text, h);
            }
        });
        this.updateCounts();
    };

    if (window.MathJax && window.MathJax.startup) {
        window.MathJax.startup.promise.then(doRestore);
    } else {
        setTimeout(doRestore, 1500);
    }
}

    mapCleanToDirtyIndex(originalStr, cleanIndex) {
      let cleanCounter = 0;
      for (let i = 0; i < originalStr.length; i++) {
        if (cleanCounter === cleanIndex) return i;
        if (!/[\*\#\_]/.test(originalStr[i])) cleanCounter++;
      }
      return originalStr.length;
    }

    findInSingleNode(root, txt, data) {
      const searchClean = this.normalizeString(txt).toLowerCase();
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
      let node;
      while ((node = walker.nextNode())) {
        const nodeValClean = this.normalizeString(node.textContent).toLowerCase();
        if (nodeValClean.includes(searchClean)) {
          try {
            const idx = nodeValClean.indexOf(searchClean);
            const realStart = this.mapCleanToDirtyIndex(node.textContent, idx);
            const realEnd = this.mapCleanToDirtyIndex(node.textContent, idx + searchClean.length);
            const range = document.createRange();
            range.setStart(node, realStart);
            range.setEnd(node, Math.min(realEnd, node.textContent.length));
            this.applyVisualHighlight(range, data);
            return true;
          } catch (e) {}
        }
      }
      return false;
    }

    _cleanForMatch(str) {
    if (!str) return "";
    return str
        .replace(/\r?\n|\r/g, " ")
        .replace(/[\*\#\_]/g, "")
        .replace(/[^\p{L}\p{N}\s]/gu, "")
        .replace(/\s+/g, " ")
        .trim()
        .toLowerCase();
}

findAcrossNodes(root, txt, data) {
    let cleanTarget = this.trimSurroundingIcons(txt);
    if (!cleanTarget || cleanTarget.length < 3) cleanTarget = txt;

    const needleClean = this._cleanForMatch(cleanTarget);
    if (needleClean.length < 3) return false;

    const IGNORE_TAGS = ['SCRIPT','STYLE','BUTTON',
                         'MO','MI','MN','MROW','MFRAC',
                         'MSUP','MSUB','SEMANTICS','ANNOTATION'];

    const isMathDuplicate = (node) => {
        let el = node.parentNode;
        while (el && el !== root) {
            const tag = el.tagName?.toUpperCase() || '';
            if (tag === 'MATH' || tag === 'SCRIPT') return true;
            el = el.parentNode;
        }
        return false;
    };

    const textNodes = [];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    let fullTextClean = "";
    let node;

    while (node = walker.nextNode()) {
        const tag = node.parentNode.tagName.toUpperCase();
        if (IGNORE_TAGS.includes(tag)) continue;
        if (tag === 'MARK' && node.parentNode.classList.contains('resumo-highlight')) continue;
        if (isMathDuplicate(node)) continue;

        const valRaw = node.textContent;
        const valClean = this._cleanForMatch(valRaw);

        if (valClean.length > 0) {
            textNodes.push({
                node,
                raw: valRaw,
                startClean: fullTextClean.length,
                lenClean: valClean.length
            });
            fullTextClean += valClean + " ";
        }
    }

    const globalIndex = fullTextClean.indexOf(needleClean);
    if (globalIndex === -1) return false;

    const globalEnd = globalIndex + needleClean.length;
    let startNode = null, endNode = null;

    for (let map of textNodes) {
        if (!startNode && globalIndex >= map.startClean && globalIndex < map.startClean + map.lenClean)
            startNode = map;
        if (globalEnd > map.startClean && globalEnd <= map.startClean + map.lenClean)
            endNode = map;
    }

    if (startNode && !endNode) endNode = textNodes[textNodes.length - 1];

    if (startNode && endNode) {
        try {
            const getRealOffset = (rawText, targetCleanOffset) => {
                let cleanCount = 0;
                let inWhitespace = false;
                let hasStarted = false;
                for (let i = 0; i < rawText.length; i++) {
                    const isSpace = /\s/.test(rawText[i]);
                    const isLetterOrNum = /[\p{L}\p{N}]/u.test(rawText[i]);
                    if (isLetterOrNum) {
                        inWhitespace = false;
                        hasStarted = true;
                        if (cleanCount === targetCleanOffset) return i;
                        cleanCount++;
                    } else if (isSpace && hasStarted) {
                        if (!inWhitespace) {
                            inWhitespace = true;
                            if (cleanCount === targetCleanOffset) return i;
                            cleanCount++;
                        }
                    }
                }
                return rawText.length;
            };

            const range = document.createRange();
            range.setStart(startNode.node, getRealOffset(startNode.raw, globalIndex - startNode.startClean));
            range.setEnd(endNode.node, getRealOffset(endNode.raw, globalEnd - endNode.startClean));
            this.applyVisualHighlight(range, data);
            return true;
        } catch (e) {
            console.warn("findAcrossNodes erro:", e);
        }
    }
    return false;
}

    applyVisualHighlight(range, data) {
    try {
        if (
            range.startContainer.parentNode.tagName === "SCRIPT" ||
            range.startContainer.parentNode.tagName === "STYLE" ||
            range.startContainer.parentNode.classList.contains("resumo-highlight")
        ) return;

        const mark = document.createElement("mark");
        mark.className = "resumo-highlight";
        mark.setAttribute("data-db-id", data.db_id || "");
        mark.onclick = (e) => {
            e.stopPropagation();
            if (confirm("Remover?")) this.removeHighlight(mark, data);
        };

        try {
            range.surroundContents(mark);
        } catch (e) {
            mark.appendChild(range.extractContents());
            range.insertNode(mark);
        }
    } catch (e) {
        console.warn("applyVisualHighlight erro:", e);
    }
}

    scrollToHighlight(dbId, selectorFallback) {
      let el = document.querySelector(`mark.resumo-highlight[data-db-id="${dbId}"]`);
      if (!el && selectorFallback && selectorFallback !== "LI" && selectorFallback !== "P") {
        el = document.querySelector(selectorFallback);
      }
      if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        const transitionOriginal = el.style.transition;
        el.style.transition = "all 0.5s ease";
        el.style.transform = "scale(1.1)";
        el.style.boxShadow = "0 0 15px rgba(234, 179, 8, 0.8)";
        setTimeout(() => {
          el.style.transform = "scale(1)";
          el.style.boxShadow = "none";
          setTimeout(() => { el.style.transition = transitionOriginal; }, 500);
        }, 800);
      } else {
        this.showNotification("⚠️ Visualização ajustada.");
      }
    }

    // =====================================================
    // MIGRAÇÃO VIBRANIUM 5.4 — Respeitando Formato Original
    // =====================================================
    async migrarDoLocalStorage() {
      // ─── PARTE 1: Migração legada (Mantida) ───
      const chavesPossiveis = [
        this.resumoId,
        window.ID_MIGRACAO_PROVAVEL,
        "/" + window.ID_MIGRACAO_PROVAVEL,
        window.location.pathname,
        "resumo_" + this.resumoId.split("/").pop(),
      ];
      let dadosAntigos = null, chaveEncontrada = null;
      for (let chave of chavesPossiveis) {
        if (chave && localStorage.getItem(chave)) {
          dadosAntigos = localStorage.getItem(chave);
          chaveEncontrada = chave;
          break;
        }
      }
      if (dadosAntigos) {
        try {
          const parsed = JSON.parse(dadosAntigos);
          if (parsed.highlights)
            for (let h of parsed.highlights) {
              if (!h.temp_id) h.temp_id = Date.now();
              await this.saveItem("highlight", h);
            }
          if (parsed.annotations)
            for (let a of parsed.annotations)
              await this.saveItem("annotation", a);
          localStorage.setItem(chaveEncontrada + "_MIGRADO", dadosAntigos);
          localStorage.removeItem(chaveEncontrada);
          this.loadData();
        } catch (e) {}
      }

      // ─── PARTE 2: SOLUÇÃO DEFINITIVA (Respeita components/...) ───
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        if (!key || !key.startsWith("highlights-") || key.endsWith("_MIGRADO")) continue;

        try {
          // 1. Decodifica o Base64 (ex: "/components/resumos/Direito_DH/resumo_01.html")
          const b64 = key.replace("highlights-", "");
          let decodedPath = atob(b64); 

          // 2. Remove apenas a barra inicial para ficar idêntico ao que o sistema original pede
          if (decodedPath.startsWith("/")) {
              decodedPath = decodedPath.substring(1); 
          }
          
          // AQUI ESTÁ A CORREÇÃO: Usamos o caminho exato sem o "resumo_"
          const correctDbRef = decodedPath; // Fica: components/resumos/Direito_DH/resumo_01.html

          const highlightsAntigos = localStorage.getItem(key);
          const arr = JSON.parse(highlightsAntigos);
          
          if (!Array.isArray(arr) || arr.length === 0) {
              localStorage.removeItem(key);
              continue;
          }

          let existentes = [];
          try {
            const resp = await fetch(`${this.apiUrl}?acao=carregar&ref=${correctDbRef}&_=${Date.now()}`);
            const dbData = await resp.json();
            existentes = (dbData.highlights || []).map(h => this._cleanForMatch(h.text));
          } catch (e) {}

          let migrados = 0;
          for (const h of arr) {
            const textoLimpo = this._cleanForMatch(h.text);
            if (existentes.some(e => e === textoLimpo || e.includes(textoLimpo) || textoLimpo.includes(e))) {
              continue; 
            }

            // 4. Salva usando a referência idêntica à do seu sistema original
            await fetch(this.apiUrl, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                acao: "salvar",
                ref: correctDbRef, 
                tipo: "highlight",
                dados: {
                  temp_id: h.id || Date.now(),
                  selector: h.selector || "BODY",
                  text: h.text || "",
                  timestamp: h.timestamp || new Date().toISOString()
                }
              }),
            });
            migrados++;
          }

          localStorage.setItem(key + "_MIGRADO", highlightsAntigos);
          localStorage.removeItem(key);
          console.log(`✅ Migrado: ${migrados} destaques salvos corretamente no arquivo: ${correctDbRef}`);

          if (correctDbRef === this.resumoId && migrados > 0) {
              this.loadData();
          }

        } catch (error) {
           console.error("❌ Erro ao decodificar/migrar a chave:", key, error);
        }
      }
    }

    async loadData() {
      try {
        const response = await fetch(
          `${this.apiUrl}?acao=carregar&ref=${this.resumoId}&_=${Date.now()}`
        );
        const data = await response.json();
        this.highlights = data.highlights || [];
        this.annotations = data.annotations || [];
        this.updateCounts();
        setTimeout(() => this.restoreHighlights(), 200);
      } catch (e) {}
    }

    async saveItem(tipo, item) {
      try {
        const response = await fetch(this.apiUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            acao: "salvar",
            ref: this.resumoId,
            tipo: tipo,
            dados: item,
          }),
        });
        const res = await response.json();
        return res.id;
      } catch (error) {
        return null;
      }
    }

    async deleteItem(dbId) {
      if (!dbId) return;
      await fetch(this.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ acao: "excluir", id: dbId }),
      });
    }

    performHighlight() {
      const selection = window.getSelection();
      let text = selection.toString().trim();
      if (!text) { this.showNotification("Selecione texto!"); return; }

      const cleanText = this.trimSurroundingIcons(text);
      if (cleanText && cleanText.length >= 2) {
        text = cleanText;
      }

      const range = selection.getRangeAt(0);

      try {
        if (range.startContainer && range.startContainer.nodeType === Node.TEXT_NODE) {
          const rawText = range.startContainer.textContent;
          const idx = rawText.indexOf(text);
          if (idx !== -1) {
            range.setStart(range.startContainer, idx);
            range.setEnd(range.startContainer, idx + text.length);
          }
        }
      } catch (e) {}

      const parent = range.commonAncestorContainer.nodeType === 1
        ? range.commonAncestorContainer
        : range.commonAncestorContainer.parentNode;
      const data = {
        temp_id: Date.now(),
        text: text,
        selector: this.getUniqueSelector(parent),
      };
      this.applyVisualHighlight(range, data);
      this.saveItem("highlight", data).then((id) => { if (id) data.db_id = id; });
      this.highlights.push(data);
      this.updateCounts();
      selection.removeAllRanges();
      this.hideSelectionTooltip();
    }

    async removeHighlight(el, data) {
      const parent = el.parentNode;
      while (el.firstChild) parent.insertBefore(el.firstChild, el);
      parent.removeChild(el);
      parent.normalize();
      this.highlights = this.highlights.filter(
        (h) => h.temp_id !== data.temp_id && h.db_id !== data.db_id
      );
      this.updateCounts();
      if (data.db_id) await this.deleteItem(data.db_id);
    }

    async removeHighlightByDbId(id) {
      const el = document.querySelector(`mark.resumo-highlight[data-db-id="${id}"]`);
      if (el) {
        const parent = el.parentNode;
        while (el.firstChild) parent.insertBefore(el.firstChild, el);
        parent.removeChild(el);
        parent.normalize();
      }
      this.highlights = this.highlights.filter((h) => h.db_id !== id);
      this.updateCounts();
      await this.deleteItem(id);
      this.showAnnotations();
    }

    async addAnnotation() {
      const text = prompt("Anotação:");
      if (text && text.trim()) {
        const data = { text: text.trim(), timestamp: new Date().toISOString() };
        const id = await this.saveItem("annotation", data);
        data.db_id = id;
        this.annotations.push(data);
        this.updateCounts();
        this.showNotification("Salvo!");
      }
    }

    async removeAnnotation(dbId) {
      this.annotations = this.annotations.filter((a) => a.db_id !== dbId);
      this.updateCounts();
      this.showAnnotations();
      await this.deleteItem(dbId);
    }

    async clearHighlights() {
      if (!confirm("Apagar tudo?")) return;
      document.querySelectorAll(".resumo-highlight").forEach((el) => {
        const p = el.parentNode;
        while (el.firstChild) p.insertBefore(el.firstChild, el);
        p.removeChild(el);
      });
      for (let h of this.highlights) { if (h.db_id) await this.deleteItem(h.db_id); }
      for (let a of this.annotations) { if (a.db_id) await this.deleteItem(a.db_id); }
      this.highlights = [];
      this.annotations = [];
      this.updateCounts();
    }

    showAnnotations() {
      const existing = document.getElementById("annotations-panel");
      if (existing) { existing.remove(); return; }
      if (this.annotations.length === 0 && this.highlights.length === 0) {
        this.showNotification("Nenhuma anotação encontrada.");
        return;
      }
      const panel = document.createElement("div");
      panel.id = "annotations-panel";
      panel.innerHTML = `<div class="fixed bottom-4 right-4 bg-white rounded-lg shadow-2xl border border-gray-200 p-4 max-w-sm w-full max-h-[80vh] overflow-y-auto z-50 flex flex-col gap-4"><div class="flex justify-between items-center border-b pb-2 sticky top-0 bg-white z-10"><h4 class="font-bold text-gray-800 flex items-center gap-2">📑 Seus Estudos <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">${this.annotations.length + this.highlights.length}</span></h4><button onclick="document.getElementById('annotations-panel').remove()" class="text-gray-400 hover:text-red-500 text-xl font-bold">&times;</button></div><div class="space-y-4">${this.annotations.map((a) => `<div class="bg-blue-50 p-3 rounded-lg border border-blue-100"><div class="text-sm font-medium">${a.text}</div><button onclick="window.resumoApp.removeAnnotation(${a.db_id})" class="text-xs text-red-500 mt-2 hover:text-red-700">Excluir</button></div>`).join("")}${this.highlights.map((h) => `<div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 cursor-pointer" onclick="window.resumoApp.scrollToHighlight(${h.db_id}, '${h.selector}')"><div class="text-sm">"${h.text.substring(0, 40)}..."</div><button onclick="event.stopPropagation();window.resumoApp.removeHighlightByDbId(${h.db_id})" class="text-xs text-red-500 mt-1 hover:text-red-700">Excluir</button></div>`).join("")}</div></div>`;
      document.body.appendChild(panel);
    }

    updateCounts() {
      const el = document.getElementById("total-count");
      if (el) el.textContent = this.highlights.length + this.annotations.length;
    }

    showNotification(msg) {
      const n = document.createElement("div");
      n.textContent = msg;
      n.style.cssText = `position:fixed;top:20px;right:20px;background:#10b981;color:white;padding:10px;border-radius:5px;z-index:10000;`;
      document.body.appendChild(n);
      setTimeout(() => n.remove(), 2000);
    }

    getUniqueSelector(el) {
    if (!el || el.id) return el && el.id ? "#" + el.id : "body";
    if (el.tagName === "MARK" && el.classList.contains("resumo-highlight")) {
        return el.parentNode ? this.getUniqueSelector(el.parentNode) : "BODY";
    }
    return el.tagName;
}

    setupTextSelection() {
      document.addEventListener("mouseup", () => {
        if (window.getSelection().toString().trim()) this.showSelectionTooltip();
      });
      document.addEventListener("mousedown", (e) => {
        if (!e.target.closest("#selection-tooltip")) this.hideSelectionTooltip();
      });
    }

    showSelectionTooltip() {
      this.hideSelectionTooltip();
      const rect = window.getSelection().getRangeAt(0).getBoundingClientRect();
      const t = document.createElement("div");
      t.id = "selection-tooltip";
      t.innerHTML = `<div class="bg-gray-800 text-white px-3 py-2 rounded shadow-lg text-sm flex gap-2"><button onclick="window.resumoApp.performHighlight()">🖍️</button><div class="w-px bg-gray-600"></div><button onclick="window.resumoApp.addAnnotation()">📝</button></div>`;
      t.style.cssText = `position:fixed;top:${rect.top - 50}px;left:${rect.left + rect.width / 2 - 30}px;z-index:1000;`;
      document.body.appendChild(t);
    }

    hideSelectionTooltip() {
      document.getElementById("selection-tooltip")?.remove();
    }

    injectStyles() {
      if (!document.getElementById("resumo-styles")) {
        const style = document.createElement("style");
        style.id = "resumo-styles";
        style.textContent = `.resumo-highlight { background-color: #fef08a !important; color: black !important; cursor: pointer; border-bottom: 2px solid #eab308; display: inline; position: relative; z-index: 10; } .resumo-highlight:hover { background-color: #fde047 !important; }`;
        document.head.appendChild(style);
      }
    }
  }

  window.ResumoAnnotations = ResumoAnnotations;

  function tryStart() {
    if (!window.resumoApp && window.ResumoAnnotations) {
      window.resumoApp = new window.ResumoAnnotations();
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => window.resumoApp.init());
      } else {
        window.resumoApp.init();
      }
    }
  }

  tryStart();
  window.addEventListener("load", tryStart);
})();