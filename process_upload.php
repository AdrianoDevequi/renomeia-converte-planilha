<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use App\FileProcessor;

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    if (!isset($_FILES['csv_file']) || empty($_FILES['csv_file']['name'][0])) {
        throw new Exception("Nenhum arquivo enviado.");
    }

    $processor = new FileProcessor();
    $processedFiles = [];

    // Normalize $_FILES structure for iteration
    $fileCount = count($_FILES['csv_file']['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['csv_file']['error'][$i] !== UPLOAD_ERR_OK) {
            continue; // Skip errors for now or log them
        }

        $singleFile = [
            'name' => $_FILES['csv_file']['name'][$i],
            'type' => $_FILES['csv_file']['type'][$i],
            'tmp_name' => $_FILES['csv_file']['tmp_name'][$i],
            'error' => $_FILES['csv_file']['error'][$i],
            'size' => $_FILES['csv_file']['size'][$i]
        ];

        try {
            $generatedFile = $processor->processUpload($singleFile);
            $processedFiles[] = $generatedFile;
        } catch (Exception $e) {
            // Collect validation errors
            // If the error is "unknown file", we want to stop everything as per user request.
            throw $e;
        }
    }

    if (empty($processedFiles)) {
        throw new Exception("Nenhum arquivo pôde ser processado corretamente.");
    }

    $outputDir = __DIR__ . '/processed_files/';

    if (count($processedFiles) === 1) {
        // Single file download
        $filePath = $outputDir . $processedFiles[0];
        $isZip = false;
        $downloadName = basename($filePath);
    } else {
        // Multiple files - Create ZIP
        $zip = new ZipArchive();
        $zipFilename = 'arquivos_conertidos_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $outputDir . $zipFilename;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Não foi possível criar o arquivo ZIP.");
        }

        foreach ($processedFiles as $file) {
            $zip->addFile($outputDir . $file, $file);
        }

        $zip->close();

        $filePath = $zipPath;
        $isZip = true;
        $downloadName = $zipFilename;
    }

    // Check if file exists before sending
    if (!file_exists($filePath)) {
        throw new Exception("Erro interno: Arquivo final não encontrado.");
    }

    // Serve file
    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($isZip ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

    // Cleanup processed files (optional logic could be added here)
    if ($isZip) {
        unlink($filePath); // Delete zip after download
        foreach ($processedFiles as $f) {
            unlink($outputDir . $f); // Delete source xlsx
        }
    } else {
        unlink($filePath); // Delete single xlsx
    }
    exit;

} catch (Throwable $e) {
    $isValidationError = $e instanceof RuntimeException;
    $bgColor = $isValidationError ? '#fff7ed' : '#fef2f2'; // Orange for warning, Red for error
    $borderColor = $isValidationError ? '#fed7aa' : '#fecaca';
    $textColor = $isValidationError ? '#9a3412' : '#dc2626';
    $title = $isValidationError ? 'Atenção' : 'Erro no Processamento';

    echo "<div style='font-family: sans-serif; padding: 2rem; color: {$textColor}; background: {$bgColor}; border: 1px solid {$borderColor}; border-radius: 0.5rem; max-width: 600px; margin: 2rem auto;'>";
    echo "<h2 style='font-weight: bold; margin-bottom: 0.5rem;'>{$title}</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";

    // Only show trace if it's NOT a validation error (for debugging)
    if (!$isValidationError) {
        echo "<pre style='background: #fff; padding: 1rem; border-radius: 4px; overflow: auto; font-size: 0.8rem; margin-top: 1rem; color: #333;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }

    echo "<p style='margin-top: 1rem;'><a href='index.php' style='text-decoration: underline; color: {$textColor};'>Voltar</a></p>";
    echo "</div>";
}
