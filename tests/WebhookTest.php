<?php

namespace GestorSimples\Tests;

use GestorSimples\Exceptions\InvalidSignatureException;
use GestorSimples\Webhooks\Webhook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A assinatura é a do GestorSimples (`App\Services\Webhooks`):
 * `sha256=` + HMAC-SHA256 hexadecimal do corpo cru com o segredo.
 */
class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_teste';

    private const BODY = '{"id":"01w1","event":"invoice.authorized","occurred_at":"2026-10-10T15:00:12-03:00","data":{"id":"01n1","status":"authorized"}}';

    #[Test]
    public function assinatura_e_hmac_sha256_do_corpo_com_prefixo(): void
    {
        $this->assertSame(
            'sha256='.hash_hmac('sha256', self::BODY, self::SECRET),
            Webhook::signature(self::BODY, self::SECRET),
        );
    }

    #[Test]
    public function aceita_a_entrega_assinada(): void
    {
        $event = Webhook::parse(self::BODY, Webhook::signature(self::BODY, self::SECRET), self::SECRET);

        $this->assertSame(Webhook::INVOICE_AUTHORIZED, $event['event']);
        $this->assertSame('01w1', $event['id']);
        $this->assertSame('authorized', $event['data']['status']);
    }

    /**
     * Reserializar o JSON (ex.: `json_encode($request->all())`) muda os
     * bytes; a verificação tem de ser sobre o corpo cru.
     */
    #[Test]
    public function recusa_corpo_alterado(): void
    {
        $signature = Webhook::signature(self::BODY, self::SECRET);
        $tampered = str_replace('authorized', 'cancelled', self::BODY);

        $this->assertFalse(Webhook::verify($tampered, $signature, self::SECRET));
    }

    #[Test]
    public function recusa_segredo_errado_ou_assinatura_ausente(): void
    {
        $signature = Webhook::signature(self::BODY, 'whsec_outro');

        $this->assertFalse(Webhook::verify(self::BODY, $signature, self::SECRET));
        $this->assertFalse(Webhook::verify(self::BODY, null, self::SECRET));
        $this->assertFalse(Webhook::verify(self::BODY, '', self::SECRET));
    }

    /**
     * Segredo vazio (env esquecida) assinaria qualquer coisa com HMAC de
     * chave vazia — tem de recusar, não aceitar tudo.
     */
    #[Test]
    public function segredo_vazio_recusa_tudo(): void
    {
        $this->assertFalse(Webhook::verify(self::BODY, Webhook::signature(self::BODY, ''), ''));
    }

    #[Test]
    public function parse_lanca_quando_a_assinatura_nao_bate(): void
    {
        $this->expectException(InvalidSignatureException::class);

        Webhook::parse(self::BODY, 'sha256=00', self::SECRET);
    }

    #[Test]
    public function parse_lanca_quando_o_corpo_nao_e_um_evento(): void
    {
        $body = '{"hello":"world"}';

        $this->expectException(InvalidSignatureException::class);

        Webhook::parse($body, Webhook::signature($body, self::SECRET), self::SECRET);
    }

    #[Test]
    public function ping_sem_dados_vem_com_data_vazio(): void
    {
        $body = '{"id":"01w2","event":"ping","occurred_at":"2026-10-10T15:00:12-03:00"}';

        $event = Webhook::parse($body, Webhook::signature($body, self::SECRET), self::SECRET);

        $this->assertSame(Webhook::PING, $event['event']);
        $this->assertSame([], $event['data']);
    }
}
