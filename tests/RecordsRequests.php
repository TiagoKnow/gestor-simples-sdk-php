<?php

namespace GestorSimples\Tests;

use GestorSimples\GestorSimplesClient;
use GuzzleHttp\Psr7\Request;

/**
 * Monta um cliente cujo transporte é falso, guardando as requisições feitas.
 *
 * O que estes testes protegem é o **contrato**: caminho, verbo e nomes de
 * parâmetro precisam bater com a API v1 do GestorSimples. Foi exatamente aí
 * que o SDK anterior se perdeu — ele falava com o admin legado.
 */
trait RecordsRequests
{
    /** @var list<Request> */
    private array $sent = [];

    /**
     * @param  list<array{0: int, 1: array}>  $responses  [status, corpo]
     */
    private function client(array $responses = [[200, ['data' => []]]]): GestorSimplesClient
    {
        $this->sent = [];

        $queue = array_map(
            fn (array $response) => new \GuzzleHttp\Psr7\Response(
                $response[0],
                ['Content-Type' => 'application/json'],
                json_encode($response[1])
            ),
            $responses
        );

        $mock = new \GuzzleHttp\Handler\MockHandler($queue);
        $stack = \GuzzleHttp\HandlerStack::create($mock);
        $stack->push(\GuzzleHttp\Middleware::history($this->sent));

        return new GestorSimplesClient([
            'api_token' => 'gsk_teste',
            'base_url' => 'https://exemplo.test/api/v1/',
            'handler' => $stack,
        ]);
    }

    private function lastRequest(): Request
    {
        return $this->sent[count($this->sent) - 1]['request'];
    }

    private function lastPath(): string
    {
        return $this->lastRequest()->getUri()->getPath();
    }

    private function lastQuery(): array
    {
        parse_str($this->lastRequest()->getUri()->getQuery(), $query);

        return $query;
    }

    private function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true) ?? [];
    }
}
