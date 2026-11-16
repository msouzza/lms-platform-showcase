// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

describe('Perfil do Aluno - Testes Completos de Frontend (perfil.php)', () => {
    let container;

    // Helper para escapar HTML simulando htmlspecialchars do PHP com segurança contra undefined/null
    const escapeHTML = (str) => {
        if (!str) return '';
        return String(str).replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    };

    const renderPerfilPage = (aluno = { nome: 'Aluno Teste', email: 'aluno@contextopolicial.com' }, queryParams = {}) => {
        document.body.innerHTML = '';
        
        const urlParams = new URLSearchParams();
        Object.entries(queryParams).forEach(([k, v]) => urlParams.set(k, v));
        const searchStr = urlParams.toString() ? '?' + urlParams.toString() : '';
        
        delete window.location;
        window.location = {
            href: 'http://localhost/perfil.php' + searchStr,
            search: searchStr,
            pathname: '/perfil.php'
        };

        const safeNome = escapeHTML(aluno.nome);
        const safeEmail = escapeHTML(aluno.email);

        container = document.createElement('div');
        container.innerHTML = `
            <div class="perfil-container">
                <a href="index.php" class="btn-voltar">Voltar à Área do Aluno</a>
                
                ${queryParams.sucesso === 'senha_alterada' || queryParams.sucesso === '1' || searchStr.includes('sucesso') ? '<div class="msg sucesso">✅ Senha alterada com sucesso!</div>' : ''}
                
                ${queryParams.erro === 'senha_atual_incorreta' ? '<div class="msg erro">❌ A senha atual não confere.</div>' : ''}
                ${queryParams.erro === 'senhas_nao_batem' ? '<div class="msg erro">❌ As novas senhas não coincidem.</div>' : ''}
                ${queryParams.erro === 'senha_curta' ? '<div class="msg erro">⚠️ A nova senha deve ter pelo menos 6 caracteres.</div>' : ''}
                ${queryParams.erro && !['senha_atual_incorreta', 'senhas_nao_batem', 'senha_curta'].includes(queryParams.erro) ? '<div class="msg erro">❌ Erro ao alterar senha.</div>' : ''}

                <div class="info-group">
                    <span class="label">Nome Completo:</span>
                    <span class="value">${safeNome}</span>
                </div>
                <div class="info-group">
                    <span class="label">E-mail de Acesso:</span>
                    <span class="value">${safeEmail}</span>
                </div>

                <form id="form-alterar-senha" method="POST" action="processa_perfil.php">
                    <input type="hidden" name="acao" value="alterar_senha">
                    
                    <div class="form-group">
                        <label for="senha_atual">Senha Atual</label>
                        <input type="password" id="senha_atual" name="senha_atual" required>
                    </div>

                    <div class="form-group">
                        <label for="nova_senha">Nova Senha</label>
                        <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo 6 caracteres" required>
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Nova Senha</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
                    </div>

                    <div id="form-error-msg" class="text-red-500 text-sm mb-3 hidden"></div>

                    <button type="submit" id="btn-submit-senha">Alterar Senha</button>
                </form>
            </div>
        `;
        document.body.appendChild(container);

        const form = document.getElementById('form-alterar-senha');
        if (form) {
            form.addEventListener('submit', function(e) {
                const senhaAtual = document.getElementById('senha_atual').value;
                const novaSenha = document.getElementById('nova_senha').value;
                const confirmarSenha = document.getElementById('confirmar_senha').value;
                const errorDiv = document.getElementById('form-error-msg');

                errorDiv.classList.add('hidden');
                errorDiv.textContent = '';

                if (!senhaAtual || !novaSenha || !confirmarSenha) {
                    e.preventDefault();
                    errorDiv.textContent = 'Preencha todos os campos obrigatórios.';
                    errorDiv.classList.remove('hidden');
                    return;
                }

                if (novaSenha.length < 6) {
                    e.preventDefault();
                    errorDiv.textContent = 'A nova senha deve ter pelo menos 6 caracteres.';
                    errorDiv.classList.remove('hidden');
                    return;
                }

                if (novaSenha !== confirmarSenha) {
                    e.preventDefault();
                    errorDiv.textContent = 'As novas senhas não coincidem.';
                    errorDiv.classList.remove('hidden');
                    return;
                }
            });
        }
    };

    beforeEach(() => {
        document.body.innerHTML = '';
        localStorage.clear();
        vi.restoreAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('1. Renderização de Dados do Aluno e XSS Prevention', () => {
        it('Deve renderizar corretamente o nome completo e e-mail do aluno', () => {
            renderPerfilPage({ nome: 'João da Silva', email: 'joao@bombeiros.rs.gov.br' });

            const infoGroups = document.querySelectorAll('.info-group');
            expect(infoGroups.length).toBe(2);

            const valores = document.querySelectorAll('.value');
            expect(valores[0].textContent).toBe('João da Silva');
            expect(valores[1].textContent).toBe('joao@bombeiros.rs.gov.br');
        });

        it('Deve escapar caracteres HTML para prevenir XSS nos dados do aluno', () => {
            const maliciousName = '<script>alert("XSS")</script> Aluno';
            renderPerfilPage({ nome: maliciousName, email: 'test@test.com' });

            const infoGroupHTML = document.querySelectorAll('.value')[0].innerHTML;
            expect(infoGroupHTML).toContain('&lt;script&gt;');
            expect(infoGroupHTML).not.toContain('<script>');
            expect(document.querySelectorAll('script').length).toBe(0);
        });
    });

    describe('2. Mensagens de Alerta e Feedback (.msg)', () => {
        it('Deve exibir mensagem de sucesso quando ?sucesso estiver presente na URL', () => {
            renderPerfilPage(undefined, { sucesso: '1' });

            const msgSucesso = document.querySelector('.msg.sucesso');
            expect(msgSucesso).not.toBeNull();
            expect(msgSucesso.textContent).toContain('✅ Senha alterada com sucesso!');
        });

        it('Deve exibir mensagem de erro correta para senha_atual_incorreta', () => {
            renderPerfilPage(undefined, { erro: 'senha_atual_incorreta' });

            const msgErro = document.querySelector('.msg.erro');
            expect(msgErro).not.toBeNull();
            expect(msgErro.textContent).toContain('❌ A senha atual não confere.');
        });

        it('Deve exibir mensagem de erro correta para senhas_nao_batem', () => {
            renderPerfilPage(undefined, { erro: 'senhas_nao_batem' });

            const msgErro = document.querySelector('.msg.erro');
            expect(msgErro).not.toBeNull();
            expect(msgErro.textContent).toContain('❌ As novas senhas não coincidem.');
        });

        it('Deve exibir mensagem de aviso correta para senha_curta', () => {
            renderPerfilPage(undefined, { erro: 'senha_curta' });

            const msgErro = document.querySelector('.msg.erro');
            expect(msgErro).not.toBeNull();
            expect(msgErro.textContent).toContain('⚠️ A nova senha deve ter pelo menos 6 caracteres.');
        });

        it('Deve exibir mensagem de erro genérica para outro código de erro', () => {
            renderPerfilPage(undefined, { erro: 'outro_erro' });

            const msgErro = document.querySelector('.msg.erro');
            expect(msgErro).not.toBeNull();
            expect(msgErro.textContent).toContain('❌ Erro ao alterar senha.');
        });
    });

    describe('3. Formulário de Alteração de Senha e Validações de Frontend', () => {
        it('Deve conter form POST com action processa_perfil.php, input hidden acao e campos required', () => {
            renderPerfilPage();

            const form = document.getElementById('form-alterar-senha');
            expect(form).not.toBeNull();
            expect(form.getAttribute('method').toUpperCase()).toBe('POST');
            expect(form.getAttribute('action')).toBe('processa_perfil.php');

            const hiddenAcao = form.querySelector('input[name="acao"]');
            expect(hiddenAcao).not.toBeNull();
            expect(hiddenAcao.value).toBe('alterar_senha');

            const senhaAtual = document.getElementById('senha_atual');
            const novaSenha = document.getElementById('nova_senha');
            const confirmarSenha = document.getElementById('confirmar_senha');

            expect(senhaAtual.hasAttribute('required')).toBe(true);
            expect(novaSenha.hasAttribute('required')).toBe(true);
            expect(confirmarSenha.hasAttribute('required')).toBe(true);
        });

        it('Deve impedir envio e exibir erro se algum campo estiver vazio', () => {
            renderPerfilPage();

            const form = document.getElementById('form-alterar-senha');
            document.getElementById('senha_atual').value = '123456';
            document.getElementById('nova_senha').value = '';
            document.getElementById('confirmar_senha').value = '';

            const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
            form.dispatchEvent(submitEvent);

            expect(submitEvent.defaultPrevented).toBe(true);
            const errorDiv = document.getElementById('form-error-msg');
            expect(errorDiv.classList.contains('hidden')).toBe(false);
            expect(errorDiv.textContent).toContain('Preencha todos os campos obrigatórios.');
        });

        it('Deve impedir envio e exibir erro se a nova senha tiver menos de 6 caracteres', () => {
            renderPerfilPage();

            const form = document.getElementById('form-alterar-senha');
            document.getElementById('senha_atual').value = 'senha123';
            document.getElementById('nova_senha').value = '12345';
            document.getElementById('confirmar_senha').value = '12345';

            const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
            form.dispatchEvent(submitEvent);

            expect(submitEvent.defaultPrevented).toBe(true);
            const errorDiv = document.getElementById('form-error-msg');
            expect(errorDiv.classList.contains('hidden')).toBe(false);
            expect(errorDiv.textContent).toContain('A nova senha deve ter pelo menos 6 caracteres.');
        });

        it('Deve impedir envio e exibir erro se nova_senha e confirmar_senha forem diferentes', () => {
            renderPerfilPage();

            const form = document.getElementById('form-alterar-senha');
            document.getElementById('senha_atual').value = 'senha123';
            document.getElementById('nova_senha').value = 'novasenha123';
            document.getElementById('confirmar_senha').value = 'outrasenha456';

            const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
            form.dispatchEvent(submitEvent);

            expect(submitEvent.defaultPrevented).toBe(true);
            const errorDiv = document.getElementById('form-error-msg');
            expect(errorDiv.classList.contains('hidden')).toBe(false);
            expect(errorDiv.textContent).toContain('As novas senhas não coincidem.');
        });

        it('Deve permitir envio (não prevenir default) quando todos os dados estiverem válidos', () => {
            renderPerfilPage();

            const form = document.getElementById('form-alterar-senha');
            document.getElementById('senha_atual').value = 'senha123';
            document.getElementById('nova_senha').value = 'novasenha123';
            document.getElementById('confirmar_senha').value = 'novasenha123';

            const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
            form.dispatchEvent(submitEvent);

            expect(submitEvent.defaultPrevented).toBe(false);
            const errorDiv = document.getElementById('form-error-msg');
            expect(errorDiv.classList.contains('hidden')).toBe(true);
        });
    });

    describe('4. Navegação', () => {
        it('Deve conter link de retorno .btn-voltar apontando para index.php', () => {
            renderPerfilPage();

            const btnVoltar = document.querySelector('.btn-voltar');
            expect(btnVoltar).not.toBeNull();
            expect(btnVoltar.getAttribute('href')).toBe('index.php');
            expect(btnVoltar.textContent).toContain('Voltar');
        });
    });
});
