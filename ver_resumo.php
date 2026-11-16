<?php
// ARQUIVO: ver_resumo.php (raiz do projeto)
// VERSÃO: CORRIGIDA + DEBUG TEMPORÁRIO

require_once __DIR__ . '/../libs/config.php';

// 1. SEGURANÇA E SESSÃO (Unificada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Aceita tanto aluno_id (novo) quanto user_id (antigo)
$id_logado = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? null;

if (!$id_logado) {
    die("Acesso negado. Por favor, faça login novamente.");
}

// 2. VALIDAÇÃO DO ARQUIVO SOLICITADO
$caminhoArquivo = $_GET['q'] ?? '';

// Proteção contra Directory Traversal
if (strpos($caminhoArquivo, 'components/') === false || strpos($caminhoArquivo, '..') !== false) {
    die("Arquivo inválido ou acesso não permitido.");
}

$arquivoFisico = __DIR__ . '/' . $caminhoArquivo;
if (!file_exists($arquivoFisico)) {
    die("Resumo não encontrado no servidor.");
}

// 3. CÁLCULO DINÂMICO DE URL (Localhost vs Produção)
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projectDir = rtrim(str_replace('\\', '/', __DIR__), '/');
$relativePath = str_replace($docRoot, '', $projectDir);

$urlSistema = $protocolo . $host . $relativePath;

// Caminho para os assets internos (Imagens/CSS do HTML do resumo)
$pastaDoResumo = dirname($caminhoArquivo);
$baseHrefParaAssets = $urlSistema . '/' . $pastaDoResumo . '/';

// Identificador único para o banco de dados
$idUnico = (count(explode('/', $caminhoArquivo)) >= 2) ? dirname($caminhoArquivo) . '/' . basename($caminhoArquivo) : $caminhoArquivo;
$versao = time();

// 4. CARREGA O HTML E INJETA A TAG BASE
$html = file_get_contents($arquivoFisico);
$html = str_replace('<head>', '<head><base href="' . $baseHrefParaAssets . '">', $html);

// 5. PACOTE DE SCRIPTS E ESTILOS (Injetado via HEREDOC)
$scriptsInjetados = <<<HTML

<style>
    mark.resumo-highlight { 
        background-color: #fef08a !important; 
        color: black !important; 
        cursor: pointer; 
        border-bottom: 2px solid #eab308;
        display: inline; position: relative; z-index: 10;
    }
    mark.resumo-highlight:hover { background-color: #fde047 !important; }
</style>

<script>
    window.resumoAppConfig = {
        apiUrl: "{$urlSistema}/api/api_anotacoes.php",
        idResumo: "{$idUnico}",
        idMigracao: "{$caminhoArquivo}"
    };

    window.ID_RESUMO_LEGADO = window.resumoAppConfig.idResumo;
    window.ID_MIGRACAO_PROVAVEL = window.resumoAppConfig.idMigracao;

    function fecharResumo() {
        if (window.opener || window.history.length > 1) {
            window.close();
            setTimeout(() => { 
                window.location.href = "{$urlSistema}/area_aluno.php"; 
            }, 200);
        } else {
            window.location.href = "{$urlSistema}/area_aluno.php";
        }
    }
</script>

<script src="{$urlSistema}/components/resumos/resumo-annotations.js?v={$versao}"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        let tentativas = 0;
        const maxTentativas = 15;

        function bootAnnotations() {
            if (typeof window.ResumoAnnotations !== 'undefined') {
                if (window.resumoApp) {
                    window.resumoApp.apiUrl = window.resumoAppConfig.apiUrl;
                    console.log("🔗 API confirmada:", window.resumoApp.apiUrl);
                } else {
                    window.resumoApp = new window.ResumoAnnotations();
                    window.resumoApp.apiUrl = window.resumoAppConfig.apiUrl;
                    window.resumoApp.init();
                }
            } else {
                tentativas++;
                if (tentativas < maxTentativas) setTimeout(bootAnnotations, 500);
            }
        }
        bootAnnotations();
    });
</script>

<script>
(function() {
    function caçarETransformarBotao() {
        const candidatos = document.querySelectorAll('a, button, .btn, .btn-voltar, div[role="button"]');
        for (let btn of candidatos) {
            if (btn.dataset.corrigido === "true") continue;
            
            const texto = (btn.textContent || "").toLowerCase().trim();
            const link = (btn.getAttribute('href') || "").toLowerCase();
            let ehOAlvo = false;

            if (texto.includes('voltar') || texto.includes('sair') || texto.includes('fechar') || texto.includes('lista')) ehOAlvo = true;
            else if (link.includes('area_aluno') || link.includes('index.php')) ehOAlvo = true;

            if (ehOAlvo) {
                btn.dataset.corrigido = "true"; 
                btn.innerHTML = "✖ Fechar Resumo"; 
                btn.setAttribute("style", "background-color: #dc2626 !important; border: 2px solid #b91c1c !important; color: white !important; cursor: pointer !important; padding: 10px 20px !important; border-radius: 8px !important; margin: 10px 0; display:inline-block; text-decoration: none; font-weight: bold; font-family: sans-serif;");
                
                if (btn.tagName === 'A') { btn.removeAttribute('href'); btn.removeAttribute('target'); }
                
                const novoBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(novoBtn, btn);
                
                novoBtn.onclick = function(e) {
                    e.preventDefault();
                    fecharResumo();
                };
            }
        }
    }
    document.addEventListener('DOMContentLoaded', caçarETransformarBotao);
    setInterval(caçarETransformarBotao, 1000);
})();
</script>
</body>
HTML;

// 6. ENTREGA O CONTEÚDO FINAL (Injeção única)
echo str_replace('</body>', $scriptsInjetados, $html);