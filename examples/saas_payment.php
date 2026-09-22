<?php

/**
 * Caso âncora: o gateway (Stripe/Asaas) confirmou um pagamento e o SaaS
 * registra a entrada no GestorSimples.
 *
 * O SaaS conhece o cliente pelo documento, não pelo id do GestorSimples —
 * por isso o findOrCreateByDocument: não duplica cadastro a cada cobrança.
 */

require __DIR__.'/../vendor/autoload.php';

use GestorSimples\Exceptions\ValidationException;
use GestorSimples\GestorSimplesClient;

$client = new GestorSimplesClient([
    'api_token' => getenv('GESTOR_SIMPLES_TOKEN') ?: 'gsk_qa_local_0000000000000000000000000000',
    'base_url' => getenv('GESTOR_SIMPLES_URL') ?: 'http://localhost:8584/api/v1/',
]);

try {
    $customer = $client->people()->findOrCreateByDocument('11.222.333/0001-81', [
        'type' => 'customer',
        'name' => 'Café Aurora LTDA',
        'email' => 'financeiro@cafeaurora.test',
    ]);

    // `category` por nome cria ou reusa: cada SaaS usa a própria, sem setup
    $entry = $client->entries()->recordPayment(
        description: 'Assinatura Pro — set/2026',
        amount: '149.90',
        date: date('Y-m-d'),
        extra: [
            'person_id' => $customer['id'],
            'category' => 'MeetBox',
        ],
    );

    echo "Lançamento {$entry['id']} registrado ({$entry['status']}).\n";
} catch (ValidationException $e) {
    foreach ($e->getErrors() as $field => $messages) {
        echo "  {$field}: ".implode(' ', $messages)."\n";
    }
}
