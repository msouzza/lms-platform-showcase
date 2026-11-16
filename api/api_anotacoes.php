<?php
// ARQUIVO: /api/api_anotacoes.php

$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
$hostOrigem = parse_url($origem, PHP_URL_HOST) ?: '';
if (preg_match('/^(?:[a-z0-9-]+\.)?(contextopolicial\.com|adrenalinapolicial\.com)(?:\.br)?$/i', $hostOrigem)) {
    header("Access-Control-Allow-Origin: " . $origem);
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

header('Content-Type: application/json');

// ========================================================
// 2. CONFIGURAÇÃO GLOBAL (Busca dentro da subpasta api/)
// ========================================================
$caminhosConfig = [
    __DIR__ . '/../../libs/config.php', // XAMPP: Sobe 2 níveis (sai de 'api', depois sai do site)
    dirname($_SERVER['DOCUMENT_ROOT']) . '/libs/config.php' // cPanel: Produção (DOCUMENT_ROOT já sabe onde é a raiz do site)
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
    echo json_encode(['erro' => 'Arquivo config.php não encontrado (Subpasta API).']);
    exit;
}

if (!isset($_SESSION['aluno_id']) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Sessão expirada ou usuário não logado']);
    exit;
}

$aluno_id = $_SESSION['aluno_id'] ?? $_SESSION['user_id'];
session_write_close();
// ESCOLA VEM DA ORIGEM (URL)
$escola_id = ID_ESCOLA_ATUAL;

$input = json_decode(file_get_contents('php://input'), true);
$acao = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_GET['acao'] ?? '') : ($input['acao'] ?? '');

try {
    if ($acao === 'carregar') {
        $ref = $_GET['ref'] ?? '';
        if (!$ref) throw new Exception("Referência inválida");

        $stmt = $pdo->prepare("SELECT id, tipo, dados_json FROM anotacoes_alunos WHERE aluno_id = ? AND escola_id = ? AND resumo_ref = ?");
        $stmt->execute([$aluno_id, $escola_id, $ref]);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $output = ['highlights' => [], 'annotations' => []];
        foreach ($resultados as $row) {
            $dados = json_decode($row['dados_json'], true);
            $dados['db_id'] = $row['id']; 
            if ($row['tipo'] === 'highlight') {
                $output['highlights'][] = $dados;
            } else {
                $output['annotations'][] = $dados;
            }
        }
        echo json_encode($output);
        exit;
    }

    if ($acao === 'salvar') {
        $ref = $input['ref'] ?? '';
        $tipo = $input['tipo'] ?? ''; 
        $dados = $input['dados'] ?? [];

        if (!$ref || !$tipo) throw new Exception("Dados insuficientes");

        $json = json_encode($dados);
        $stmt = $pdo->prepare("INSERT INTO anotacoes_alunos (aluno_id, escola_id, resumo_ref, tipo, dados_json) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$aluno_id, $escola_id, $ref, $tipo, $json]);
        
        echo json_encode(['status' => 'sucesso', 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($acao === 'excluir') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM anotacoes_alunos WHERE id = ? AND aluno_id = ? AND escola_id = ?");
        $stmt->execute([$id, $aluno_id, $escola_id]);
        echo json_encode(['status' => 'sucesso']);
        exit;
    }
} catch (Exception $e) {
    error_log('Erro api_anotacoes: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao processar anotação.']);
}