<?php

/**
 * Consultas do dia a dia: o que entrou no mês, o que está vencido.
 */

require __DIR__.'/../vendor/autoload.php';

use GestorSimples\GestorSimplesClient;

$client = new GestorSimplesClient([
    'api_token' => getenv('GESTOR_SIMPLES_TOKEN') ?: 'gsk_qa_local_0000000000000000000000000000',
    'base_url' => getenv('GESTOR_SIMPLES_URL') ?: 'http://localhost:8584/api/v1/',
]);

$mes = $client->entries()->betweenDueDates(
    date('Y-m-01'),
    date('Y-m-t'),
    ['kind' => 'income', 'per_page' => 100],
);

echo "Recebimentos do mês: {$mes['meta']['total']}\n";

foreach ($mes['data'] as $entry) {
    echo "  {$entry['due_date']}  R$ {$entry['amount']}  {$entry['description']}\n";
}

$vencidos = $client->entries()->overdue(['kind' => 'income']);
echo "\nEm atraso: {$vencidos['meta']['total']}\n";
