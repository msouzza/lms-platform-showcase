// Script para integrar sistema de anotações em todos os resumos
// Execute este script no Node.js para atualizar todos os arquivos

const fs = require('fs');
const path = require('path');

const resumosDir = './components/resumos';
const scriptTag = '<script src="../resumo-annotations.js"></script>';

function updateResumoFile(filePath, relativePath) {
    try {
        let content = fs.readFileSync(filePath, 'utf8');
        
        // Verificar se já tem o script
        if (content.includes('resumo-annotations.js')) {
            console.log(`✓ ${relativePath} já possui o sistema de anotações`);
            return;
        }
        
        // Encontrar a linha do verifica-pagamento.js e adicionar após ela
        const lines = content.split('\n');
        let insertIndex = -1;
        
        for (let i = 0; i < lines.length; i++) {
            if (lines[i].includes('verifica-pagamento.js')) {
                insertIndex = i + 1;
                break;
            }
        }
        
        if (insertIndex === -1) {
            // Se não encontrou verifica-pagamento.js, procurar por </head>
            for (let i = 0; i < lines.length; i++) {
                if (lines[i].includes('</head>')) {
                    insertIndex = i;
                    break;
                }
            }
        }
        
        if (insertIndex !== -1) {
            // Determinar o caminho relativo correto para o script
            const depth = (relativePath.match(/\//g) || []).length;
            let scriptPath = '../'.repeat(depth) + 'resumo-annotations.js';
            const scriptLine = `    <script src="${scriptPath}"></script>`;
            
            lines.splice(insertIndex, 0, scriptLine);
            
            const updatedContent = lines.join('\n');
            fs.writeFileSync(filePath, updatedContent, 'utf8');
            console.log(`✓ Atualizado: ${relativePath}`);
        } else {
            console.log(`⚠ Não foi possível atualizar: ${relativePath}`);
        }
        
    } catch (error) {
        console.error(`✗ Erro ao processar ${relativePath}:`, error.message);
    }
}

function processDirectory(dir, baseDir = '') {
    const items = fs.readdirSync(dir);
    
    for (const item of items) {
        const fullPath = path.join(dir, item);
        const relativePath = path.join(baseDir, item);
        const stat = fs.statSync(fullPath);
        
        if (stat.isDirectory()) {
            processDirectory(fullPath, relativePath);
        } else if (item.endsWith('.html')) {
            updateResumoFile(fullPath, relativePath);
        }
    }
}

console.log('🚀 Iniciando integração do sistema de anotações...\n');

if (fs.existsSync(resumosDir)) {
    processDirectory(resumosDir);
    console.log('\n✅ Integração concluída!');
    console.log('\n📝 Sistema de anotações adicionado aos resumos:');
    console.log('   • Marcação de texto com destaque amarelo');
    console.log('   • Anotações personalizadas com referência ao texto');
    console.log('   • Salvamento automático no localStorage');
    console.log('   • Interface intuitiva com toolbar flutuante');
} else {
    console.log('❌ Diretório de resumos não encontrado:', resumosDir);
}
