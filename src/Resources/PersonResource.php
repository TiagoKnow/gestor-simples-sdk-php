<?php

namespace GestorSimples\Resources;

use GestorSimples\Exceptions\ApiException;
use GestorSimples\GestorSimplesClient;

/**
 * Clientes e fornecedores — `/v1/people`.
 *
 * Os ids são ULID (string), nunca inteiros: a API não expõe id sequencial.
 *
 * Formato de retorno, uniforme em todo o SDK: método que devolve **um**
 * recurso entrega o recurso já desembrulhado; método de **lista** entrega o
 * envelope completo, com `data` e `meta` da paginação.
 */
class PersonResource
{
    public function __construct(private GestorSimplesClient $client) {}

    /**
     * Lista cadastros.
     *
     * @param  array{type?: string, search?: string, per_page?: int}  $params
     *                                                                        type: customer | supplier | both
     */
    public function all(array $params = []): array
    {
        return $this->client->get('people', $params);
    }

    public function find(string $id): ?array
    {
        try {
            return $this->client->get("people/{$id}")['data'] ?? null;
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Cria um cadastro. O documento é validado pelos dígitos verificadores e
     * é único por empresa — CPF, CNPJ ou CNPJ alfanumérico, com ou sem máscara.
     */
    public function create(array $data): array
    {
        return $this->client->post('people', $data)['data'] ?? [];
    }

    /**
     * Procura pelo documento. A busca da API aceita documento entre os
     * campos pesquisáveis; devolve null quando não há correspondência exata.
     */
    public function findByDocument(string $document): ?array
    {
        $digits = preg_replace('/[^0-9A-Za-z]/', '', $document) ?? '';

        if ($digits === '') {
            return null;
        }

        $response = $this->all(['search' => $digits, 'per_page' => 100]);

        foreach ($response['data'] ?? [] as $person) {
            if (($person['document'] ?? null) === strtoupper($digits)) {
                return $person;
            }
        }

        return null;
    }

    /**
     * Cria o cadastro só se ainda não existir, devolvendo o existente quando
     * já houver. É o caminho de um integrador que conhece o cliente pelo
     * documento e não guarda o id do GestorSimples.
     */
    public function findOrCreateByDocument(string $document, array $data): array
    {
        return $this->findByDocument($document)
            ?? $this->create(['document' => $document] + $data);
    }
}
