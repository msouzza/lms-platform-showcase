<?php
// ARQUIVO: area-do-aluno/ver_flashcard.php

require_once __DIR__ . '/../libs/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// MUDANÇA: Identidade Unificada
$id_logado = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? null;
if (!$id_logado) die("Acesso negado.");

$caminhoArquivo = $_GET['q'] ?? '';

// Segurança básica
if (strpos($caminhoArquivo, 'components/') === false || strpos($caminhoArquivo, '..') !== false) {
    die("Arquivo inválido.");
}

$arquivoFisico = __DIR__ . '/' . $caminhoArquivo;
if (!file_exists($arquivoFisico)) die("Flashcard não encontrado.");

// MUDANÇA: Cálculo de URL para Redirecionamento 100% Seguro
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); 
$urlSistema = $protocolo . $host . rtrim($scriptPath, '/\\');

$html = file_get_contents($arquivoFisico);
$pastaBase = dirname($caminhoArquivo) . '/'; 
$html = str_replace('<head>', '<head><base href="' . $pastaBase . '">', $html);

// MUDANÇA: Lógica de fechamento no botão adicionada
$scriptCorretor = <<<JS
<script>
(function() {
    // Procura por botões de voltar, fechar ou ícones de seta
    const PALAVRAS_CHAVE = ["voltar", "fechar", "sair", "retornar", "dashboard", "area_aluno"];
    
    function caçarETransformarBotao() {
        const candidatos = document.querySelectorAll('a, button, .btn, .back-button, .btn-voltar, div[role="button"]');

        for (let btn of candidatos) {
            // Se já tem o X e o texto certo, ignora
            if (btn.dataset.corrigido === "true" && btn.innerHTML.includes("✖")) continue;

            const texto = (btn.textContent || "").toLowerCase().trim();
            const classes = (btn.className || "").toLowerCase();
            const link = (btn.getAttribute('href') || "").toLowerCase();
            const title = (btn.getAttribute('title') || "").toLowerCase();

            let ehOAlvo = false;
            
            // Lógica de detecção
            if (PALAVRAS_CHAVE.some(p => texto.includes(p))) ehOAlvo = true;
            else if (classes.includes('back') || classes.includes('voltar')) ehOAlvo = true;
            else if (link.includes('area_aluno') || link.includes('index.php')) ehOAlvo = true;
            else if (title.includes('voltar')) ehOAlvo = true;

            if (ehOAlvo) {
                console.log("⚡ Flashcard Button Fixed:", btn);
                btn.dataset.corrigido = "true"; 
                
                // Limpa e aplica estilo
                btn.innerHTML = ""; 
                btn.textContent = "✖ Fechar Flashcard";
                
                btn.setAttribute("style", "background-color: #dc2626 !important; border: 2px solid #b91c1c !important; color: white !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; text-decoration: none !important; font-weight: bold !important; padding: 10px 20px !important; border-radius: 8px !important; min-width: 150px; box-shadow: 0 4px 6px rgba(0,0,0,0.2) !important; z-index: 9999 !important;");
                
                if (btn.tagName === 'A') { btn.removeAttribute('href'); btn.removeAttribute('target'); }

                const novoBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(novoBtn, btn);

                novoBtn.onclick = function(e) {
                    e.preventDefault();
                    if (window.opener || window.history.length > 1) {
                        window.close(); 
                        setTimeout(() => { window.location.href = "{$urlSistema}/area_aluno.php"; }, 200);
                    } else {
                        window.location.href = "{$urlSistema}/area_aluno.php";
                    }
                };
            }
        }
    }

    document.addEventListener('DOMContentLoaded', caçarETransformarBotao);
    setInterval(caçarETransformarBotao, 800); // Verifica constantemente
})();
</script>
</body>
JS;

echo str_replace('</body>', $scriptCorretor, $html);
?>