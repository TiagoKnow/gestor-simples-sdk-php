<?php

namespace GestorSimples\Tests;

use GestorSimples\Exceptions\ApiException;
use GestorSimples\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contrato de `/v1/invoices` (docs/api.md do GestorSimples): o pagamento de
 * um SaaS vira recebimento + NFS-e numa chamada idempotente.
 */
class InvoiceContractTest extends TestCase
{
    use RecordsRequests;

    private const PAYMENT = [
        'external_id' => 'consultafast:credit:6721a0f0c3b1',
        'description' => 'ConsultaFast - Total de 100 créditos',
        'amount' => '30.00',
        'person' => ['document' => '11.222.333/0001-81', 'name' => 'Café Aurora LTDA'],
        'entry' => ['category' => 'ConsultaFast'],
    ];

    #[Test]
    public function pede_a_nota_com_post_em_invoices(): void
    {
        $invoice = $this->client([[201, ['data' => ['id' => '01n1', 'status' => 'queued']]]])
            ->invoices()->issue(self::PAYMENT);

        $this->assertSame('queued', $invoice['status']); // recurso desembrulhado
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/invoices', $this->lastPath());
        $this->assertSame(self::PAYMENT, $this->lastBody());
    }

    /**
     * Reenvio com a mesma chave responde 200 com a nota original: para o
     * SaaS é o mesmo retorno de uma emissão nova, sem exceção.
     */
    #[Test]
    public function reenvio_idempotente_devolve_a_nota_original(): void
    {
        $invoice = $this->client([[200, ['data' => ['id' => '01n1', 'status' => 'authorized']]]])
            ->invoices()->issue(self::PAYMENT);

        $this->assertSame('01n1', $invoice['id']);
    }

    #[Test]
    public function dados_invalidos_viram_excecao_com_os_campos(): void
    {
        $client = $this->client([[422, [
            'message' => 'Dados inválidos',
            'errors' => ['person.document' => ['CPF/CNPJ inválido.']],
        ]]]);

        try {
            $client->invoices()->issue(self::PAYMENT);
            $this->fail('esperava ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('person.document', $e->getErrors());
        }
    }

    #[Test]
    public function busca_nota_por_ulid(): void
    {
        $invoice = $this->client([[200, ['data' => ['id' => '01n1']]]])
            ->invoices()->find('01n1');

        $this->assertSame('01n1', $invoice['id']);
        $this->assertSame('/api/v1/invoices/01n1', $this->lastPath());
    }

    #[Test]
    public function nota_inexistente_devolve_null(): void
    {
        $this->assertNull($this->client([[404, ['message' => 'Not found']]])->invoices()->find('01nada'));
    }

    #[Test]
    public function acha_nota_pela_chave_do_saas(): void
    {
        $invoice = $this->client([[200, ['data' => [['id' => '01n1']], 'meta' => []]]])
            ->invoices()->findByExternalId('consultafast:credit:6721a0f0c3b1');

        $this->assertSame('01n1', $invoice['id']);
        $this->assertSame('/api/v1/invoices', $this->lastPath());
        $this->assertSame('consultafast:credit:6721a0f0c3b1', $this->lastQuery()['external_id']);
    }

    #[Test]
    public function chave_ainda_sem_nota_devolve_null(): void
    {
        $this->assertNull($this->client([[200, ['data' => [], 'meta' => []]]])
            ->invoices()->findByExternalId('consultafast:credit:nada'));
    }

    #[Test]
    public function lista_com_os_filtros_da_v1(): void
    {
        $page = $this->client([[200, ['data' => [], 'meta' => ['total' => 0]]]])
            ->invoices()->all(['status' => 'error', 'date_from' => '2026-10-01']);

        $this->assertArrayHasKey('meta', $page); // lista entrega o envelope
        $this->assertSame(['status' => 'error', 'date_from' => '2026-10-01'], $this->lastQuery());
    }

    #[Test]
    public function retry_manda_a_correcao_do_tomador(): void
    {
        $this->client([[200, ['data' => ['id' => '01n1', 'status' => 'queued']]]])
            ->invoices()->retry('01n1', ['zip_code' => '60160250', 'number' => '578']);

        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/invoices/01n1/retry', $this->lastPath());
        $this->assertSame(['person' => ['zip_code' => '60160250', 'number' => '578']], $this->lastBody());
    }

    #[Test]
    public function retry_sem_correcao_vai_sem_corpo(): void
    {
        $this->client([[200, ['data' => ['id' => '01n1']]]])->invoices()->retry('01n1');

        $this->assertSame('', (string) $this->lastRequest()->getBody());
    }

    #[Test]
    public function retry_de_nota_que_nao_esta_em_erro_vira_409(): void
    {
        $client = $this->client([[409, ['message' => 'Só notas com erro podem ser reenviadas.']]]);

        try {
            $client->invoices()->retry('01n1');
            $this->fail('esperava ApiException');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->getCode());
        }
    }
}
