<?php
// ARQUIVO: area-do-aluno/ver_correcao.php
require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php';

// 1. Segurança e Identidade Unificada
if (session_status() === PHP_SESSION_NONE) session_start();

$id_logado = $_SESSION['aluno_id'] ?? $_SESSION['user_id'] ?? null;
if (!$id_logado) die("Acesso negado.");

// 2. Parâmetros da URL
$id = $_GET['id'] ?? null;
$tipo = $_GET['tipo'] ?? 'simulado'; 

if (!$id) die("ID não informado.");

// --- FUNÇÃO PARA CORRIGIR TEXTO LATEX ---
function formatarTextoLegal($texto) {
    if (empty($texto)) return "";
    $texto = str_replace('$', '', $texto);
    $texto = str_replace('\S', '§', $texto);
    $texto = str_replace('^{\circ}', 'º', $texto);
    $texto = str_replace('\\', '', $texto);
    return nl2br(htmlspecialchars($texto));
}
// -----------------------------------------------------

// 3. Busca no Banco de Dados (MUDANÇA: Inclusão do isolamento escola_id)
$tabela = ($tipo === 'quiz') ? 'resultados_quizzes' : 'resultados_simulados';
$escolaIdAtual = ID_ESCOLA_ATUAL; 

try {
    // Blindado: Só vê a correção se pertencer ao aluno e à escola atual da URL
    $sql = "SELECT * FROM $tabela WHERE id = ? AND aluno_id = ? AND escola_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $id_logado, $escolaIdAtual]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resultado) {
        die("<div style='padding:20px; font-family:sans-serif;'>❌ Resultado não encontrado ou não pertence ao seu acesso nesta escola.</div>");
    }

    $detalhes = json_decode($resultado['detalhes_json'], true);
    
    if (!is_array($detalhes)) {
        $detalhes = [];
        $avisoSemDetalhes = true;
    }

} catch (Exception $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// 4. Cálculos para o Cabeçalho
$nota = number_format($resultado['nota'], 1);
$acertos = $resultado['acertos'];
$total = $resultado['total_questoes'];

// --- NOVO: Resgate do Tempo ---
$tempo = !empty($resultado['tempo_decorrido']) ? $resultado['tempo_decorrido'] : '--:--:--';

// Data
$dataDb = $resultado['data_inicio'] ?? $resultado['data_realizacao'] ?? date('Y-m-d H:i:s');
$data = date('d/m/Y H:i', strtotime($dataDb));

// Cor da Nota
$corNota = ($resultado['nota'] >= 70) ? '#28a745' : (($resultado['nota'] >= 50) ? '#ffc107' : '#dc3545');
$textoCorNota = ($resultado['nota'] >= 50 && $resultado['nota'] < 70) ? '#333' : '#fff'; 

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção: <?php echo htmlspecialchars($resultado['titulo'] ?? 'Simulado'); ?></title>
    <style>
        :root {
            --bg-body: #f4f6f9;
            --bg-card: #ffffff;
            --text-main: #333;
            --text-muted: #666;
            --border: #e0e0e0;
            --success-light: #d4edda;
            --success-dark: #155724;
            --error-light: #f8d7da;
            --error-dark: #721c24;
            --primary: #007bff;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        
        /* Header Card (Resumo) */
        .header-card {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 4px solid <?php echo $corNota; ?>;
        }
        .header-info h1 { margin: 0 0 5px 0; font-size: 1.5rem; }
        .header-info p { margin: 2px 0; color: var(--text-muted); font-size: 0.95rem; }
        .score-badge {
            background: <?php echo $corNota; ?>;
            color: <?php echo $textoCorNota; ?>;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.5rem;
            text-align: center;
            min-width: 80px;
        }
        .score-label { font-size: 0.7rem; display: block; text-transform: uppercase; opacity: 0.9; }

        /* Questões */
        .question-card {
            background: var(--bg-card);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .q-header {
            padding: 15px 20px;
            background: #fafafa;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .q-number { font-weight: bold; color: var(--text-muted); }
        .q-status { font-size: 0.85rem; font-weight: bold; padding: 4px 10px; border-radius: 4px; }
        .status-correct { background: var(--success-light); color: var(--success-dark); }
        .status-wrong { background: var(--error-light); color: var(--error-dark); }

        .q-body { padding: 20px; }
        .q-text { 
            font-size: 1.1rem; 
            margin-bottom: 15px; 
            line-height: 1.6; 
            font-weight: 500;
            white-space: pre-wrap;
            word-wrap: break-word; 
        }
        
        .answer-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        @media (max-width: 600px) { .answer-box { grid-template-columns: 1fr; } }

        .ans-item { padding: 10px; border-radius: 6px; border: 1px solid #ddd; }
        .ans-label { display: block; font-size: 0.75rem; color: #888; margin-bottom: 4px; text-transform: uppercase; }
        .ans-value { font-weight: bold; font-size: 1.1rem; }
        
        .ans-user.correct { border-color: var(--success-dark); background: #f0fff4; color: var(--success-dark); }
        .ans-user.wrong { border-color: var(--error-dark); background: #fff5f5; color: var(--error-dark); }
        .ans-correct { border-color: var(--success-dark); background: #f0fff4; color: var(--success-dark); }

        .comment-box {
            margin-top: 15px;
            padding: 15px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            color: #0d47a1;
            font-size: 0.95rem;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }

        .actions { margin-top: 30px; text-align: center; padding-bottom: 40px; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.2s;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }
        .btn:hover { background: #5a6268; }
        .btn-print { background: var(--primary); margin-left: 10px; }
        .btn-print:hover { background: #0056b3; }

        @media print {
            .btn, .actions { display: none; }
            body { background: white; }
            .container { max-width: 100%; box-shadow: none; }
            .question-card, .header-card { box-shadow: none; border: 1px solid #ccc; break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header-card">
        <div class="header-info">
            <h1><?php echo htmlspecialchars($resultado['titulo'] ?? 'Resultado do Exame'); ?></h1>
            <p>📅 Realizado em: <?php echo $data; ?> • ⏱ Duração: <strong><?php echo $tempo; ?></strong></p>
            <p>📊 Tipo: <?php echo ucfirst($tipo); ?> | Total de Questões: <?php echo $total; ?></p>
            <p>✅ Acertos: <strong style="color:green"><?php echo $acertos; ?></strong> | ❌ Erros: <strong style="color:red"><?php echo $total - $acertos; ?></strong></p>
        </div>
        <div class="score-badge">
            <span class="score-label">Sua Nota</span>
            <?php echo $nota; ?>%
        </div>
    </div>

    <?php if (isset($avisoSemDetalhes)): ?>
        <div style="text-align:center; padding: 40px; color: #666;">
            <h3>⚠️ Detalhes indisponíveis</h3>
            <p>Este simulado é antigo ou não salvou o detalhamento questão a questão.</p>
        </div>
    <?php else: ?>

        <?php foreach ($detalhes as $idx => $item): 
            $acertou = $item['acertou'] ?? false;
            $numero = $idx + 1;
            
            $respAluno = $item['respostaAluno'] ?? '-';
            $respCerta = $item['respostaCorreta'] ?? '-';
            $textoQuestao = $item['questao'] ?? 'Questão sem enunciado.';
            $comentario = $item['comentario'] ?? null;
        ?>
            <div class="question-card">
                <div class="q-header">
                    <span class="q-number">Questão <?php echo $numero; ?></span>
                    <?php if ($acertou): ?>
                        <span class="q-status status-correct">✓ ACERTOU</span>
                    <?php else: ?>
                        <span class="q-status status-wrong">✕ ERROU</span>
                    <?php endif; ?>
                </div>
                
                <div class="q-body">
                    <div class="q-text"><?php echo formatarTextoLegal($textoQuestao); ?></div>
                    
                    <div class="answer-box">
                        <div class="ans-item ans-user <?php echo $acertou ? 'correct' : 'wrong'; ?>">
                            <span class="ans-label">Você marcou:</span>
                            <span class="ans-value"><?php echo htmlspecialchars($respAluno); ?></span>
                        </div>

                        <div class="ans-item ans-correct">
                            <span class="ans-label">Gabarito correto:</span>
                            <span class="ans-value"><?php echo htmlspecialchars($respCerta); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($comentario)): ?>
                        <div class="comment-box">
                            <strong>💡 Comentário:</strong><br>
                            <?php echo formatarTextoLegal($comentario); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <div class="actions">
        <a href="meus_resultados.php" class="btn">← Voltar para Meus Resultados</a>
        <button onclick="window.print()" class="btn btn-print">🖨️ Imprimir / PDF</button>
    </div>

</div>

</body>
</html>