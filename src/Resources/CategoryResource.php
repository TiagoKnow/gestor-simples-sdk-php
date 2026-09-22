<?php

namespace GestorSimples\Resources;

use GestorSimples\GestorSimplesClient;

/**
 * Categorias de lançamento — `/v1/categories`.
 *
 * Só leitura: a API cria categoria sozinha quando um lançamento chega com
 * `category` por nome, então um integrador raramente precisa desta lista —
 * ela serve para quem prefere mandar `category_id` explícito.
 */
class CategoryResource
{
    public function __construct(private GestorSimplesClient $client) {}

    public function all(): array
    {
        return $this->client->get('categories');
    }

    /**
     * Procura pelo nome (comparação sem diferenciar maiúsculas).
     */
    public function findByName(string $name, ?string $kind = null): ?array
    {
        $target = mb_strtolower(trim($name));

        foreach ($this->all()['data'] ?? [] as $category) {
            if (mb_strtolower((string) ($category['name'] ?? '')) !== $target) {
                continue;
            }

            if ($kind !== null && ($category['kind'] ?? null) !== $kind) {
                continue;
            }

            return $category;
        }

        return null;
    }
}
