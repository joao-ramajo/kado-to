# 💰 Fillament Wallet API

![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-API%20Auth-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-Tests-7A1FA2?style=for-the-badge&logo=php&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%2010-4F5D95?style=for-the-badge&logo=php&logoColor=white)
![Rector](https://img.shields.io/badge/Rector-Refactoring-6E4AFF?style=for-the-badge&logo=php&logoColor=white)

Kado-to é a API central para o projeto Kado, sendo seu backend responsável por lidar com as operações de controle de gastos, é uma 


## Funcionalidades Principais

O sistema permite cadastro e autenticação de usuários com emissão de token, criação automática de uma fonte principal de saldo e organização de lançamentos financeiros por categoria e origem.

Entre as funcionalidades disponíveis estão:

- Cadastro e login de usuários com autenticação via token usando `Laravel Sanctum`
- CRUD de despesas e receitas
- Marcação manual de despesas como pagas
- Cadastro e edição de categorias personalizadas com cor
- Gestão de fontes financeiras, como saldo principal, carteiras e cartões de crédito
- Dashboard com resumo financeiro, totais consolidados e cards configuráveis por usuário
- Listagem de despesas com filtros por status, categoria, mês e busca textual
- Importação de despesas por arquivo CSV
- Exportação de dados em CSV e XLSX
- Geração e pagamento de faturas de cartão de crédito, incluindo compras parceladas

## 🧱 Arquitetura

Este backend segue um padrão baseado em `Actions`, com controllers de entrada em single action controller, DTOs de input e output e regras de negócio centralizadas nas actions.

Módulos principais da API:

- `Auth`: registro e login
- `Dashboard`: resumo financeiro, cards e listagem de despesas
- `Expense`: criação, edição, exclusão, baixa e importação
- `Category`: cadastro e manutenção de categorias
- `Source`: gerenciamento de contas, carteiras e cartões
- `CreditCard`: pagamento e estorno de faturas

## 🚀 Como Executar

### Pré-requisitos

- Docker
- Docker Compose
- Composer

### Instalação

1. Clone o repositório e entre na pasta do backend.
2. Instale as dependências PHP:

```bash
composer install
```

1. Suba os containers:

```bash
./vendor/bin/sail up -d
```

1. Configure a aplicação:

```bash
./vendor/bin/sail composer setup
```

1. Inicie o backend:

```bash
./vendor/bin/sail composer dev
```

1. A API ficará disponível em:

```text
http://localhost
```

> O script `setup` cria o `.env` quando necessário, gera a `APP_KEY` e executa as migrations.

## 🧪 Comandos Úteis

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail composer test
./vendor/bin/sail composer lint:all:check
./vendor/bin/sail composer lint:fix
./vendor/bin/sail shell
```

## 📌 Endpoints e Fluxos

Principais rotas expostas pela API:

- `POST /api/register`
- `POST /api/login`
- `GET /api/dashboard/summary`
- `GET /api/dashboard/expenses`
- `GET /api/dashboard/spreadsheet/csv/export`
- `GET /api/dashboard/spreadsheet/xlsx/export`
- `POST /api/dashboard/spreadsheet/csv/import`
- `POST /api/expenses`
- `PUT /api/expenses/{id}`
- `DELETE /api/expenses/{id}`
- `POST /api/expenses/{id}/mark-as-paid`
- `GET /api/categories`
- `POST /api/categories`
- `PUT /api/categories/{id}`
- `GET /api/users/sources`
- `GET /api/sources`
- `POST /api/sources`
- `PUT /api/sources/{id}`
- `DELETE /api/sources/{id}`
- `POST /api/credit-cards/statements/{statementId}/pay`
- `POST /api/credit-cards/statements/{statementId}/undo-pay`

## 📄 Observações

- O backend possui cobertura de testes para fluxos de autenticação, despesas, dashboard, exportação, importação e cartão de crédito.
- O projeto usa autenticação por token e protege rotas privadas com `auth:sanctum`.
- Há documentação e regras de desenvolvimento adicionais na pasta `AI/`.
