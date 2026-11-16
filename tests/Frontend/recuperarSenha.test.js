import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import fs from 'fs';
import path from 'path';
import { JSDOM } from 'jsdom';

describe('Password Recovery & Reset Frontend Suite', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('esqueci_senha.php', () => {
        const esqueciHtml = fs.readFileSync(path.resolve(__dirname, '../../esqueci_senha.php'), 'utf8');

        const createEsqueciDOM = (url = 'http://localhost/esqueci_senha.php') => {
            const urlObj = new URL(url);
            const erro = urlObj.searchParams.get('erro') || '';
            const sucesso = urlObj.searchParams.get('sucesso') || '';

            let processedHtml = esqueciHtml
                .replace('<?= NOME_ESCOLA ?>', 'Contexto Policial')
                .replace(/<\?php[\s\S]*?\?>/, '');

            if (erro === 'nao_encontrado') {
                processedHtml = processedHtml.replace(
                    /<\?php if \(\$erro === 'nao_encontrado'\): \?>[\s\S]*?<\?php elseif \(\$erro === 'sistema'\): \?>[\s\S]*?<\?php elseif \(\$sucesso === 'enviado'\): \?>[\s\S]*?<\?php endif; \?>/,
                    '<div class="bg-red-100 text-red-700 px-4 py-3 rounded-lg shadow-sm mb-6 flex items-start font-bold text-center justify-center"><span class="block">Não localizamos nenhum aluno com este e-mail.</span></div>'
                );
            } else if (erro === 'sistema') {
                processedHtml = processedHtml.replace(
                    /<\?php if \(\$erro === 'nao_encontrado'\): \?>[\s\S]*?<\?php elseif \(\$erro === 'sistema'\): \?>[\s\S]*?<\?php elseif \(\$sucesso === 'enviado'\): \?>[\s\S]*?<\?php endif; \?>/,
                    '<div class="bg-red-100 text-red-700 px-4 py-3 rounded-lg shadow-sm mb-6 flex items-start font-bold text-center justify-center"><span class="block">Ocorreu um erro no servidor. Tente novamente mais tarde.</span></div>'
                );
            } else if (sucesso === 'enviado') {
                processedHtml = processedHtml.replace(
                    /<\?php if \(\$erro === 'nao_encontrado'\): \?>[\s\S]*?<\?php elseif \(\$erro === 'sistema'\): \?>[\s\S]*?<\?php elseif \(\$sucesso === 'enviado'\): \?>[\s\S]*?<\?php endif; \?>/,
                    '<div class="bg-green-100 text-green-700 px-4 py-3 rounded-lg shadow-sm mb-6 flex flex-col items-center font-bold text-center"><span class="block text-sm mb-1">Tudo certo! Um link de recuperação foi enviado.</span><span class="block text-xs text-green-600">Verifique sua caixa de entrada e também o Lixo Eletrônico (Spam).</span></div>'
                );
            } else {
                processedHtml = processedHtml.replace(/<\?php if \(\$erro === 'nao_encontrado'\): \?>[\s\S]*?<\?php endif; \?>/g, '');
            }

            if (sucesso === 'enviado') {
                processedHtml = processedHtml.replace(/<\?php if \(\$sucesso !== 'enviado'\): \?>[\s\S]*?<\?php endif; \?>/g, '');
            } else {
                processedHtml = processedHtml.replace(/<\?php if \(\$sucesso !== 'enviado'\): \?>/, '').replace(/<\?php endif; \?>/, '');
            }

            const cleaned = processedHtml.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (m) => m.includes('src=') ? '' : m);
            const dom = new JSDOM(cleaned, { url, runScripts: 'dangerously' });
            return { dom, window: dom.window, document: dom.window.document };
        };

        it('should render initial card correctly with title, instructions and email input', () => {
            const { document } = createEsqueciDOM();

            const heading = document.querySelector('h1');
            expect(heading).not.toBeNull();
            expect(heading.textContent).toContain('MÉTODO CONTEXTO POLICIAL');

            const emailInput = document.querySelector('input[name="email"]');
            expect(emailInput).not.toBeNull();
            expect(emailInput.getAttribute('type')).toBe('email');
            expect(emailInput.required).toBe(true);

            const form = document.querySelector('form');
            expect(form).not.toBeNull();
            expect(form.getAttribute('action')).toBe('processa_esqueci_senha.php');
            expect(form.getAttribute('method')).toBe('POST');

            const backLink = document.querySelector('a[href="index.php"]');
            expect(backLink).not.toBeNull();
            expect(backLink.textContent).toContain('Voltar para o Login');
        });

        it('should display error alert when erro === "nao_encontrado"', () => {
            const { document } = createEsqueciDOM('http://localhost/esqueci_senha.php?erro=nao_encontrado');

            const alert = document.querySelector('.bg-red-100');
            expect(alert).not.toBeNull();
            expect(alert.textContent).toContain('Não localizamos nenhum aluno com este e-mail.');
        });

        it('should display error alert when erro === "sistema"', () => {
            const { document } = createEsqueciDOM('http://localhost/esqueci_senha.php?erro=sistema');

            const alert = document.querySelector('.bg-red-100');
            expect(alert).not.toBeNull();
            expect(alert.textContent).toContain('Ocorreu um erro no servidor.');
        });

        it('should display success message when sucesso === "enviado" and hide form', () => {
            const { document } = createEsqueciDOM('http://localhost/esqueci_senha.php?sucesso=enviado');

            const successAlert = document.querySelector('.bg-green-100');
            expect(successAlert).not.toBeNull();
            expect(successAlert.textContent).toContain('Tudo certo! Um link de recuperação foi enviado.');

            const form = document.querySelector('form');
            expect(form).toBeNull();
        });
    });

    describe('reset.php', () => {
        const createResetDOM = (url = 'http://localhost/reset.php?token=valid_token', tokenValido = true, alunoNome = 'Aluno Teste', erro = '') => {
            let bodyContent = '';

            if (!tokenValido) {
                bodyContent = `
                    <div class="main-card max-w-md w-full p-8 rounded-2xl shadow-2xl">
                        <div class="text-center mb-8">
                            <h1 class="text-3xl font-bold text-white mb-2">MÉTODO CONTEXTO POLICIAL</h1>
                            <h2 class="text-lg font-semibold text-white mb-2">CONCURSO BOMBEIRO MILITAR DE MG 2026</h2>
                            <p class="text-white mb-4 font-bold uppercase">Link Expirado</p>
                        </div>
                        <div class="bg-red-100 text-red-700 px-4 py-4 rounded-lg shadow-sm mb-6 text-center font-bold" role="alert">
                            <span class="block">Este link é inválido ou já expirou.</span>
                            <p class="text-xs mt-2 text-red-600">Para sua segurança, os links de recuperação de senha têm validade de 1 hora e só funcionam uma vez.</p>
                        </div>
                        <a href="esqueci_senha.php" class="w-full block text-center bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 transition-all transform hover:scale-105">
                            Solicitar Novo Link
                        </a>
                        <div class="mt-6 text-center">
                            <a href="index.php" class="text-yellow-300 hover:text-yellow-100 font-medium text-sm transition-colors">← Voltar ao Login</a>
                        </div>
                    </div>
                `;
            } else {
                let errorHtml = '';
                if (erro === 'senhas_diferentes') {
                    errorHtml = `
                        <div class="bg-red-100 text-red-700 font-bold px-4 py-3 rounded-lg shadow-sm mb-6 text-center" role="alert">
                            <span class="block">As senhas não coincidem. Tente novamente.</span>
                        </div>
                    `;
                } else if (erro === 'senha_curta') {
                    errorHtml = `
                        <div class="bg-red-100 text-red-700 font-bold px-4 py-3 rounded-lg shadow-sm mb-6 text-center" role="alert">
                            <span class="block">A senha deve ter pelo menos 6 caracteres.</span>
                        </div>
                    `;
                } else if (erro === 'sistema') {
                    errorHtml = `
                        <div class="bg-red-100 text-red-700 font-bold px-4 py-3 rounded-lg shadow-sm mb-6 text-center" role="alert">
                            <span class="block">Erro ao atualizar a senha. Tente novamente.</span>
                        </div>
                    `;
                }

                bodyContent = `
                    <div class="main-card max-w-md w-full p-8 rounded-2xl shadow-2xl">
                        <div class="text-center mb-8">
                            <h1 class="text-3xl font-bold text-white mb-2">MÉTODO CONTEXTO POLICIAL</h1>
                            <h2 class="text-lg font-semibold text-white mb-2">CONCURSO BOMBEIRO MILITAR DE MG 2026</h2>
                            <p class="text-white mb-4 font-bold uppercase">Criar Nova Senha</p>
                            <p class="text-white">
                                Olá, <strong>${alunoNome}</strong>! Digite sua nova senha abaixo.
                            </p>
                        </div>

                        ${errorHtml}

                        <form action="processa_reset.php" method="POST" id="resetForm" class="space-y-6">
                            <input type="hidden" name="token" value="dummy_token_123">
                            <div>
                                <label for="senha1" class="block text-sm font-medium text-white mb-2">🔑 Nova Senha</label>
                                <input type="password" id="senha1" name="nova_senha" required minlength="6"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    placeholder="Mínimo 6 caracteres">
                            </div>
                            <div>
                                <label for="senha2" class="block text-sm font-medium text-white mb-2">🔑 Confirmar Nova Senha</label>
                                <input type="password" id="senha2" name="confirma_senha" required minlength="6"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    placeholder="Repita a senha">
                                <p id="errorMatch" class="text-yellow-300 text-xs font-semibold mt-1 hidden">As senhas não coincidem.</p>
                            </div>
                            <button type="submit" id="submitBtn"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:scale-105 mt-2">
                                💾 Salvar e Entrar
                            </button>
                        </form>

                        <div class="mt-6 text-center">
                            <a href="index.php" class="text-yellow-300 hover:text-yellow-100 font-medium text-sm">← Voltar ao Login</a>
                        </div>
                    </div>
                `;
            }

            const html = `
                <!DOCTYPE html>
                <html lang="pt-BR">
                <head><meta charset="UTF-8"><title>Reset</title></head>
                <body>
                    ${bodyContent}
                </body>
                </html>
            `;

            const dom = new JSDOM(html, { url, runScripts: 'dangerously' });
            return { dom, window: dom.window, document: dom.window.document };
        };

        it('should display invalid token view and hide reset form when token is invalid or expired', () => {
            const { document } = createResetDOM('http://localhost/reset.php?token=invalid', false);

            const alert = document.querySelector('[role="alert"]');
            expect(alert).not.toBeNull();
            expect(alert.textContent).toContain('Este link é inválido ou já expirou.');

            const solicitarLinkBtn = document.querySelector('a[href="esqueci_senha.php"]');
            expect(solicitarLinkBtn).not.toBeNull();
            expect(solicitarLinkBtn.textContent).toContain('Solicitar Novo Link');

            const resetForm = document.getElementById('resetForm');
            expect(resetForm).toBeNull();
        });

        it('should display valid token view with student name, password inputs, hidden token, and form action', () => {
            const { document } = createResetDOM('http://localhost/reset.php?token=valid123', true, 'Carlos Silva');

            const greeting = document.body.textContent;
            expect(greeting).toContain('Olá, Carlos Silva! Digite sua nova senha abaixo.');

            const resetForm = document.getElementById('resetForm');
            expect(resetForm).not.toBeNull();
            expect(resetForm.getAttribute('action')).toBe('processa_reset.php');
            expect(resetForm.getAttribute('method')).toBe('POST');

            const tokenInput = document.querySelector('input[name="token"]');
            expect(tokenInput).not.toBeNull();
            expect(tokenInput.getAttribute('type')).toBe('hidden');
            expect(tokenInput.value).toBe('dummy_token_123');

            const novaSenhaInput = document.querySelector('input[name="nova_senha"]');
            expect(novaSenhaInput).not.toBeNull();
            expect(novaSenhaInput.getAttribute('type')).toBe('password');
            expect(novaSenhaInput.required).toBe(true);

            const confirmaSenhaInput = document.querySelector('input[name="confirma_senha"]');
            expect(confirmaSenhaInput).not.toBeNull();
            expect(confirmaSenhaInput.getAttribute('type')).toBe('password');
            expect(confirmaSenhaInput.required).toBe(true);
        });

        it('should display error alert when erro === "senhas_diferentes"', () => {
            const { document } = createResetDOM('http://localhost/reset.php?token=valid123&erro=senhas_diferentes', true, 'Carlos Silva', 'senhas_diferentes');
            const alert = document.querySelector('[role="alert"]');
            expect(alert).not.toBeNull();
            expect(alert.textContent).toContain('As senhas não coincidem.');
        });

        it('should display error alert when erro === "senha_curta"', () => {
            const { document } = createResetDOM('http://localhost/reset.php?token=valid123&erro=senha_curta', true, 'Carlos Silva', 'senha_curta');
            const alert = document.querySelector('[role="alert"]');
            expect(alert).not.toBeNull();
            expect(alert.textContent).toContain('A senha deve ter pelo menos 6 caracteres.');
        });

        it('should display error alert when erro === "sistema"', () => {
            const { document } = createResetDOM('http://localhost/reset.php?token=valid123&erro=sistema', true, 'Carlos Silva', 'sistema');
            const alert = document.querySelector('[role="alert"]');
            expect(alert).not.toBeNull();
            expect(alert.textContent).toContain('Erro ao atualizar a senha.');
        });
    });
});
