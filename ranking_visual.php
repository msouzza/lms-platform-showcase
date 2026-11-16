<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking da Escola</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
        /* =========================================
           VARIÁVEIS DE CORES (MODERN & CLEAN)
           ========================================= */
        :root {
            --bg-color: #f4f7f6;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --primary: #0f172a; /* Azul bem escuro, quase preto (Estilo Policial) */
            --gold: #fbbf24;
            --silver: #94a3b8;
            --bronze: #b45309;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            padding: 20px;
        }

        .ranking-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header-ranking {
            text-align: center;
            margin-bottom: 40px;
        }

        .header-ranking h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .header-ranking p {
            color: var(--text-muted);
            margin-top: 5px;
        }

        /* =========================================
           O PÓDIO (TOP 3) - FLEXBOX
           ========================================= */
        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end; /* Alinha por baixo para dar efeito de escada */
            gap: 15px;
            margin-bottom: 40px;
        }

        .podium-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 20px 10px;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            width: 30%;
            position: relative;
            transition: transform 0.3s ease;
        }

        .podium-card:hover {
            transform: translateY(-5px);
        }

        /* Destaque para o 1º Lugar */
        .podium-card.first {
            height: 220px;
            border: 2px solid var(--gold);
            z-index: 2;
        }

        /* 2º e 3º Lugares */
        .podium-card.second { height: 190px; border-top: 4px solid var(--silver); }
        .podium-card.third { height: 170px; border-top: 4px solid var(--bronze); }

        .medal-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .podium-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-main);
            margin-bottom: 5px;
        }

        .podium-pts {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* =========================================
           A LISTA (DO 4º AO 50º)
           ========================================= */
        .ranking-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ranking-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s;
        }

        .ranking-row:hover {
            transform: scale(1.01);
            border-left: 4px solid var(--primary);
        }

        .row-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .pos-number {
            font-weight: 800;
            color: var(--text-muted);
            width: 30px;
        }

        .row-name {
            font-weight: 600;
        }

        .row-badge {
            font-size: 0.8rem;
            background-color: #e2e8f0;
            color: #475569;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .row-pts {
            font-weight: 800;
            color: var(--primary);
        }

        /* =========================================
           RESPONSIVIDADE (CELULARES)
           ========================================= */
        @media (max-width: 600px) {
            /* No celular, o pódio vira uma lista em destaque */
            .podium {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            .podium-card {
                width: 100%;
                height: auto !important; /* Remove as alturas fixas da escada */
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px 20px;
            }
            /* Reordena para o 1º aparecer no topo no celular */
            .podium-card.first { order: 1; }
            .podium-card.second { order: 2; }
            .podium-card.third { order: 3; }

            .medal-icon { margin-bottom: 0; font-size: 2rem; }
            .podium-name { margin-bottom: 0; }
        }
    </style>
</head>
<body>

<div class="ranking-container">
    <div class="header-ranking">
        <h1>🏆 Elite da Aprovação</h1>
        <p>Ranking de desempenho nos Simulados e Quizzes</p>
    </div>

    <div class="podium" id="podium-container">
        </div>

    <div class="ranking-list" id="list-container">
        </div>
</div>

<script>
    // SIMULAÇÃO DO SEU JSON DE RETORNO DA API
    // Na vida real, você fará um fetch('/api/ranking.php')
    const dadosRanking = [
        { posicao: 1, nome_exibicao: "Mateus G.", pontos: 1250 },
        { posicao: 2, nome_exibicao: "Laura N.", pontos: 1100 },
        { posicao: 3, nome_exibicao: "Samuel S.", pontos: 950 },
        { posicao: 4, nome_exibicao: "Alex A.", pontos: 820 },
        { posicao: 5, nome_exibicao: "Nikolas F.", pontos: 780 },
        { posicao: 6, nome_exibicao: "João M.", pontos: 450 },
        { posicao: 7, nome_exibicao: "Maria C.", pontos: 320 }
    ];

    const podiumContainer = document.getElementById('podium-container');
    const listContainer = document.getElementById('list-container');

    // Função para definir o Nível/Badge (A Medalha Menor) baseada em pontos
    function getBadge(pontos) {
        if (pontos >= 1000) return "🎯 Sniper";
        if (pontos >= 800) return "⚡ Força Tática";
        if (pontos >= 500) return "🛡️ Operacional";
        return "📚 Cadete";
    }

    // Renderiza a tela
    dadosRanking.forEach((aluno, index) => {
        // TOP 3 (Vai para o Pódio)
        if (aluno.posicao <= 3) {
            let classe = '', icone = '';
            if (aluno.posicao === 1) { classe = 'first'; icone = '🥇'; }
            else if (aluno.posicao === 2) { classe = 'second'; icone = '🥈'; }
            else if (aluno.posicao === 3) { classe = 'third'; icone = '🥉'; }

            const podiumHTML = `
                <div class="podium-card ${classe}">
                    <div class="medal-icon">${icone}</div>
                    <div class="podium-name">${aluno.nome_exibicao}</div>
                    <div class="podium-pts">${aluno.pontos} pts</div>
                    <div class="row-badge" style="margin-top: 10px; display: inline-block;">${getBadge(aluno.pontos)}</div>
                </div>
            `;
            podiumContainer.innerHTML += podiumHTML;
        } 
        // DO 4º EM DIANTE (Vai para a Lista)
        else {
            const listHTML = `
                <div class="ranking-row">
                    <div class="row-left">
                        <div class="pos-number">${aluno.posicao}º</div>
                        <div class="row-name">${aluno.nome_exibicao}</div>
                        <div class="row-badge">${getBadge(aluno.pontos)}</div>
                    </div>
                    <div class="row-pts">${aluno.pontos} pts</div>
                </div>
            `;
            listContainer.innerHTML += listHTML;
        }
    });
</script>

</body>
</html>