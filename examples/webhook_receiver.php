<?php

/**
 * Receptor dos webhooks do GestorSimples (PHP puro; em Laravel, troque
 * `file_get_contents('php://input')` por `$request->getContent()`).
 *
 * Para testar local: `php -S localhost:9000 examples/webhook_receiver.php` e
 * cadastre http://localhost:9000 com WEBHOOKS_ALLOW_PRIVATE=true no
 * GestorSimples (em produção a URL precisa ser https pública).
 */

require __DIR__.'/../vendor/autoload.php';

use GestorSimples\Exceptions\InvalidSignatureException;
use GestorSimples\Webhooks\Webhook;

$secret = getenv('GESTOR_SIMPLES_WEBHOOK_SECRET') ?: '';

try {
    $event = Webhook::parse(
        file_get_contents('php://input') ?: '',     // o corpo CRU, nunca reserializado
        $_SERVER['HTTP_X_GESTORSIMPLES_SIGNATURE'] ?? null,
        $secret,
    );
} catch (InvalidSignatureException) {
    http_response_code(401);

    return;
}

// A mesma entrega pode chegar de novo: guarde $event['id'] e ignore repetidos.

$invoice = $event['data'];

switch ($event['event']) {
    case Webhook::INVOICE_AUTHORIZED:
        // em Embu o PDF já vem; o link vale 60 min — baixe agora ou peça a
        // nota de novo depois ($client->invoices()->find($invoice['id']))
        error_log("nota {$invoice['external_id']} autorizada: {$invoice['documents']['pdf_url']}");
        break;

    case Webhook::INVOICE_OFFICIAL_DOCUMENT:
        error_log("XML oficial de {$invoice['external_id']}: {$invoice['documents']['xml_url']}");
        break;

    case Webhook::INVOICE_ERROR:
        error_log("nota {$invoice['external_id']} não saiu: {$invoice['error']}");
        break;

    case Webhook::INVOICE_CANCELLED:
        error_log("nota {$invoice['external_id']} cancelada");
        break;
}

// qualquer 2xx confirma a entrega; responda rápido e processe na fila
http_response_code(204);
