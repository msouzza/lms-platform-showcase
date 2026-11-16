<?php
// ARQUIVO: public_html/logout.php
// OBJETIVO: Destruir a sessão e o Cookie Global (SSO)

// 1. Carrega as configurações (Para pegar o nome da sessão e domínio corretos)
require_once __DIR__ . '/../libs/config.php';

// A sessão já foi iniciada pelo config.php, então podemos limpá-la direto.

// 2. Limpa todas as variáveis de sessão
$_SESSION = array();

// 3. Mata o Cookie no Navegador (O passo mais importante para o SSO)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    
    // Mata usando os parâmetros atuais da sessão
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );

    // REFORÇO DE SEGURANÇA:
    // Força a morte do cookie no domínio global explicitamente
    // Caso o navegador tenha salvo de forma diferente.
    setcookie('SESSAO_ADRENALINA_SSO', '', time() - 42000, '/', '.adrenalinapolicial.com');
}

// 4. Destrói a sessão no servidor
session_destroy();

// 5. Redireciona para a Vitrine (Limpa)
header("Location: index.php");
exit;
?>