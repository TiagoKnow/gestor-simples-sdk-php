<?php

namespace GestorSimples\Tests;

use GestorSimples\Exceptions\AuthenticationException;
use GestorSimples\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contrato do SDK contra a API v1 do GestorSimples (docs/api.md).
 *
 * Cada teste tranca caminho, verbo e nome de parâmetro. A versão anterior do
 * SDK falava com o admin legado (`financial_entry`, `start_date`, `type`,
 * ids inteiros) e teria dado 404 em tudo — sem testes, ninguém percebia.
 */
class ContractTest extends TestCase
{
    use RecordsRequests;

    #[Test]
    public function autentica_com_bearer_e_pede_json(): void
    {
        $this->client()->people()->all();

        $request = $this->lastRequest();
        $this->assertSame('Bearer gsk_teste', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    #[Test]
    public function exige_token_na_construcao(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new \GestorSimples\GestorSimplesClient([]);
    }

    /**
     * A base sem a v1 era o que sobrava do admin legado: o SDK apontava para
     * o domínio novo e caminhos antigos. E `app.` nem existe no DNS — a
     * produção é o domínio raiz.
     */
    #[Test]
    public function base_padrao_aponta_para_a_v1(): void
    {
        $client = new \GestorSimples\GestorSimplesClient(['api_token' => 'gsk_x']);

        $this->assertSame(
            'https://gestorsimples.com.br/api/v1/',
            (new \ReflectionProperty($client, 'baseUrl'))->getValue($client)
        );
    }

    // --- cadastros -------------------------------------------------------

    #[Test]
    public function lista_cadastros_em_people(): void
    {
        $this->client()->people()->all(['type' => 'customer']);

        $this->assertSame('/api/v1/people', $this->lastPath());
        $this->assertSame(['type' => 'customer'], $this->lastQuery());
    }

    #[Test]
    public function busca_cadastro_por_ulid(): void
    {
        $person = $this->client([[200, ['data' => ['id' => '01k9']]]])
            ->people()->find('01k9x7bdmf6h12ckjs234vgn3w');

        $this->assertSame('01k9', $person['id']); // recurso desembrulhado

        $this->assertSame('/api/v1/people/01k9x7bdmf6h12ckjs234vgn3w', $this->lastPath());
    }

    #[Test]
    public function cadastro_inexistente_devolve_null_em_vez_de_excecao(): void
    {
        $person = $this->client([[404, ['message' => 'Not found']]])
            ->people()->find('01k9inexistente');

        $this->assertNull($person);
    }

    #[Test]
    public function busca_por_documento_compara_so_os_digitos(): void
    {
        $client = $this->client([[200, ['data' => [
            ['id' => '01k1', 'document' => '11222333000181'],
        ]]]]);

        $person = $client->people()->findByDocument('11.222.333/0001-81');

        $this->assertSame('01k1', $person['id']);
        $this->assertSame('11222333000181', $this->lastQuery()['search']);
    }

    #[Test]
    public function busca_por_documento_ignora_correspondencia_parcial(): void
    {
        $client = $this->client([[200, ['data' => [
            ['id' => '01k1', 'document' => '11222333000199'],
        ]]]]);

        $this->assertNull($client->people()->findByDocument('11222333000181'));
    }

    /**
     * Caminho de um integrador que só conhece o cliente pelo documento e não
     * guarda id do GestorSimples: não pode duplicar cadastro a cada cobrança.
     */
    #[Test]
    public function find_or_create_reusa_o_cadastro_existente(): void
    {
        $client = $this->client([[200, ['data' => [
            ['id' => '01k1', 'document' => '11222333000181'],
        ]]]]);

        $person = $client->people()->findOrCreateByDocument('11222333000181', [
            'type' => 'customer',
            'name' => 'Café Aurora',
        ]);

        $this->assertSame('01k1', $person['id']);
        $this->assertCount(1, $this->sent); // não chegou a criar
    }

    #[Test]
    public function find_or_create_cria_quando_nao_existe(): void
    {
        $client = $this->client([
            [200, ['data' => []]],
            [201, ['data' => ['id' => '01k2']]],
        ]);

        $person = $client->people()->findOrCreateByDocument('11222333000181', [
            'type' => 'customer',
            'name' => 'Café Aurora',
        ]);

        $this->assertSame('01k2', $person['id']);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('11222333000181', $this->lastBody()['document']);
    }

    // --- lançamentos -----------------------------------------------------

    #[Test]
    public function lista_lancamentos_em_entries(): void
    {
        $this->client()->entries()->all();

        $this->assertSame('/api/v1/entries', $this->lastPath());
    }

    /**
     * O legado usava `type` e `paid`; a v1 usa `kind` e `status`.
     */
    #[Test]
    public function usa_kind_e_status_e_nao_type_e_paid(): void
    {
        $this->client()->entries()->income(['status' => 'pending']);

        $query = $this->lastQuery();
        $this->assertSame('income', $query['kind']);
        $this->assertSame('pending', $query['status']);
        $this->assertArrayNotHasKey('type', $query);
        $this->assertArrayNotHasKey('paid', $query);
    }

    /**
     * O legado usava `start_date`/`end_date`.
     */
    #[Test]
    public function filtra_periodo_por_date_from_e_date_to(): void
    {
        $this->client()->entries()->betweenDueDates('2026-09-01', '2026-09-30');

        $query = $this->lastQuery();
        $this->assertSame('2026-09-01', $query['date_from']);
        $this->assertSame('2026-09-30', $query['date_to']);
    }

    #[Test]
    public function atalho_de_pagamento_monta_entrada_ja_quitada(): void
    {
        $this->client([[201, ['data' => ['id' => '01k3']]]])
            ->entries()->recordPayment('Assinatura Pro', '149.90', '2026-09-05', [
                'category' => 'MeetBox',
            ]);

        $body = $this->lastBody();
        $this->assertSame('income', $body['kind']);
        $this->assertTrue($body['paid']);
        $this->assertSame('149.90', $body['amount']);
        $this->assertSame('2026-09-05', $body['due_date']);
        $this->assertSame('MeetBox', $body['category']);
    }

    #[Test]
    public function marcar_pagamento_usa_patch_no_sub_recurso(): void
    {
        $this->client([[200, ['data' => []]]])->entries()->setPaid('01k3');

        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/entries/01k3/payment', $this->lastPath());
        $this->assertTrue($this->lastBody()['paid']);
    }

    #[Test]
    public function estorno_manda_paid_falso(): void
    {
        $this->client([[200, ['data' => []]]])->entries()->setPaid('01k3', false);

        $this->assertFalse($this->lastBody()['paid']);
    }

    #[Test]
    public function acha_lancamento_pela_chave_do_saas(): void
    {
        $entry = $this->client([[200, ['data' => [['id' => '01k4']]]]])
            ->entries()->findByExternalId('consultafast:credit:abc');

        $this->assertSame('01k4', $entry['id']);
        $this->assertSame('consultafast:credit:abc', $this->lastQuery()['external_id']);
    }

    #[Test]
    public function lancamento_sem_a_chave_devolve_null(): void
    {
        $this->assertNull($this->client([[200, ['data' => []]]])
            ->entries()->findByExternalId('consultafast:credit:nada'));
    }

    // --- categorias e erros ----------------------------------------------

    #[Test]
    public function categoria_por_nome_respeita_o_tipo(): void
    {
        $client = $this->client([[200, ['data' => [
            ['id' => '01c1', 'name' => 'Assinaturas', 'kind' => 'expense'],
            ['id' => '01c2', 'name' => 'Assinaturas', 'kind' => 'income'],
        ]]]]);

        $this->assertSame('01c2', $client->categories()->findByName('assinaturas', 'income')['id']);
    }

    #[Test]
    public function erro_de_validacao_vira_excecao_com_os_campos(): void
    {
        $client = $this->client([[422, [
            'message' => 'Dados inválidos',
            'errors' => ['amount' => ['O valor precisa ser maior que zero.']],
        ]]]);

        try {
            $client->entries()->create(['kind' => 'income']);
            $this->fail('esperava ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->getErrors());
        }
    }

    #[Test]
    public function chave_invalida_vira_excecao_de_autenticacao(): void
    {
        $client = $this->client([[401, ['message' => 'Unauthenticated.']]]);

        $this->expectException(AuthenticationException::class);

        $client->entries()->all();
    }
}
