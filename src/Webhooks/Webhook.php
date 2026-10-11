<?php

namespace GestorSimples\Webhooks;

use GestorSimples\Exceptions\InvalidSignatureException;

/**
 * Receptor dos webhooks do GestorSimples.
 *
 * Cada entrega é um POST com o corpo `{id, event, occurred_at, data}` e o
 * cabeçalho `X-GestorSimples-Signature: sha256=<HMAC-SHA256 do corpo>`,
 * assinado com o segredo `whsec_…` mostrado uma vez no cadastro do endpoint.
 *
 * Use o corpo **cru** da requisição: reserializar o JSON muda os bytes e a
 * assinatura deixa de bater.
 *
 *     $event = Webhook::parse(
 *         $request->getContent(),
 *         $request->header(Webhook::SIGNATURE_HEADER),
 *         env('GESTORSIMPLES_WEBHOOK_SECRET'),
 *     );
 *
 * A mesma entrega pode chegar mais de uma vez (o GestorSimples tenta de novo
 * até receber 2xx): ignore `$event['id']` já processado.
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'X-GestorSimples-Signature';

    public const EVENT_HEADER = 'X-GestorSimples-Event';

    public const DELIVERY_HEADER = 'X-GestorSimples-Delivery';

    public const INVOICE_AUTHORIZED = 'invoice.authorized';

    public const INVOICE_OFFICIAL_DOCUMENT = 'invoice.official_document';

    public const INVOICE_ERROR = 'invoice.error';

    public const INVOICE_CANCELLED = 'invoice.cancelled';

    public const PING = 'ping';

    public static function signature(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }

    /**
     * Comparação em tempo constante: não vaza, pela demora, quanto da
     * assinatura acertou.
     */
    public static function verify(string $body, ?string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(self::signature($body, $secret), trim($signature));
    }

    /**
     * Confere a assinatura e devolve o evento decodificado.
     *
     * @return array{id: string, event: string, occurred_at: string, data: array<string, mixed>}
     *
     * @throws InvalidSignatureException assinatura ausente, errada ou corpo que não é um evento
     */
    public static function parse(string $body, ?string $signature, string $secret): array
    {
        if (! self::verify($body, $signature, $secret)) {
            throw new InvalidSignatureException('Assinatura do webhook inválida.');
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! isset($event['id'], $event['event'])) {
            throw new InvalidSignatureException('Corpo do webhook não é um evento do GestorSimples.');
        }

        $event['data'] = is_array($event['data'] ?? null) ? $event['data'] : [];

        return $event;
    }
}
