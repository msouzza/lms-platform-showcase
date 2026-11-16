<?php
// ARQUIVO: area-do-aluno/api_progresso.php
// VERSÃO: DEFINITIVA (Isolamento via URL/Origem + Salvamento de Configuração)

ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Permissão de CORS estrita para subdomínios oficiais
$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
$hostOrigem = parse_url($origem, PHP_URL_HOST) ?: '';
if (preg_match('/^(?:[a-z0-9-]+\.)?(contextopolicial\.com|adrenalinapolicial\.com)(?:\.br)?$/i', $hostOrigem)) {
    header("Access-Control-Allow-Origin: " . $origem);
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

header('Content-Type: application/json');

// ========================================================
// 2. CONFIGURAÇÃO GLOBAL (Busca na Raiz do Site)
// ========================================================
$caminhosConfig = [
    __DIR__ . '/../libs/config.php', // XAMPP: Sobe 1 nível
    dirname($_SERVER['DOCUMENT_ROOT']) . '/libs/config.php' // cPanel: Produção
];

$configCarregado = false;
foreach ($caminhosConfig as $caminho) {
    if (file_exists($caminho)) {
        require_once $caminho;
        $configCarregado = true;
        break;
    }
}

if (!$configCarregado) {
    http_response_code(500);
    echo json_encode(['erro' => 'Arquivo config.php não encontrado (Raiz).']);
    exit;
}

// 3. Quem é o aluno? (Identidade via Sessão)
if (!isset($_SESSION['aluno_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['erro' => 'Usuário não logado.']);
    exit;
}
$alunoId = $_SESSION['aluno_id'] ?? $_SESSION['user_id'];
session_write_close(); // Libera o lock de sessão para requisições AJAX simultâneas

// 4. Onde ele está? (Contexto APENAS via URL/Config, NUNCA via sessão)
$escolaId = ID_ESCOLA_ATUAL;

$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? '';

try {
    if ($acao === 'sincronizar') {
        $lista = $input['lista'] ?? [];
        if (empty($lista)) { echo json_encode(['status'=>'ok']); exit; }

        $sql = "INSERT INTO progresso_alunos (aluno_id, escola_id, conteudo_ref, tipo_conteudo, data_conclusao) 
                VALUES (:aluno_id, :escola_id, :ref, :tipo, NOW())
                ON DUPLICATE KEY UPDATE data_conclusao = NOW()";
        
        $stmt = $pdo->prepare($sql);
        $pdo->beginTransaction();
        foreach ($lista as $item) {
            $stmt->execute([
                ':aluno_id' => $alunoId,
                ':escola_id' => $escolaId, 
                ':ref' => $item['ref'],
                ':tipo' => $item['tipo'] ?? 'Organizador'
            ]);
        }
        $pdo->commit();
        echo json_encode(['status' => 'sucesso', 'msg' => 'Organizador sincronizado']);

    } elseif ($acao === 'marcar') {
        $ref = $input['ref'] ?? $input['conteudo_ref'] ?? '';
        $tipo = $input['tipo'] ?? 'conteudo';

        if (empty($ref)) throw new Exception("Referência vazia.");

        if ($tipo === 'ConfigOrg') {
            $decoded = is_array($ref) ? $ref : json_decode((string)$ref, true);
            $ref = json_encode(is_array($decoded) ? $decoded : [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }

        $sql = "INSERT INTO progresso_alunos (aluno_id, escola_id, conteudo_ref, tipo_conteudo, data_conclusao) 
                VALUES (:aluno_id, :escola_id, :ref, :tipo, NOW())
                ON DUPLICATE KEY UPDATE data_conclusao = NOW()";
        
        $pdo->prepare($sql)->execute([':aluno_id' => $alunoId, ':escola_id' => $escolaId, ':ref' => $ref, ':tipo' => $tipo]);
        echo json_encode(['status' => 'sucesso', 'msg' => 'Marcado como concluído']);

    } elseif ($acao === 'desmarcar') {
        $ref = $input['ref'] ?? $input['conteudo_ref'] ?? ''; 
        $tipo = $input['tipo'] ?? 'Organizador';
        
        $stmt = $pdo->prepare("DELETE FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ? AND conteudo_ref = ? AND tipo_conteudo = ?");
        $stmt->execute([$alunoId, $escolaId, $ref, $tipo]);
        echo json_encode(['status' => 'sucesso', 'msg' => 'Item desmarcado']);

    } elseif ($acao === 'atualizar') {
        $ref = $input['conteudo_ref'] ?? $input['ref'] ?? $input['id'] ?? '';
        $tipo = $input['tipo'] ?? 'aula';
        $concluido = filter_var($input['status'] ?? $input['concluido'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($ref)) throw new Exception("Referência inválida.");

        if ($concluido) {
            $sql = "INSERT INTO progresso_alunos (aluno_id, escola_id, conteudo_ref, tipo_conteudo, data_conclusao) 
                    VALUES (:aluno_id, :escola_id, :ref, :tipo, NOW())
                    ON DUPLICATE KEY UPDATE data_conclusao = NOW()";
            $pdo->prepare($sql)->execute([':aluno_id' => $alunoId, ':escola_id' => $escolaId, ':ref' => $ref, ':tipo' => $tipo]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ? AND conteudo_ref = ? AND tipo_conteudo = ?");
            $stmt->execute([$alunoId, $escolaId, $ref, $tipo]);
        }
        echo json_encode(['status' => 'sucesso']);

    } elseif ($acao === 'salvar_config_org') {
        // Recebe as configurações e transforma em JSON com escape de tags HTML
        $configJson = json_encode($input['config'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        
        $pdo->beginTransaction();
        // 1. Deleta a configuração antiga desta escola específica
        $stmtDel = $pdo->prepare("DELETE FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ? AND tipo_conteudo = 'ConfigOrg'");
        $stmtDel->execute([$alunoId, $escolaId]);
        
        // 2. Salva a configuração nova
        $stmtIns = $pdo->prepare("INSERT INTO progresso_alunos (aluno_id, escola_id, conteudo_ref, tipo_conteudo, data_conclusao) VALUES (?, ?, ?, 'ConfigOrg', NOW())");
        $stmtIns->execute([$alunoId, $escolaId, $configJson]);
        $pdo->commit();
        
        echo json_encode(['status' => 'sucesso']);

    } elseif ($acao === 'listar') {
        $stmt = $pdo->prepare("SELECT conteudo_ref FROM progresso_alunos WHERE aluno_id = ? AND escola_id = ?");
        $stmt->execute([$alunoId, $escolaId]);
        echo json_encode(['concluidas' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    } else {
        echo json_encode(['status' => 'erro', 'msg' => 'Ação desconhecida']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Erro api_progresso: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao processar o progresso.']);
}
?>