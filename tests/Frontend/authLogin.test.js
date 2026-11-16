import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import fs from 'fs';
import path from 'path';
import { JSDOM } from 'jsdom';

describe('Auth & Login Frontend Suite (index.php)', () => {
    let dom;
    let window;
    let document;

    const htmlContent = fs.readFileSync(path.resolve(__dirname, '../../index.php'), 'utf8');

    beforeEach(() => {
        // Remove external scripts and links to avoid JSDOM network resource errors
        const cleanedHtml = htmlContent
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (match) => {
                if (match.includes('src=')) return '';
                return match;
            })
            .replace(/<link\b[^>]*rel="stylesheet"[^>]*>/gi, '');

        dom = new JSDOM(cleanedHtml, {
            url: 'http://localhost/index.php',
            runScripts: 'dangerously'
        });
        window = dom.window;
        document = window.document;

        if (!window.fbq) {
            window.fbq = vi.fn();
        }
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('should render homeSection by default and hide loginSection', () => {
        const homeSection = document.getElementById('homeSection');
        const loginSection = document.getElementById('loginSection');

        expect(homeSection).not.toBeNull();
        expect(loginSection).not.toBeNull();
        expect(homeSection.classList.contains('active')).toBe(true);
        // Initially in CSS, .login-section has display: none via stylesheet, or we can check style.display
        expect(loginSection.style.display === 'none' || window.getComputedStyle(loginSection).display === 'none').toBe(true);
    });

    it('should switch between Home and Login sections correctly', () => {
        const homeSection = document.getElementById('homeSection');
        const loginSection = document.getElementById('loginSection');

        expect(typeof window.showLoginSection).toBe('function');
        expect(typeof window.showHomeSection).toBe('function');

        // Call showLoginSection
        window.showLoginSection();
        expect(homeSection.style.display).toBe('none');
        expect(loginSection.style.display).toBe('block');

        // Call showHomeSection
        window.showHomeSection();
        expect(homeSection.style.display).toBe('block');
        expect(loginSection.style.display).toBe('none');
    });

    it('should toggle password visibility correctly', () => {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeOffIcon = document.getElementById('eyeOffIcon');

        expect(typeof window.togglePasswordVisibility).toBe('function');

        expect(passwordInput.type).toBe('password');
        expect(eyeIcon.classList.contains('hidden')).toBe(false);
        expect(eyeOffIcon.classList.contains('hidden')).toBe(true);

        // Click to show password
        window.togglePasswordVisibility();
        expect(passwordInput.type).toBe('text');
        expect(eyeIcon.classList.contains('hidden')).toBe(true);
        expect(eyeOffIcon.classList.contains('hidden')).toBe(false);

        // Click again to hide password
        window.togglePasswordVisibility();
        expect(passwordInput.type).toBe('password');
        expect(eyeIcon.classList.contains('hidden')).toBe(false);
        expect(eyeOffIcon.classList.contains('hidden')).toBe(true);
    });

    it('should have correct login form attributes', () => {
        const loginForm = document.getElementById('loginForm');
        expect(loginForm).not.toBeNull();
        expect(loginForm.getAttribute('action')).toBe('processa_login.php');
        expect(loginForm.getAttribute('method')).toBe('POST');

        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const forgotLink = document.querySelector('a[href="esqueci_senha.php"]');
        const loginBtn = document.getElementById('loginBtn');

        expect(usernameInput.type).toBe('email');
        expect(usernameInput.hasAttribute('required')).toBe(true);

        expect(passwordInput.type).toBe('password');
        expect(passwordInput.hasAttribute('required')).toBe(true);

        expect(forgotLink).not.toBeNull();
        expect(loginBtn).not.toBeNull();
        expect(loginBtn.type).toBe('submit');
    });

    it('should handle URL error parameter: dados_incorretos', async () => {
        const cleanedHtml = htmlContent.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (m) => m.includes('src=') ? '' : m).replace(/<link\b[^>]*rel="stylesheet"[^>]*>/gi, '');
        const errorDom = new JSDOM(cleanedHtml, {
            url: 'http://localhost/index.php?erro=dados_incorretos',
            runScripts: 'dangerously'
        });
        await new Promise(resolve => setTimeout(resolve, 50));
        const errDoc = errorDom.window.document;

        const messageDiv = errDoc.getElementById('message');
        expect(messageDiv.classList.contains('hidden')).toBe(false);
        expect(messageDiv.textContent).toContain('E-mail ou senha incorretos.');
    });

    it('should handle URL error parameter: assinatura_vencida', async () => {
        const cleanedHtml = htmlContent.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (m) => m.includes('src=') ? '' : m).replace(/<link\b[^>]*rel="stylesheet"[^>]*>/gi, '');
        const errorDom = new JSDOM(cleanedHtml, {
            url: 'http://localhost/index.php?erro=assinatura_vencida',
            runScripts: 'dangerously'
        });
        await new Promise(resolve => setTimeout(resolve, 50));
        const errDoc = errorDom.window.document;

        const messageDiv = errDoc.getElementById('message');
        expect(messageDiv.classList.contains('hidden')).toBe(false);
        expect(messageDiv.textContent).toContain('Sua assinatura expirou');
    });

    it('should handle URL error parameter: conta_pendente', async () => {
        const cleanedHtml = htmlContent.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (m) => m.includes('src=') ? '' : m).replace(/<link\b[^>]*rel="stylesheet"[^>]*>/gi, '');
        const errorDom = new JSDOM(cleanedHtml, {
            url: 'http://localhost/index.php?erro=conta_pendente',
            runScripts: 'dangerously'
        });
        await new Promise(resolve => setTimeout(resolve, 50));
        const errDoc = errorDom.window.document;

        const messageDiv = errDoc.getElementById('message');
        expect(messageDiv.classList.contains('hidden')).toBe(false);
        expect(messageDiv.textContent).toContain('Pagamento em análise');
    });

    it('should handle URL error parameter: acesso_negado_escola', async () => {
        const cleanedHtml = htmlContent.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (m) => m.includes('src=') ? '' : m).replace(/<link\b[^>]*rel="stylesheet"[^>]*>/gi, '');
        const errorDom = new JSDOM(cleanedHtml, {
            url: 'http://localhost/index.php?erro=acesso_negado_escola',
            runScripts: 'dangerously'
        });
        await new Promise(resolve => setTimeout(resolve, 50));
        const errDoc = errorDom.window.document;

        const messageDiv = errDoc.getElementById('message');
        expect(messageDiv.classList.contains('hidden')).toBe(false);
        expect(messageDiv.textContent).toContain('Acesso negado');
    });

    it('should handle generic unknown error parameter', async () => {
        const cleanedHtml = htmlContent.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, (m) => m.includes('src=') ? '' : m).replace(/<link\b[^>]*rel="stylesheet"[^>]*>/gi, '');
        const errorDom = new JSDOM(cleanedHtml, {
            url: 'http://localhost/index.php?erro=unknown_error_code',
            runScripts: 'dangerously'
        });
        await new Promise(resolve => setTimeout(resolve, 50));
        const errDoc = errorDom.window.document;

        const messageDiv = errDoc.getElementById('message');
        expect(messageDiv.classList.contains('hidden')).toBe(false);
        expect(messageDiv.textContent).toContain('Ocorreu um erro no login.');
    });
});
