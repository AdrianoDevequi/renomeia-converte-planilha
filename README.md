# Sistema de Conversão e Renomeação de Planilhas

## Visão Geral

Este projeto é uma aplicação web desenvolvida em PHP que automatiza o processo de conversão de arquivos CSV para o formato Excel (.xlsx), aplicando regras específicas de formatação e validação focadas em SEO (Search Engine Optimization).

O sistema permite o upload de arquivos CSV, processa os dados, conta caracteres de colunas críticas (como Título e Descrição) e gera um arquivo Excel formatado com feedback visual (cores) para auxiliar na otimização de conteúdo.

## Funcionalidades Principais

*   **Conversão de CSV para Excel**: Transforma dados brutos de CSV em planilhas Excel organizadas.
*   **Contagem Automática de Caracteres**:
    *   Adiciona colunas de contagem de caracteres ao lado das colunas de "Título" e "Descrição".
    *   Essencial para validar se o conteúdo está dentro dos limites recomendados para SEO.
*   **Formatação Condicional (Visual)**:
    *   **Títulos**:
        *   < 50 caracteres: Amarelo (Curto)
        *   50 - 60 caracteres: Verde (Ideal)
        *   > 60 caracteres: Vermelho (Longo)
    *   **Descrições**:
        *   < 150 caracteres: Amarelo (Curto)
        *   150 - 160 caracteres: Verde (Ideal)
        *   > 160 caracteres: Vermelho (Longo)
*   **Formatação de Colunas**:
    *   Ajuste automático de largura de colunas baseado no tipo de conteúdo (Links, Códigos, Texto).
    *   Wrapping de texto inteligente para melhor legibilidade.
*   **Integração com Banco de Dados**:
    *   Utiliza um banco de dados para gerenciar definições de arquivos e mapear nomes de arquivos originais para nomes traduzidos e descrições personalizadas no Excel gerado.
*   **Cabeçalho Personalizado**: Inclui um cabeçalho no Excel com Logo e Descrição do arquivo.

## Requisitos

*   PHP 7.4 ou superior
*   Composer (para gerenciar dependências)
*   Servidor Web (Apache/Nginx) ou servidor embutido do PHP
*   MySQL/MariaDB (para as definições de arquivos)
*   Extensões PHP: `pdo`, `pdo_mysql`, `gd`, `zip`, `xml`

## Instalação

1.  Clone o repositório:
    ```bash
    git clone https://github.com/AdrianoDevequi/renomeia-converte-planilha.git
    cd renomeia-converte-planilha
    ```

2.  Instale as dependências via Composer:
    ```bash
    composer install
    ```

3.  Configure o banco de dados:
    *   Crie um banco de dados (ex: `file_converter`).
    *   Importe o arquivo `database.sql` para criar a tabela necessária.
    *   Edite o arquivo `config.php` com suas credenciais de banco de dados.

4.  Inicie o servidor local (para desenvolvimento):
    ```bash
    php -S localhost:8000
    ```

5.  Acesse `http://localhost:8000` no seu navegador.

## Como Usar

1.  Na página inicial, arraste ou selecione seu arquivo `.csv`.
2.  Clique em "Converter e Baixar".
3.  O sistema processará o arquivo e iniciará o download do arquivo `.xlsx` automaticamente.

## Estrutura do Projeto

*   `index.php`: Página principal de upload.
*   `process_upload.php`: Script que recebe o arquivo e inicia o processamento.
*   `src/FileProcessor.php`: Lógica principal de leitura do CSV, aplicação de regras de negócio e geração do Excel (usando PhpSpreadsheet).
*   `src/Database.php`: Gerenciamento de conexão e consultas ao banco de dados.
*   `assets/`: Arquivos estáticos (CSS, imagens).
