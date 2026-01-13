<?php
require 'vendor/autoload.php';
use App\Database;

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original = $_POST['original_name'] ?? '';
    $translated = $_POST['translated_name'] ?? '';
    $desc = $_POST['description'] ?? '';
    $id = $_POST['id'] ?? null;

    if ($original && $translated) {
        try {
            // Validar duplicações
            $checkSql = "SELECT id FROM file_definitions WHERE original_name = :orig";
            $params = ['orig' => $original];
            if ($id) {
                $checkSql .= " AND id != :id";
                $params['id'] = $id;
            }
            $stmt = $conn->prepare($checkSql);
            $stmt->execute($params);

            if ($stmt->fetch()) {
                $message = "Erro: Já existe um arquivo cadastrado com esse nome original.";
                $messageType = 'error';
            } else {
                if ($id) {
                    // Update
                    $stmt = $conn->prepare("UPDATE file_definitions SET original_name = :orig, translated_name = :trans, description = :desc WHERE id = :id");
                    $stmt->execute(['orig' => $original, 'trans' => $translated, 'desc' => $desc, 'id' => $id]);
                    $message = "Configuração atualizada com sucesso!";
                } else {
                    // Create
                    $stmt = $conn->prepare("INSERT INTO file_definitions (original_name, translated_name, description) VALUES (:orig, :trans, :desc)");
                    $stmt->execute(['orig' => $original, 'trans' => $translated, 'desc' => $desc]);
                    $message = "Configuração criada com sucesso!";
                }
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = "Erro ao salvar: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "Preencha os campos obrigatórios.";
        $messageType = 'error';
    }
}

// Fetch all definitions
$stmt = $conn->query("SELECT * FROM file_definitions ORDER BY id DESC");
$definitions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Conversor</title>
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
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="settings.php"
                            class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Configurações</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">

        <?php if ($message): ?>
            <div
                class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Form Column -->
            <div class="md:col-span-1">
                <div class="glass-panel p-6 rounded-xl sticky top-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Nova Definição de Arquivo</h3>
                    <form action="settings.php" method="POST">
                        <input type="hidden" name="id" id="formId">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Nome Original (com extensão)</label>
                            <input type="text" name="original_name" id="formOriginal" required
                                placeholder="ex: arquivo.csv"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Nome Traduzido (sem extensão)</label>
                            <input type="text" name="translated_name" id="formTranslated" required
                                placeholder="ex: Arquivo Traduzido"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border">
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Descrição (Linha 1)</label>
                            <textarea name="description" id="formDescription" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm p-2 border"></textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="button" onclick="resetForm()"
                                class="text-sm text-gray-500 hover:text-gray-700">Limpar</button>
                            <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List Column -->
            <div class="md:col-span-2">
                <div class="bg-white shadow overflow-hidden sm:rounded-md border border-gray-200">
                    <ul role="list" class="divide-y divide-gray-200">
                        <?php foreach ($definitions as $def): ?>
                            <li>
                                <div class="px-4 py-4 sm:px-6 hover:bg-gray-50 flex items-center justify-between">
                                    <div class="flex-1 min-w-0" onclick='editDef(<?php echo json_encode($def); ?>)'>
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-indigo-600 truncate">
                                                <?php echo htmlspecialchars($def['translated_name']); ?>
                                            </p>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-500">
                                            <p><span class="font-semibold">Original:</span>
                                                <?php echo htmlspecialchars($def['original_name']); ?></p>
                                            <p class="truncate text-xs mt-1 bg-gray-100 p-1 rounded inline-block">
                                                <?php echo htmlspecialchars($def['description']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <button onclick='editDef(<?php echo json_encode($def); ?>)'
                                            class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Editar</button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>

                        <?php if (empty($definitions)): ?>
                            <li class="px-4 py-8 text-center text-gray-500">
                                Nenhuma definição encontrada. Adicione uma ao lado.
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editDef(data) {
            document.getElementById('formId').value = data.id;
            document.getElementById('formOriginal').value = data.original_name;
            document.getElementById('formTranslated').value = data.translated_name;
            document.getElementById('formDescription').value = data.description;
        }

        function resetForm() {
            document.getElementById('formId').value = '';
            document.getElementById('formOriginal').value = '';
            document.getElementById('formTranslated').value = '';
            document.getElementById('formDescription').value = '';
        }
    </script>
</body>

</html>