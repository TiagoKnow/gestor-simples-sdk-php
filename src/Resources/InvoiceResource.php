<?php

namespace GestorSimples\Resources;

use GestorSimples\Exceptions\ApiException;
use GestorSimples\GestorSimplesClient;

/**
 * Notas fiscais de serviço — `/v1/invoices`.
 *
 * Caso âncora: o gateway confirmou o pagamento e o SaaS pede a nota. Uma
 * chamada só cuida do tomador (achado ou criado pelo documento), do
 * recebimento no contas a receber e da emissão na fila.
 *
 * A emissão é assíncrona: a nota volta `queued` e muda de estado depois
 * (`authorized` | `error`). Para saber quando, cadastre um webhook
 * (`GestorSimples\Webhooks\Webhook`) ou consulte com `find()`.
 *
 * Formato de retorno, uniforme em todo o SDK: método que devolve **um**
 * recurso entrega o recurso já desembrulhado; método de **lista** entrega o
 * envelope completo, com `data` e `meta` da paginação.
 */
class InvoiceResource
{
    public function __construct(private GestorSimplesClient $client) {}

    /**
     * Pede a nota de um pagamento.
     *
     * `external_id` é a chave estável do pagamento no SaaS e torna a chamada
     * idempotente: repetir (webhook do gateway reentregue, retry do job)
     * devolve a nota original — nunca emite outra, mesmo que o corpo mude.
     *
     * Nota que não pode sair (tomador sem endereço, configuração fiscal
     * incompleta) volta com `status: "error"` e o motivo em `error`; o
     * recebimento é lançado igual. Corrija e use `retry()`.
     *
     * @param  array{
     *     external_id: string,
     *     description: string,
     *     amount: string,
     *     person: array{document: string, name: string, email?: string, phone?: string, zip_code?: string, street?: string, number?: string, complement?: string, district?: string, city?: string, state?: string, city_ibge_code?: string},
     *     competence_date?: string,
     *     service_code?: string,
     *     entry?: array{category?: string, paid?: bool, due_date?: string, notes?: string},
     * }  $data
     */
    public function issue(array $data): array
    {
        return $this->client->post('invoices', $data)['data'] ?? [];
    }

    public function find(string $id): ?array
    {
        try {
            return $this->client->get("invoices/{$id}")['data'] ?? null;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * A nota de um pagamento pela chave do SaaS — para quem não guardou o id
     * do GestorSimples. Null quando ainda não foi pedida.
     */
    public function findByExternalId(string $externalId): ?array
    {
        $response = $this->all(['external_id' => $externalId, 'per_page' => 1]);

        return $response['data'][0] ?? null;
    }

    /**
     * @param  array{external_id?: string, status?: string, date_from?: string, date_to?: string, per_page?: int}  $params
     *                                                                                                                       status: queued | processing | authorized | error | cancelling | cancelled · datas da competência
     */
    public function all(array $params = []): array
    {
        return $this->client->get('invoices', $params);
    }

    /**
     * Devolve à fila uma nota com erro. Os dados de `$person` substituem os
     * do cadastro (é a correção explícita: o endereço que faltava); o
     * documento não muda — o tomador da nota é fixo. Nota fora de `error`
     * responde 409.
     *
     * @param  array{name?: string, email?: string, phone?: string, zip_code?: string, street?: string, number?: string, complement?: string, district?: string, city?: string, state?: string, city_ibge_code?: string}  $person
     */
    public function retry(string $id, array $person = []): array
    {
        return $this->client->post("invoices/{$id}/retry", $person === [] ? [] : ['person' => $person])['data'] ?? [];
    }
}
