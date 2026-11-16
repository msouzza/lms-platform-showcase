<?php
// ARQUIVO: area-do-aluno/api/salvar_simulado.php

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
    echo json_encode(['success' => false, 'message' => 'Sessão expirou.']);
    exit;
}

$alunoId = $_SESSION['aluno_id'] ?? $_SESSION['user_id'];
session_write_close();
// ESCOLA VEM DA ORIGEM (URL)
$escolaId = ID_ESCOLA_ATUAL;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

try {
    $detalhesJson = isset($input['detalhes']) ? (is_array($input['detalhes']) ? json_encode($input['detalhes'], JSON_UNESCAPED_UNICODE) : $input['detalhes']) : null;
    $titulo = $input['titulo'] ?? 'Simulado Sem Título';

    // Validação e recálculo server-side da nota (impede adulteração da média da escola)
    $totalQuestoes = max(1, (int) ($input['total_questoes'] ?? 1));
    $acertos       = max(0, min($totalQuestoes, (int) ($input['acertos'] ?? 0)));
    $notaSegura    = round(($acertos / $totalQuestoes) * 100, 2);

    $sql = "INSERT INTO resultados_simulados 
            (aluno_id, escola_id, simulado_ref, titulo, acertos, total_questoes, nota, data_inicio, data_fim, tempo_decorrido, detalhes_json) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alunoId, $escolaId, $input['simulado_ref'], $titulo, $acertos, $totalQuestoes, $notaSegura, $input['data_inicio'], $input['data_fim'], $input['tempo_decorrido'], $detalhesJson]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    error_log('Erro salvar_simulado: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao salvar simulado.']);
}