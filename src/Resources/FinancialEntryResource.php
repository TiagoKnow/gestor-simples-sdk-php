<?php

namespace GestorSimples\Resources;

use GestorSimples\GestorSimplesClient;

class FinancialEntryResource
{
    private GestorSimplesClient $client;

    public function __construct(GestorSimplesClient $client)
    {
        $this->client = $client;
    }

    /**
     * Lista todos os lançamentos financeiros
     */
    public function all(array $params = []): array
    {
        return $this->client->get('financial_entry', $params);
    }

    /**
     * Busca um lançamento financeiro pelo ID
     */
    public function find(int $id): ?array
    {
        try {
            return $this->client->get("financial_entry/{$id}");
        } catch (\GestorSimples\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Cria um novo lançamento financeiro
     */
    public function create(array $data): array
    {
        return $this->client->post('financial_entry', $data);
    }

    /**
     * Atualiza um lançamento financeiro pelo ID
     */
    public function update(int $id, array $data): array
    {
        return $this->client->put("financial_entry/{$id}", $data);
    }

    /**
     * Filtra lançamentos por período
     */
    public function filterByPeriod(string $startDate, string $endDate, array $additionalParams = []): array
    {
        $params = array_merge($additionalParams, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $this->all($params);
    }

    /**
     * Busca lançamentos por tipo (income/expense)
     */
    public function findByType(string $type, array $params = []): array
    {
        return $this->all(array_merge($params, ['type' => $type]));
    }

    /**
     * Busca lançamentos pendentes
     */
    public function findPending(array $params = []): array
    {
        return $this->all(array_merge($params, ['paid' => false]));
    }

    /**
     * Busca lançamentos pagos
     */
    public function findPaid(array $params = []): array
    {
        return $this->all(array_merge($params, ['paid' => true]));
    }
}