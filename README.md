# LMS Platform Showcase

[![CI - LMS Platform Showcase](https://github.com/msouzza/lms-platform-showcase/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/msouzza/lms-platform-showcase/actions/workflows/ci-cd.yml)

Full-stack **Learning Management System (LMS)** architecture built with **PHP 8.2**, **Vanilla JavaScript**, and **MySQL 8.0**, engineered with a comprehensive automated testing pyramid, **OpenAPI 3.0.3 (Swagger)** API documentation, **AppSec baseline checks**, and **GitHub Actions CI**.

> **Portfolio & Sanitization Notice:** This public repository is a sanitized architectural showcase of a production LMS platform. Proprietary course materials, PDFs, production credentials, and deployment secrets have been removed and replaced with minimal sample fixtures so all automated tests and CI pipelines remain reproducible.

---

## Key Engineering Highlights

- **OpenAPI 3.0.3 & Interactive Swagger UI (`docs/api/`):**
  - Documents core operational endpoints (`POST /api/salvar_simulado.php`, `POST /api/salvar_quiz.php`, `POST /api_progresso.php`, `GET/POST /api/api_anotacoes.php`, and `POST /api/api_taf.php`).
  - Zero-dependency interactive dashboard at `docs/api/index.html` with `PHPSESSID` session authentication support.
  - Automated **Swagger Contract Test** (`tests/Integration/SwaggerContractTest.php`) validating OpenAPI JSON schema integrity, endpoint file existence, and security response definitions.
- **Security-First Backend (AppSec Baseline):**
  - Server-side score recalculation against official answer keys to prevent client-side tampering.
  - Protection against **IDOR**, **Path Traversal / LFI** (`components/` containment), **Host Header Injection**, and unauthenticated content leaks.
  - Dedicated `SecurityBaselineTest.php` suite validating security controls on every push.
- **Full-Stack Automated Testing Pyramid:**
  - **Frontend (Vitest + JSDOM):** 14 test suites covering dashboard flows, spaced-repetition study planner (`orgEstudos`), interactive annotations, flashcards, mock exams, and physical fitness (TAF) scoring.
  - **Backend Unit & Regression (PHPUnit):** Service unit tests, database fixtures, multi-tenant school isolation, and authentication/redirect flows.
  - **Syntax & Hygiene (`tests/ci/lint.php`):** Automated `php -l` syntax verification across all PHP files.

---

## Project Structure

```text
├── api/                         # REST API endpoints (exams, quizzes, annotations, TAF)
├── components/                  # Sanitized sample learning content & annotation engine
├── docs/api/                    # OpenAPI 3.0.3 spec (openapi.json) & Swagger UI (index.html)
├── src/                         # Modular backend services (TAF, scoring, etc.)
├── teste_fisico/                # Physical fitness (TAF) evaluation module
├── tests/
│   ├── Frontend/                # 14 Vitest + JSDOM test suites
│   ├── Unit/                    # PHPUnit pure unit tests
│   ├── Integration/             # Regression & SwaggerContract tests
│   ├── Security/                # AppSec baseline tests (IDOR, LFI, anti-fraud)
│   ├── Support/                 # Isolated HTTP test client with session cookies
│   ├── fixtures/                # Test SQL schema
│   ├── ci/                      # PHP linter & CI bootstrap scripts
│   └── run_all.js               # Unified cross-platform test orchestrator
└── .github/workflows/ci-cd.yml  # GitHub Actions CI pipeline
```

---

## Running Locally

### Prerequisites
- **PHP 8.2+** (with `pdo_mysql`, `curl`, `mbstring`, `json`)
- **Node.js 20+** and **npm**
- **Composer**
- **MySQL 8.0** (for full integration/regression tests)

### 1. Install Dependencies
```bash
npm install
composer install
```

### 2. Run Automated Tests
```bash
# Run PHP syntax lint
php tests/ci/lint.php

# Run Frontend tests (Vitest)
npx vitest run

# Run OpenAPI / Swagger Contract validation
npm run test:swagger

# Run full unified test suite
npm test
```

### 3. Explore Swagger API Docs
Serve the project with Apache (XAMPP) or PHP's built-in server:
```bash
php -S 127.0.0.1:8089
```
Then open `http://127.0.0.1:8089/docs/api/` in your browser.

---

## Copyright & License

**Copyright © 2026 Mateus Souza. All Rights Reserved.**

This repository is published strictly as a **technical portfolio showcase** for inspection and evaluation. No open-source license is granted; copying, modifying, redistributing, or using this codebase commercially without explicit written permission from the author is prohibited.
