<?php

/**
 * Caso âncora: o gateway (Stripe/Asaas) confirmou um pagamento e o SaaS pede
 * a NFS-e. Uma chamada só: o GestorSimples acha ou cria o tomador pelo
 * documento, lança o recebimento e põe a nota na fila.
 *
 * `external_id` é a chave do pagamento no SaaS: o webhook do gateway pode
 * chegar duas vezes, e a segunda chamada devolve a mesma nota.
 *
 * ATENÇÃO: contra um servidor configurado, isto emite uma nota de verdade.
 */

require __DIR__.'/../vendor/autoload.php';

use GestorSimples\Exceptions\ValidationException;
use GestorSimples\GestorSimplesClient;

$client = new GestorSimplesClient([
    'api_token' => getenv('GESTOR_SIMPLES_TOKEN') ?: 'gsk_qa_local_0000000000000000000000000000',
    'base_url' => getenv('GESTOR_SIMPLES_URL') ?: 'http://localhost:8584/api/v1/',
]);

try {
    $invoice = $client->invoices()->issue([
        'external_id' => 'consultafast:credit:6721a0f0c3b1',
        'description' => 'ConsultaFast - Total de 100 créditos',
        'amount' => '30.00',
        'person' => [
            'document' => '11.222.333/0001-81',
            'name' => 'Café Aurora LTDA',
            'email' => 'financeiro@cafeaurora.test',
            'zip_code' => '60160-250',
            'street' => 'R Pereira Valente',
            'number' => '578',
            'district' => 'Meireles',
            'city' => 'Fortaleza',
            'state' => 'CE',
        ],
        // a categoria por nome é criada na primeira vez
        'entry' => ['category' => 'ConsultaFast', 'notes' => 'Asaas pay_123'],
    ]);

    // a emissão é assíncrona: guarde o id e espere o webhook invoice.authorized
    echo "Nota {$invoice['id']} pedida ({$invoice['status']}).\n";

    if ($invoice['status'] === 'error') {
        // o recebimento foi lançado igual; corrija o tomador e peça de novo
        echo "Não saiu: {$invoice['error']}\n";
    }
} catch (ValidationException $e) {
    foreach ($e->getErrors() as $field => $messages) {
        echo "  {$field}: ".implode(' ', $messages)."\n";
    }
}
