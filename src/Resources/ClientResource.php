<?php

namespace GestorSimples\Resources;

use GestorSimples\GestorSimplesClient;

class ClientResource
{
    private GestorSimplesClient $client;

    public function __construct(GestorSimplesClient $client)
    {
        $this->client = $client;
    }

    /**
     * Lista todos os clientes
     */
    public function all(array $params = []): array
    {
        return $this->client->get('clients', $params);
    }

    /**
     * Busca um cliente pelo documento
     */
    public function findByDocument(string $document): ?array
    {
        try {
            return $this->client->get("clients/{$document}");
        } catch (\GestorSimples\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Cria um novo cliente
     */
    public function create(array $data): array
    {
        return $this->client->post('clients', $data);
    }

    /**
     * Atualiza um cliente pelo documento
     */
    public function update(string $document, array $data): array
    {
        return $this->client->put("clients/{$document}", $data);
    }

    /**
     * Busca ou cria um cliente
     */
    public function findOrCreate(string $document, array $data): array
    {
        $client = $this->findByDocument($document);

        if ($client) {
            return $client;
        }

        return $this->create(array_merge($data, ['document' => $document]));
    }

    /**
     * Busca clientes por parâmetros
     */
    public function search(array $criteria): array
    {
        return $this->all($criteria);
    }
}