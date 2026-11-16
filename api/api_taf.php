<?php
// ARQUIVO: api/api_taf.php

$origem = $_SERVER['HTTP_ORIGIN'] ?? '';
$hostOrigem = parse_url($origem, PHP_URL_HOST) ?: '';
if (preg_match('/^(?:[a-z0-9-]+\.)?(contextopolicial\.com|adrenalinapolicial\.com)(?:\.br)?$/i', $hostOrigem)) {
    header("Access-Control-Allow-Origin: " . $origem);
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

header('Content-Type: application/json');

$caminhosConfig = [
    __DIR__ . '/../../libs/config.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/libs/config.php'
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
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

$aluno_id = $_SESSION['aluno_id'] ?? $_SESSION['user_id'];
session_write_close();
$escola_id = ID_ESCOLA_ATUAL;

$input = json_decode(file_get_contents('php://input'), true);
$acao = $input['acao'] ?? '';

try {
    if ($acao === 'salvar') {
        $resultados = $input['dados'] ?? [];

        // CORREÇÃO: Verifica duplicata antes de inserir
        $stmtCheck = $pdo->prepare(
            "SELECT COUNT(*) FROM historico_taf 
             WHERE aluno_id = ? AND escola_id = ? AND data_treino = ? AND sexo = ? AND tipo_teste = ? AND resultado = ?"
        );
        $stmt = $pdo->prepare("INSERT INTO historico_taf 
    (aluno_id, escola_id, data_treino, sexo, tipo_teste, resultado) 
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE resultado = VALUES(resultado)");

        $pdo->beginTransaction();
        $inseridos = 0;

        foreach ($resultados as $r) {
            // Checa se já existe registro idêntico
            $stmtCheck->execute([$aluno_id, $escola_id, $r['data'], $r['sexo'], $r['id'], $r['resultadoNumerico']]);
            $existe = $stmtCheck->fetchColumn() > 0;

            if (!$existe) {
                $stmt->execute([$aluno_id, $escola_id, $r['data'], $r['sexo'], $r['id'], $r['resultadoNumerico']]);
                $inseridos++;
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'sucesso', 'inseridos' => $inseridos, 'ignorados' => count($resultados) - $inseridos]);
        exit;
    }

    if ($acao === 'excluir_dia') {
        $data = $input['data'] ?? '';
        $sexo = $input['sexo'] ?? '';
        if (!$data)
            throw new Exception("Data inválida");

        $stmt = $pdo->prepare("DELETE FROM historico_taf WHERE aluno_id = ? AND escola_id = ? AND data_treino = ? AND sexo = ?");
        $stmt->execute([$aluno_id, $escola_id, $data, $sexo]);

        echo json_encode(['status' => 'sucesso']);
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log('Erro api_taf: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'Erro interno ao processar treino TAF.']);
}