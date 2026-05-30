# Kado-to API

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat-square&logo=mysql&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-4F5D95?style=flat-square)
![Tests](https://img.shields.io/badge/tests-Pest-7A1FA2?style=flat-square)

Backend do [Kado](https://kado-tan.vercel.app/dashboard) — um sistema de controle financeiro pessoal para registrar lançamentos, organizar gastos e acompanhar faturas de cartão de crédito.

---

## Sobre o projeto

O Kado nasceu de uma necessidade simples: ter clareza sobre pra onde o dinheiro vai. Mais do que um CRUD de lançamentos, o sistema centraliza receitas, despesas, fontes financeiras e cartões num único lugar — com um dashboard que transforma esses dados em uma visão útil do mês.

Este repositório é a API que sustenta toda essa lógica. O frontend que a consome está disponível em [kado-tan.vercel.app](https://kado-tan.vercel.app/dashboard).

---

## Arquitetura

A API foi construída com foco em separação de responsabilidades e manutenibilidade. A estrutura evita que controllers concentrem lógica de negócio, distribuindo as responsabilidades da seguinte forma:

- **Controllers** — recebem a requisição HTTP, validam a entrada e delegam
- **Actions** — executam os casos de uso; cada action tem uma responsabilidade única
- **DTOs** — transportam dados entre camadas de forma tipada e previsível

Essa organização facilita testes unitários, torna o fluxo de cada funcionalidade rastreável e reduz o acoplamento entre partes do sistema.

### Módulos principais

| Módulo | Responsabilidade |
|---|---|
| `Auth` | Cadastro, login e autenticação via Sanctum |
| `Expense` | Receitas, despesas, pagamento, importação e exportação |
| `Category` | Categorias personalizadas por usuário |
| `Source` | Fontes financeiras (conta, carteira, cartão) |
| `CreditCard` | Faturas, parcelamentos, pagamento e estorno |
| `Dashboard` | Resumos financeiros e cards configuráveis |

---

## Stack

**Runtime:** PHP 8.4+ · Laravel 13 · MySQL 8

**Auth:** Laravel Sanctum (token-based)

**Qualidade:** Pest · PHPStan nível 10 · Rector · Laravel Pint

**Infra local:** Laravel Sail (Docker)

---

## Rodando localmente

**Pré-requisitos:** Docker, Docker Compose e Composer

```bash
composer install
./vendor/bin/sail up -d
./vendor/bin/sail composer setup   # cria .env, gera APP_KEY e roda migrations
./vendor/bin/sail composer dev
```

A API ficará disponível em `http://localhost`.

```bash
./vendor/bin/sail composer test           # testes com Pest
./vendor/bin/sail composer lint:all:check # PHPStan + Pint
./vendor/bin/sail composer lint:fix       # corrige automaticamente
./vendor/bin/sail shell                   # acessa o container
```

## Licença e uso

Este projeto é público para fins de transparência, demonstração técnica e colaboração.

O código-fonte é de direito restrito. Você pode visualizar o repositório, estudar a estrutura do projeto e propor melhorias por meio de issues ou pull requests.

No entanto, não é permitido copiar, redistribuir, vender, republicar, usar comercialmente ou criar produtos derivados a partir deste código sem autorização prévia do autor.

Pull requests são bem-vindos, desde que estejam alinhados com o objetivo do projeto. Ao contribuir, você concorda que sua contribuição poderá ser incorporada ao projeto sob os mesmos termos de uso deste repositório.
