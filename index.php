<?php
require 'vendor/autoload.php';

// Simple session check could be added here if needed
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversor de Arquivos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="text-slate-800">

    <nav class="bg-white shadow-sm border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="font-bold text-xl text-indigo-600">FileConverter</span>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="index.php"
                            class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="settings.php"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Configurações</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">

        <div class="glass-panel rounded-xl p-8 mb-8">
            <h2 class="text-2xl font-bold mb-6 text-gray-900">Upload de Arquivos</h2>

            <form action="process_upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
                <div
                    class="drop-zone w-full h-64 rounded-lg flex flex-col items-center justify-center cursor-pointer bg-slate-50 relative group">
                    <input type="file" name="csv_file[]" id="fileInput"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".csv" multiple required>

                    <div
                        class="text-center p-6 pointer-events-none group-hover:scale-105 transition-transform duration-200">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none"
                            viewBox="0 0 48 48" aria-hidden="true">
                            <path
                                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <p class="mt-1 text-sm text-gray-600">Arraste um arquivo CSV ou clique para selecionar</p>
                        <p class="mt-1 text-xs text-gray-500" id="fileNameDisplay">Nenhum arquivo selecionado</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        Converter e Baixar
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Output Logic could go here if we tracked history in DB -->

    </div>

    <script>
        const fileInput = document.getElementById('fileInput');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const dropZone = document.querySelector('.drop-zone');

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) {
                if (e.target.files.length === 1) {
                    fileNameDisplay.textContent = e.target.files[0].name;
                } else {
                    fileNameDisplay.textContent = `${e.target.files.length} arquivos selecionados`;
                }
                dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
            } else {
                fileNameDisplay.textContent = 'Nenhum arquivo selecionado';
                dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
            }
        });

        // Simple drag visual cues
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                dropZone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                dropZone.classList.remove('dragover');
            }, false);
        });
    </script>
</body>

</html>