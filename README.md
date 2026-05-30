
# Kado-to API

![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-API%20Auth-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-Tests-7A1FA2?style=for-the-badge&logo=php&logoColor=white)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%2010-4F5D95?style=for-the-badge&logo=php&logoColor=white)
![Rector](https://img.shields.io/badge/Rector-Refactoring-6E4AFF?style=for-the-badge&logo=php&logoColor=white)

## Status do projeto

O projeto já está deployado e disponível online por meio da aplicação frontend.

A interface web consome esta API e permite utilizar os principais fluxos financeiros do sistema, como cadastro de lançamentos, categorias, fontes financeiras, dashboard, importação/exportação e controle de faturas.

Acesse o projeto:

<https://kado-tan.vercel.app/dashboard>

---

Kado-to é a API central do projeto Kado, um sistema de controle financeiro pessoal criado para ajudar usuários a entender, organizar e acompanhar melhor sua vida financeira.

O projeto permite registrar receitas, despesas, fontes de saldo, cartões de crédito e categorias, oferecendo uma base para visualizar gastos, acompanhar pagamentos, importar dados e gerar resumos financeiros de forma simples e estruturada.

Mais do que apenas cadastrar lançamentos, a proposta do Kado é centralizar informações financeiras do dia a dia e transformar esses dados em uma visão mais clara sobre como o dinheiro está sendo usado.

## Sobre o projeto

O Kado-to funciona como o backend responsável por toda a regra de negócio do sistema Kado.

Ele cuida da autenticação dos usuários, do controle de lançamentos financeiros, da organização por categorias, da gestão de fontes financeiras e da geração de informações para o dashboard.

Na prática, a API permite que o usuário tenha controle sobre:

- Quanto recebeu
- Quanto gastou
- Quais despesas já foram pagas
- Quais despesas ainda estão pendentes
- De onde o dinheiro está saindo
- Em quais categorias o dinheiro está sendo usado
- Como estão suas faturas de cartão de crédito
- Como os dados financeiros podem ser importados ou exportados

## Principais funcionalidades

### Controle de lançamentos financeiros

O sistema permite cadastrar receitas e despesas, editar informações, remover lançamentos e marcar despesas como pagas.

Esse fluxo ajuda o usuário a acompanhar não apenas o que foi planejado, mas também o que de fato já foi pago.

### Organização por categorias

As categorias permitem classificar os lançamentos financeiros de acordo com o tipo de gasto ou receita.

Cada usuário pode criar e personalizar suas próprias categorias, facilitando a análise dos dados no dashboard e nas listagens.

### Fontes financeiras

O sistema trabalha com fontes financeiras, como saldo principal, carteiras, contas e cartões de crédito.

Isso permite separar de onde o dinheiro vem ou para onde ele está indo, tornando o controle mais próximo da realidade financeira do usuário.

### Cartão de crédito e faturas

A API possui suporte para cartões de crédito, incluindo geração de faturas, compras parceladas, pagamento de faturas e estorno de pagamento.

Esse fluxo é importante porque muitos gastos pessoais acontecem no cartão, mas só impactam o controle financeiro de forma consolidada na fatura.

### Dashboard financeiro

O dashboard consolida os principais dados financeiros do usuário, permitindo visualizar totais, resumos e cards configuráveis.

A ideia é oferecer uma visão rápida da situação financeira, sem que o usuário precise analisar lançamento por lançamento.

### Importação e exportação de dados

O sistema permite importar despesas via CSV e exportar dados em CSV ou XLSX.

Isso facilita a entrada de dados em massa e também permite que o usuário mantenha cópias ou faça análises externas quando necessário.

## Funcionalidades disponíveis

- Cadastro de usuários
- Login com autenticação por token
- Criação automática de uma fonte principal de saldo
- Cadastro, edição e remoção de receitas
- Cadastro, edição e remoção de despesas
- Marcação manual de despesas como pagas
- Cadastro e edição de categorias personalizadas
- Gestão de fontes financeiras
- Gestão de cartões de crédito
- Geração de faturas
- Pagamento e estorno de faturas
- Suporte a compras parceladas
- Dashboard com resumo financeiro
- Cards configuráveis por usuário
- Filtros por status, categoria, mês e busca textual
- Importação de despesas por CSV
- Exportação de dados em CSV e XLSX

## Visão técnica

O backend foi desenvolvido com Laravel e segue uma organização baseada em ações, DTOs e controllers de responsabilidade única.

A estrutura busca manter as regras de negócio centralizadas, evitando que controllers concentrem lógica de aplicação.

Principais decisões técnicas:

- Controllers com foco em entrada HTTP
- Actions responsáveis pela execução dos casos de uso
- DTOs para entrada e saída de dados
- Autenticação com Laravel Sanctum
- Testes automatizados com Pest
- Análise estática com PHPStan
- Refatorações automatizadas com Rector

## Módulos principais

### Auth

Responsável pelo cadastro, login e autenticação dos usuários.

### Dashboard

Responsável pelos resumos financeiros, listagem de despesas e cards configuráveis.

### Expense

Responsável pelo fluxo de receitas, despesas, marcação de pagamento, importação e exportação.

### Category

Responsável pela criação e manutenção das categorias do usuário.

### Source

Responsável pelo gerenciamento das fontes financeiras, como saldo principal, carteiras e cartões.

### CreditCard

Responsável pelo fluxo de faturas de cartão de crédito, incluindo pagamento e estorno.

## Tecnologias utilizadas

- PHP 8.2+
- Laravel 13
- MySQL 8
- Laravel Sanctum
- Pest
- PHPStan
- Rector
- Laravel Sail

## Como executar o projeto

### Pré-requisitos

- Docker
- Docker Compose
- Composer

### Instalação

Clone o repositório e acesse a pasta do projeto.

Instale as dependências PHP:

```bash
composer install
```

Suba os containers:

```bash
./vendor/bin/sail up -d
```

Configure a aplicação:

```bash
./vendor/bin/sail composer setup
```

Inicie o ambiente de desenvolvimento:

```bash
./vendor/bin/sail composer dev
```

A API ficará disponível em:

```text
http://localhost
```

O script `setup` cria o arquivo `.env` quando necessário, gera a `APP_KEY` e executa as migrations.

## Comandos úteis

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
./vendor/bin/sail composer test
./vendor/bin/sail composer lint:all:check
./vendor/bin/sail composer lint:fix
./vendor/bin/sail shell
```

## Qualidade e manutenção

O projeto possui testes automatizados cobrindo fluxos importantes da aplicação, como autenticação, despesas, dashboard, importação, exportação e cartão de crédito.

Também utiliza ferramentas de análise e padronização para manter o código mais seguro, consistente e fácil de evoluir.

Ferramentas utilizadas:

- Pest para testes automatizados
- PHPStan para análise estática
- Rector para apoio em refatorações
- Laravel Pint ou ferramenta equivalente para padronização de código

## Observações

Este projeto é o backend do sistema Kado e foi pensado para servir como base de uma aplicação de controle financeiro pessoal.

A API expõe os recursos necessários para que uma interface frontend possa consumir os dados, exibir dashboards, permitir lançamentos financeiros e acompanhar a evolução da vida financeira do usuário.
