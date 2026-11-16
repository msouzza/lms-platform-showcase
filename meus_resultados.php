<?php
// ARQUIVO: area-do-aluno/meus_resultados.php

require_once __DIR__ . '/../libs/config.php';
require_once __DIR__ . '/../database/conexao.php';

// 1. Segurança e Sessão
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$alunoId = $_SESSION['user_id'];
$escolaId = defined('ID_ESCOLA_ATUAL') ? ID_ESCOLA_ATUAL : 1;

// --- 2. BUSCA DOS RESULTADOS INDIVIDUAIS ---
$sqlSim = "SELECT id, titulo, nota, acertos, total_questoes, tempo_decorrido, data_inicio as data, 'simulado' as tipo 
           FROM resultados_simulados 
           WHERE aluno_id = ? AND escola_id = ? 
           ORDER BY data_inicio DESC";
$stmtSim = $pdo->prepare($sqlSim);
$stmtSim->execute([$alunoId, $escolaId]);
$simulados = $stmtSim->fetchAll(PDO::FETCH_ASSOC);

$sqlQuiz = "SELECT id, titulo, nota, acertos, total_questoes, data_realizacao as data, 'quiz' as tipo 
            FROM resultados_quizzes 
            WHERE aluno_id = ? AND escola_id = ? 
            ORDER BY data_realizacao DESC";
$stmtQuiz = $pdo->prepare($sqlQuiz);
$stmtQuiz->execute([$alunoId, $escolaId]);
$quizzes = $stmtQuiz->fetchAll(PDO::FETCH_ASSOC);

// --- 3. CÁLCULOS DA MÉDIA DO ALUNO ---
$totalSim = count($simulados);
$somaSim = 0;
foreach ($simulados as $s)
    $somaSim += $s['nota'];
$mediaSimAluno = ($totalSim > 0) ? ($somaSim / $totalSim) : 0;

$totalQuiz = count($quizzes);
$somaQuiz = 0;
foreach ($quizzes as $q)
    $somaQuiz += $q['nota'];
$mediaQuizAluno = ($totalQuiz > 0) ? ($somaQuiz / $totalQuiz) : 0;

// --- 4. CÁLCULOS DA MÉDIA DA CONCORRÊNCIA (O Motor Comparativo) ---
// Média Geral Simulados (Excluindo o próprio aluno)
$stmtMediaGeralSim = $pdo->prepare("SELECT AVG(nota) as media_geral FROM resultados_simulados WHERE aluno_id != ? AND escola_id = ?");
$stmtMediaGeralSim->execute([$alunoId, $escolaId]);
$mediaGeralSim = (float) $stmtMediaGeralSim->fetchColumn();

// Média Geral Quizzes (Excluindo o próprio aluno)
$stmtMediaGeralQuiz = $pdo->prepare("SELECT AVG(nota) as media_geral FROM resultados_quizzes WHERE aluno_id != ? AND escola_id = ?");
$stmtMediaGeralQuiz->execute([$alunoId, $escolaId]);
$mediaGeralQuiz = (float) $stmtMediaGeralQuiz->fetchColumn();

// Função auxiliar de cor
function getCorNota($nota)
{
    if ($nota >= 70)
        return ['bg' => '#d4edda', 'text' => '#155724', 'border' => '#c3e6cb'];
    if ($nota >= 50)
        return ['bg' => '#fff3cd', 'text' => '#856404', 'border' => '#ffeeba'];
    return ['bg' => '#f8d7da', 'text' => '#721c24', 'border' => '#f5c6cb'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Resultados | Área do Aluno</title>
    <style>
        :root {
            --primary: #4a90e2;
            --bg-light: #f4f7f6;
            --white: #ffffff;
            --text-dark: #333;
            --radius: 8px;
            --verde-sucesso: #16a34a;
            --verde-bg: #dcfce7;
            --laranja-alerta: #ea580c;
            --laranja-bg: #ffedd5;
            --cinza-texto: #64748b;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Abas e Listas */
        .tabs-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
            background: #e9ecef;
            padding: 5px;
            border-radius: 50px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .tab-btn {
            padding: 10px 30px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 40px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .tab-btn:hover {
            color: var(--primary);
        }

        .tab-btn.active {
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-item {
            background: var(--white);
            border-radius: var(--radius);
            margin-bottom: 15px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid transparent;
        }

        .res-info h3 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            color: #333;
        }

        .res-meta {
            font-size: 0.9rem;
            color: #555;
            line-height: 1.6;
        }

        .res-score {
            text-align: right;
            min-width: 140px;
        }

        .score-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .btn-details {
            font-size: 0.85rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            padding: 5px 10px;
            border: 1px solid var(--primary);
            border-radius: 4px;
            transition: 0.2s;
        }

        .btn-details:hover {
            background: var(--primary);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: #fff;
            border-radius: var(--radius);
            color: #999;
            border: 2px dashed #ddd;
        }

        /* PAINEL COMPARATIVO */
        .dashboard-desempenho {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card-desempenho {
            background: var(--white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .card-titulo {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .comparativo-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .nota-box {
            text-align: center;
        }

        .nota-label {
            font-size: 0.75rem;
            color: var(--cinza-texto);
            font-weight: 600;
            text-transform: uppercase;
        }

        .nota-valor {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .acima-media .nota-valor {
            color: var(--verde-sucesso);
        }

        .abaixo-media .nota-valor {
            color: var(--laranja-alerta);
        }

        .geral-valor {
            color: var(--text-dark);
        }

        .vs-badge {
            background: #e2e8f0;
            color: var(--cinza-texto);
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: bold;
        }

        .feedback-msg {
            font-size: 0.85rem;
            padding: 10px;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
        }

        .feedback-msg.verde {
            background: var(--verde-bg);
            color: #166534;
        }

        .feedback-msg.laranja {
            background: var(--laranja-bg);
            color: #9a3412;
        }

        /* =========================================
           BOTÃO PADRÃO DE VOLTAR (VERMELHO)
           ========================================= */
        .btn-voltar-red {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ef4444;
            /* Vermelho moderno (Tailwind) */
            color: #ffffff !important;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
            border: 1px solid #dc2626;
        }

        .btn-voltar-red:hover {
            background-color: #dc2626;
            /* Vermelho mais escuro no hover */
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);
        }

        @media (max-width: 600px) {
            .result-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .res-score {
                text-align: left;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 10px;
                border-top: 1px solid #eee;
                padding-top: 10px;
            }

            .tabs-container {
                width: 100%;
                justify-content: space-between;
            }

            .tab-btn {
                flex: 1;
                padding: 10px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="margin:0;">Meu Desempenho</h1>

            <a href="area_aluno.php" class="btn-voltar-red">
                Fechar
            </a>
        </div>
        <div class="card-desempenho" style="margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">

            <div
                style="font-size: 2.5rem; background: var(--bg-light); width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                🎯
            </div>
            <div>
                <h2 style="margin: 0; color: var(--primary); font-size: 1.5rem; font-weight: 800;">Painel Tático: O Seu
                    Desempenho no Campo de Batalha</h2>
                <p style="margin: 5px 0 0 0; color: var(--cinza-texto); font-size: 0.95rem;">
                    Compare seus resultados com a concorrência, identifique seus pontos fracos e ajuste sua rota rumo à
                    posse!
                </p>
            </div>
        </div>

        <div class="dashboard-desempenho">

            <div class="card-desempenho">
                <div class="card-titulo">
                    <span>🎯 Média em Simulados</span>
                </div>

                <?php if ($totalSim > 0):
                    $statusSim = ($mediaSimAluno >= $mediaGeralSim) ? 'acima-media' : 'abaixo-media';
                    ?>
                    <div class="comparativo-box <?php echo $statusSim; ?>">
                        <div class="nota-box">
                            <div class="nota-label">Sua Média</div>
                            <div class="nota-valor"><?php echo number_format($mediaSimAluno, 1); ?>%</div>
                        </div>
                        <div class="vs-badge">VS</div>
                        <div class="nota-box">
                            <div class="nota-label">Concorrência</div>
                            <div class="nota-valor geral-valor"><?php echo number_format($mediaGeralSim, 1); ?>%</div>
                        </div>
                    </div>

                    <?php if ($statusSim == 'acima-media'): ?>
                        <div class="feedback-msg verde">🚀 Excelente! Você está acima da média. A vaga está próxima!</div>
                    <?php else: ?>
                        <div class="feedback-msg laranja">⚠️ Atenção! Você está abaixo da média geral. Foco na revisão!</div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999; font-size: 0.9rem; text-align:center; margin-top: 20px;">Faça seu primeiro
                        simulado para comparar sua nota.</p>
                <?php endif; ?>
            </div>

            <div class="card-desempenho">
                <div class="card-titulo">
                    <span>⚡ Média em Quizzes</span>
                </div>

                <?php if ($totalQuiz > 0):
                    $statusQuiz = ($mediaQuizAluno >= $mediaGeralQuiz) ? 'acima-media' : 'abaixo-media';
                    ?>
                    <div class="comparativo-box <?php echo $statusQuiz; ?>">
                        <div class="nota-box">
                            <div class="nota-label">Sua Média</div>
                            <div class="nota-valor"><?php echo number_format($mediaQuizAluno, 1); ?>%</div>
                        </div>
                        <div class="vs-badge">VS</div>
                        <div class="nota-box">
                            <div class="nota-label">Concorrência</div>
                            <div class="nota-valor geral-valor"><?php echo number_format($mediaGeralQuiz, 1); ?>%</div>
                        </div>
                    </div>

                    <?php if ($statusQuiz == 'acima-media'): ?>
                        <div class="feedback-msg verde">🔥 Parabéns! Seu desempenho nos testes rápidos supera a concorrência.
                        </div>
                    <?php else: ?>
                        <div class="feedback-msg laranja">⚠️ Cuidado! A concorrência está mais rápida e precisa nos quizzes.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999; font-size: 0.9rem; text-align:center; margin-top: 20px;">Faça seu primeiro quiz
                        para comparar sua agilidade.</p>
                <?php endif; ?>
            </div>

        </div>
        <div class="tabs-container">
            <button class="tab-btn active" onclick="openTab('simulados')">📚 Meus Simulados</button>
            <button class="tab-btn" onclick="openTab('quizzes')">⚡ Meus Quizzes</button>
        </div>

        <div id="view-simulados" class="tab-content active">
            <?php if (count($simulados) == 0): ?>
                <div class="empty-state">Você ainda não realizou nenhum simulado neste curso.</div>
            <?php else: ?>
                <?php foreach ($simulados as $sim):
                    $cor = getCorNota($sim['nota']);
                    $dataF = date('d/m/Y H:i', strtotime($sim['data']));
                    $erros = $sim['total_questoes'] - $sim['acertos'];
                    $tempo = !empty($sim['tempo_decorrido']) ? $sim['tempo_decorrido'] : '--:--:--';
                    ?>
                    <div class="result-item" style="border-left-color: <?php echo $cor['text']; ?>">
                        <div class="res-info">
                            <h3><?php echo htmlspecialchars($sim['titulo']); ?></h3>
                            <div class="res-meta">
                                📅 Realizado em: <strong><?php echo $dataF; ?></strong><br>
                                ⏱ Duração: <strong><?php echo $tempo; ?></strong><br>
                                📊 Total: <strong><?php echo $sim['total_questoes']; ?></strong> |
                                ✅ Acertos: <strong style="color:green"><?php echo $sim['acertos']; ?></strong> |
                                ❌ Erros: <strong style="color:red"><?php echo $erros; ?></strong>
                            </div>
                        </div>

                        <div class="res-score">
                            <span class="score-badge"
                                style="background:<?php echo $cor['bg']; ?>; color:<?php echo $cor['text']; ?>">
                                Nota: <?php echo number_format($sim['nota'], 1); ?>
                            </span>
                            <br>
                            <a href="ver_correcao.php?id=<?php echo $sim['id']; ?>&tipo=simulado" class="btn-details">Ver
                                Correção</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="view-quizzes" class="tab-content">
            <?php if (count($quizzes) == 0): ?>
                <div class="empty-state">Você ainda não realizou nenhum quiz rápido neste curso.</div>
            <?php else: ?>
                <?php foreach ($quizzes as $quiz):
                    $cor = getCorNota($quiz['nota']);
                    $dataF = date('d/m/Y H:i', strtotime($quiz['data']));
                    $erros = $quiz['total_questoes'] - $quiz['acertos'];
                    ?>
                    <div class="result-item" style="border-left-color: <?php echo $cor['text']; ?>">
                        <div class="res-info">
                            <h3><?php echo htmlspecialchars($quiz['titulo']); ?></h3>
                            <div class="res-meta">
                                📅 Realizado em: <strong><?php echo $dataF; ?></strong><br>
                                📊 Total: <strong><?php echo $quiz['total_questoes']; ?></strong> |
                                ✅ Acertos: <strong style="color:green"><?php echo $quiz['acertos']; ?></strong> |
                                ❌ Erros: <strong style="color:red"><?php echo $erros; ?></strong>
                            </div>
                        </div>
                        <div class="res-score">
                            <span class="score-badge"
                                style="background:<?php echo $cor['bg']; ?>; color:<?php echo $cor['text']; ?>">
                                Nota: <?php echo number_format($quiz['nota'], 1); ?>
                            </span>
                            <br>
                            <a href="ver_correcao.php?id=<?php echo $quiz['id']; ?>&tipo=quiz" class="btn-details">Ver
                                Correção</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function openTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('view-simulados').classList.remove('active');
            document.getElementById('view-quizzes').classList.remove('active');

            event.currentTarget.classList.add('active');
            document.getElementById('view-' + tabName).classList.add('active');
        }
    </script>

</body>

</html>