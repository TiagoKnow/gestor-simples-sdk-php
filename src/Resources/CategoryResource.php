<?php

namespace GestorSimples\Resources;

use GestorSimples\GestorSimplesClient;

class CategoryResource
{
    private GestorSimplesClient $client;

    public function __construct(GestorSimplesClient $client)
    {
        $this->client = $client;
    }

    /**
     * Lista todas as categorias
     */
    public function all(array $params = []): array
    {
        return $this->client->get('categories', $params);
    }

    /**
     * Busca uma categoria pelo ID
     */
    public function find(int $id): ?array
    {
        try {
            return $this->client->get("categories/{$id}");
        } catch (\GestorSimples\Exceptions\ApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Cria uma nova categoria
     */
    public function create(array $data): array
    {
        // Validações básicas
        if (!isset($data['name']) || empty($data['name'])) {
            throw new \InvalidArgumentException('O campo "name" é obrigatório');
        }

        if (!isset($data['type']) || !in_array($data['type'], ['income', 'expense'])) {
            throw new \InvalidArgumentException('O campo "type" deve ser "income" ou "expense"');
        }

        return $this->client->post('categories', $data);
    }

    /**
     * Atualiza uma categoria pelo ID
     */
    public function update(int $id, array $data): array
    {
        if (isset($data['type']) && !in_array($data['type'], ['income', 'expense'])) {
            throw new \InvalidArgumentException('O campo "type" deve ser "income" ou "expense"');
        }

        return $this->client->put("categories/{$id}", $data);
    }

    /**
     * Busca categorias por tipo
     */
    public function findByType(string $type): array
    {
        if (!in_array($type, ['income', 'expense'])) {
            throw new \InvalidArgumentException('O tipo deve ser "income" ou "expense"');
        }

        return $this->all(['type' => $type]);
    }

    /**
     * Busca categorias de receita
     */
    public function incomeCategories(): array
    {
        return $this->findByType('income');
    }

    /**
     * Busca categorias de despesa
     */
    public function expenseCategories(): array
    {
        return $this->findByType('expense');
    }

    /**
     * Busca categoria pelo nome
     */
    public function findByName(string $name, string $type = null): ?array
    {
        $params = ['name' => $name];
        if ($type) {
            $params['type'] = $type;
        }

        $categories = $this->all($params);

        if (!empty($categories)) {
            return $categories[0] ?? null;
        }

        return null;
    }

    /**
     * Busca ou cria uma categoria
     */
    public function findOrCreate(string $name, string $type, string $color = null): array
    {
        $category = $this->findByName($name, $type);

        if ($category) {
            return $category;
        }

        $data = [
            'name' => $name,
            'type' => $type
        ];

        if ($color) {
            $data['color'] = $color;
        }

        return $this->create($data);
    }

    /**
     * Sugere uma cor baseada no tipo
     */
    public function suggestColor(string $type): string
    {
        return match($type) {
            'income' => '#10B981', // Verde
            'expense' => '#EF4444', // Vermelho
            default => '#6B7280' // Cinza
        };
    }

    /**
     * Cria uma categoria com cor sugerida
     */
    public function createWithSuggestedColor(string $name, string $type): array
    {
        return $this->create([
            'name' => $name,
            'type' => $type,
            'color' => $this->suggestColor($type)
        ]);
    }
}