# SDK PHP do Gestor Simples

SDK oficial para integração com a API do Gestor Simples.

## Instalação

```bash
composer require tiagoknow/gestor-simples-sdk
```

Configuração
```bash
require 'vendor/autoload.php';

use GestorSimples\GestorSimplesClient;

// Para produção
$client = new GestorSimplesClient([
    'api_token' => 'SEU_TOKEN_AQUI',
    'base_url' => 'https://app.gestorsimples.com.br/api/'
]);

// Para desenvolvimento local
$devClient = new GestorSimplesClient([
    'api_token' => 'SEU_TOKEN_DEV',
    'base_url' => 'http://127.0.0.1:8000/api/',
    'timeout' => 60
]);
```
Uso
Clientes
```bash
// Listar todos os clientes
$clients = $client->clients()->all();

// Buscar cliente por documento
$clientData = $client->clients()->findByDocument('123.456.789-09');

// Criar novo cliente
$newClient = $client->clients()->create([
    'name' => 'João Silva',
    'document' => '123.456.789-09',
    'email' => 'joao@email.com',
    'phone' => '(11) 99999-9999'
]);

// Atualizar cliente
$updatedClient = $client->clients()->update('123.456.789-09', [
    'email' => 'novoemail@email.com'
]);

// Buscar ou criar
$clientData = $client->clients()->findOrCreate('123.456.789-09', [
    'name' => 'João Silva',
    'email' => 'joao@email.com'
]);
Lançamentos Financeiros

// Listar todos os lançamentos
$entries = $client->financialEntries()->all();

// Buscar por ID
$entry = $client->financialEntries()->find(123);

// Criar novo lançamento
$newEntry = $client->financialEntries()->create([
    'description' => 'Venda de produto',
    'amount' => 150.50,
    'type' => 'income',
    'due_date' => '2024-01-15'
]);

// Filtrar por período
$entries = $client->financialEntries()->filterByPeriod('2024-01-01', '2024-01-31');

// Buscar lançamentos pendentes
$pending = $client->financialEntries()->findPending();

// Buscar lançamentos pagos
$paid = $client->financialEntries()->findPaid();

Uso Direto

// Métodos HTTP diretos
$response = $client->get('endpoint', ['param' => 'value']);
$response = $client->post('endpoint', ['data' => 'value']);
$response = $client->put('endpoint', ['data' => 'value']);
$response = $client->delete('endpoint');
Tratamento de Erros

try {
    $clients = $client->clients()->all();
} catch (\GestorSimples\Exceptions\AuthenticationException $e) {
    echo "Erro de autenticação: " . $e->getMessage();
} catch (\GestorSimples\Exceptions\ValidationException $e) {
    echo "Erros de validação:";
    print_r($e->getErrors());
} catch (\GestorSimples\Exceptions\ApiException $e) {
    echo "Erro na API: " . $e->getMessage();
}
Exemplos
Verifique a pasta examples/ para mais exemplos de uso.
```