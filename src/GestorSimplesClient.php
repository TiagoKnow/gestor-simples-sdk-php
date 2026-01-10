<?php

namespace GestorSimples;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GestorSimples\Exceptions\ApiException;
use GestorSimples\Exceptions\AuthenticationException;
use GestorSimples\Exceptions\ValidationException;
use GestorSimples\Resources\ClientResource;
use GestorSimples\Resources\FinancialEntryResource;

class GestorSimplesClient
{
    private Client $httpClient;
    private string $baseUrl;
    private ?string $apiToken = null;
    private array $defaultHeaders = [];

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? 'https://app.gestorsimples.com.br/api/';
        $this->apiToken = $config['api_token'] ?? null;

        if (empty($this->apiToken)) {
            throw new \InvalidArgumentException('API token is required');
        }

        $this->defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'GestorSimples-PHP-SDK/1.0',
        ];

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $config['timeout'] ?? 30,
            'headers' => $this->defaultHeaders,
            'http_errors' => false, // Lidamos com erros manualmente
        ]);
    }

    public function request(string $method, string $endpoint, array $data = []): array
    {
        $options = [];

        if (!empty($data)) {
            if ($method === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }
        }

        try {
            $response = $this->httpClient->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return $body ?? [];
            }

            $this->handleError($statusCode, $body ?? []);

        } catch (RequestException $e) {
            throw new ApiException('Network error: ' . $e->getMessage(), [], 0, $e);
        }
    }

    private function handleError(int $statusCode, array $body): void
    {
        $message = $body['message'] ?? 'An error occurred';
        $errors = $body['errors'] ?? [];

        switch ($statusCode) {
            case 400:
                throw new ApiException($message, $errors, 400);
            case 401:
                throw new AuthenticationException($message);
            case 403:
                throw new ApiException('Forbidden: ' . $message, $errors, 403);
            case 404:
                throw new ApiException('Resource not found: ' . $message, $errors, 404);
            case 422:
                throw new ValidationException($errors, $message);
            case 429:
                throw new ApiException('Rate limit exceeded: ' . $message, $errors, 429);
            case 500:
                throw new ApiException('Server error: ' . $message, $errors, 500);
            default:
                throw new ApiException("HTTP {$statusCode}: " . $message, $errors, $statusCode);
        }
    }

    // Métodos para recursos específicos
    public function clients(): ClientResource
    {
        return new ClientResource($this);
    }

    public function financialEntries(): FinancialEntryResource
    {
        return new FinancialEntryResource($this);
    }

    // Métodos diretos (para conveniência)
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $data): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    public function put(string $endpoint, array $data): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }
}