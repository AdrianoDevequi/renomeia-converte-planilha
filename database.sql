-- Create table in the currently selected database
CREATE TABLE IF NOT EXISTS file_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL UNIQUE,
    translated_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data based on user screenshot
INSERT INTO file_definitions (original_name, translated_name, description) VALUES 
('h1_multiple.csv', 'multiplas tags h1', 'Relatório contendo páginas com múltiplas tags H1.'),
('response_codes_internal_client_error_(4xx).csv', 'erros cliente 4xx', 'Lista de URLs que retornaram erro 4xx.'),
('page_titles_below_30_characters.csv', 'titulos curtos', 'Páginas com títulos abaixo de 30 caracteres.'),
('content_lorem_ipsum_placeholder.csv', 'conteudo placeholder', 'Páginas detectadas com texto Lorem Ipsum.')
ON DUPLICATE KEY UPDATE translated_name=VALUES(translated_name), description=VALUES(description);
