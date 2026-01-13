<?php

require __DIR__ . '/../vendor/autoload.php';

use GestorSimples\GestorSimplesClient;

// Configuração
$client = new GestorSimplesClient([
    'api_token' => 'SEU_TOKEN_AQUI',
    'base_url' => 'http://127.0.0.1:8000/api/'
]);

echo "=== Testando SDK de Categorias ===\n\n";

try {
    // 1. Listar todas as categorias
    echo "1. Listando todas as categorias:\n";
    $categories = $client->categories()->all();
    echo "Total de categorias: " . count($categories) . "\n";
    print_r($categories);
    echo "\n";

    // 2. Criar categoria de receita
    echo "2. Criando categoria de receita:\n";
    $incomeCategory = $client->categories()->create([
        'name' => 'Vendas Online',
        'type' => 'income',
        'color' => '#10B981'
    ]);
    echo "Categoria criada com ID: " . ($incomeCategory['id'] ?? 'N/A') . "\n";
    print_r($incomeCategory);
    echo "\n";

    // 3. Criar categoria de despesa
    echo "3. Criando categoria de despesa:\n";
    $expenseCategory = $client->categories()->createWithSuggestedColor(
        'Material de Escritório',
        'expense'
    );
    echo "Categoria criada: " . $expenseCategory['name'] . "\n";
    print_r($expenseCategory);
    echo "\n";

    // 4. Buscar categorias por tipo
    echo "4. Buscando categorias de receita:\n";
    $incomeCategories = $client->categories()->incomeCategories();
    echo "Categorias de receita encontradas: " . count($incomeCategories) . "\n";

    echo "5. Buscando categorias de despesa:\n";
    $expenseCategories = $client->categories()->expenseCategories();
    echo "Categorias de despesa encontradas: " . count($expenseCategories) . "\n";

    // 5. Buscar categoria específica
    echo "6. Buscando categoria por ID:\n";
    if (!empty($incomeCategory['id'])) {
        $foundCategory = $client->categories()->find($incomeCategory['id']);
        if ($foundCategory) {
            echo "Categoria encontrada: " . $foundCategory['name'] . "\n";
        } else {
            echo "Categoria não encontrada\n";
        }
    }
    echo "\n";

    // 6. Atualizar categoria
    echo "7. Atualizando categoria:\n";
    if (!empty($expenseCategory['id'])) {
        $updatedCategory = $client->categories()->update($expenseCategory['id'], [
            'color' => '#F59E0B', // Laranja
            'name' => 'Material de Escritório e Papelaria'
        ]);
        echo "Categoria atualizada!\n";
        print_r($updatedCategory);
    }
    echo "\n";

    // 7. Buscar ou criar
    echo "8. Buscando ou criando categoria:\n";
    $category = $client->categories()->findOrCreate(
        'Serviços Prestados',
        'income',
        '#3B82F6' // Azul
    );
    echo "Categoria: " . $category['name'] . "\n";

    // Tentar novamente (deve encontrar a existente)
    $sameCategory = $client->categories()->findOrCreate(
        'Serviços Prestados',
        'income'
    );
    echo "Mesma categoria (encontrada): " . $sameCategory['name'] . "\n";
    echo "\n";

    // 8. Buscar por nome
    echo "9. Buscando categoria por nome:\n";
    $foundByName = $client->categories()->findByName('Vendas Online', 'income');
    if ($foundByName) {
        echo "Encontrada: " . $foundByName['name'] . " - " . $foundByName['type'] . "\n";
    } else {
        echo "Não encontrada\n";
    }

    // 9. Sugerir cores
    echo "\n10. Sugestões de cores:\n";
    echo "Cor para receita: " . $client->categories()->suggestColor('income') . "\n";
    echo "Cor para despesa: " . $client->categories()->suggestColor('expense') . "\n";

} catch (\GestorSimples\Exceptions\ValidationException $e) {
    echo "Erros de validação:\n";
    print_r($e->getErrors());
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getErrors')) {
        print_r($e->getErrors());
    }
}