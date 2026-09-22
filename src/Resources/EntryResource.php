<?php

namespace GestorSimples\Resources;

use GestorSimples\Exceptions\ApiException;
use GestorSimples\GestorSimplesClient;

/**
 * Lançamentos financeiros — `/v1/entries`.
 *
 * Caso âncora: o gateway confirma um pagamento e o integrador registra a
 * entrada aqui. Valores são strings decimais com ponto ("149.90"); datas são
 * `YYYY-MM-DD`; ids são ULID.
 *
 * Formato de retorno, uniforme em todo o SDK: método que devolve **um**
 * recurso entrega o recurso já desembrulhado; método de **lista** entrega o
 * envelope completo, com `data` e `meta` da paginação.
 */
class EntryResource
{
    public function __construct(private GestorSimplesClient $client) {}

    /**
     * @param  array{kind?: string, status?: string, date_from?: string, date_to?: string, per_page?: int}  $params
     *                                                                                                              kind: income | expense · status: pending | paid | overdue
     */
    public function all(array $params = []): array
    {
        return $this->client->get('entries', $params);
    }

    public function find(string $id): ?array
    {
        try {
            return $this->client->get("entries/{$id}")['data'] ?? null;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Cria um lançamento.
     *
     * `category` por nome cria ou reusa a categoria daquele tipo, então cada
     * sistema integrado usa a própria sem precisar de configuração prévia.
     */
    public function create(array $data): array
    {
        return $this->client->post('entries', $data)['data'] ?? [];
    }

    /**
     * Atalho para o caso mais comum: pagamento confirmado no gateway vira
     * conta a receber já quitada.
     */
    public function recordPayment(
        string $description,
        string $amount,
        string $date,
        array $extra = [],
    ): array {
        return $this->create([
            'kind' => 'income',
            'description' => $description,
            'amount' => $amount,
            'due_date' => $date,
            'paid' => true,
            ...$extra,
        ]);
    }

    /**
     * Marca como pago ou estorna.
     */
    public function setPaid(string $id, bool $paid = true): array
    {
        return $this->client->patch("entries/{$id}/payment", ['paid' => $paid])['data'] ?? [];
    }

    /**
     * @param  array{kind?: string, status?: string, per_page?: int}  $extra
     */
    public function betweenDueDates(string $from, string $to, array $extra = []): array
    {
        return $this->all([...$extra, 'date_from' => $from, 'date_to' => $to]);
    }

    public function income(array $params = []): array
    {
        return $this->all([...$params, 'kind' => 'income']);
    }

    public function expenses(array $params = []): array
    {
        return $this->all([...$params, 'kind' => 'expense']);
    }

    public function pending(array $params = []): array
    {
        return $this->all([...$params, 'status' => 'pending']);
    }

    public function paid(array $params = []): array
    {
        return $this->all([...$params, 'status' => 'paid']);
    }

    public function overdue(array $params = []): array
    {
        return $this->all([...$params, 'status' => 'overdue']);
    }
}
