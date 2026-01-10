<?php

require __DIR__ . '/../vendor/autoload.php';

use GestorSimples\GestorSimplesClient;

// Configuração
$client = new GestorSimplesClient([
    'api_token' => 'SEU_TOKEN_AQUI',
    'base_url' => 'http://127.0.0.1:8000/api/'
]);

echo "=== Testando SDK de Clientes ===\n\n";

try {
    // 1. Listar todos os clientes
    echo "1. Listando todos os clientes:\n";
    $clients = $client->clients()->all();
    print_r($clients);
    echo "\n";

    // 2. Buscar cliente específico
    echo "2. Buscando cliente por documento:\n";
    $specificClient = $client->clients()->findByDocument('123.456.789-09');
    print_r($specificClient);
    echo "\n";

    // 3. Criar novo cliente
    echo "3. Criando novo cliente:\n";
    $newClient = $client->clients()->create([
        'name' => 'Maria Santos',
        'document' => '987.654.321-00',
        'email' => 'maria@email.com',
        'phone' => '(11) 88888-8888',
        'person_type' => 'individual',
        'address' => [
            'street' => 'Rua Exemplo',
            'number' => '123',
            'city' => 'São Paulo',
            'state' => 'SP'
        ]
    ]);
    echo "Cliente criado com sucesso!\n";
    print_r($newClient);
    echo "\n";

    // 4. Atualizar cliente
    echo "4. Atualizando cliente:\n";
    $updatedClient = $client->clients()->update('987.654.321-00', [
        'email' => 'maria.nova@email.com'
    ]);
    echo "Cliente atualizado!\n";
    print_r($updatedClient);
    echo "\n";

    // 5. Buscar ou criar
    echo "5. Buscando ou criando cliente:\n";
    $clientData = $client->clients()->findOrCreate('111.222.333-44', [
        'name' => 'Carlos Oliveira',
        'email' => 'carlos@email.com'
    ]);
    print_r($clientData);
    echo "\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getErrors')) {
        print_r($e->getErrors());
    }
}