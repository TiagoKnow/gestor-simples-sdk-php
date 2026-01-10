<?php

require __DIR__ . '/../vendor/autoload.php';

use GestorSimples\GestorSimplesClient;

// Configuração
$client = new GestorSimplesClient([
    'api_token' => 'SEU_TOKEN_AQUI',
    'base_url' => 'http://127.0.0.1:8000/api/'
]);

echo "=== Testando SDK de Lançamentos Financeiros ===\n\n";

try {
    // 1. Listar todos os lançamentos
    echo "1. Listando todos os lançamentos:\n";
    $entries = $client->financialEntries()->all();
    print_r($entries);
    echo "\n";

    // 2. Criar novo lançamento de receita
    echo "2. Criando lançamento de receita:\n";
    $incomeEntry = $client->financialEntries()->create([
        'description' => 'Venda de Produto X',
        'amount' => 299.90,
        'type' => 'income',
        'due_date' => date('Y-m-d', strtotime('+7 days')),
        'category_id' => 1,
        'person_id' => 1,
        'notes' => 'Venda realizada via site'
    ]);
    echo "Lançamento criado com ID: " . ($incomeEntry['id'] ?? 'N/A') . "\n";
    print_r($incomeEntry);
    echo "\n";

    // 3. Criar novo lançamento de despesa
    echo "3. Criando lançamento de despesa:\n";
    $expenseEntry = $client->financialEntries()->create([
        'description' => 'Compra de material',
        'amount' => 150.00,
        'type' => 'expense',
        'due_date' => date('Y-m-d'),
        'paid' => true,
        'paid_at' => date('Y-m-d'),
        'category_id' => 2
    ]);
    echo "Despesa criada com ID: " . ($expenseEntry['id'] ?? 'N/A') . "\n";
    echo "\n";

    // 4. Filtrar por período
    echo "4. Filtrando lançamentos do mês atual:\n";
    $monthEntries = $client->financialEntries()->filterByPeriod(
        date('Y-m-01'),
        date('Y-m-t')
    );
    echo "Total de lançamentos: " . count($monthEntries) . "\n";
    echo "\n";

    // 5. Buscar lançamentos pendentes
    echo "5. Buscando lançamentos pendentes:\n";
    $pendingEntries = $client->financialEntries()->findPending();
    echo "Lançamentos pendentes: " . count($pendingEntries) . "\n";
    echo "\n";

    // 6. Buscar lançamentos por tipo
    echo "6. Buscando apenas receitas:\n";
    $incomes = $client->financialEntries()->findByType('income');
    echo "Total de receitas: " . count($incomes) . "\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getErrors')) {
        print_r($e->getErrors());
    }
}