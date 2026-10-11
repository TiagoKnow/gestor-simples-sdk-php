# SDK PHP do Gestor Simples

SDK oficial para a **API v1** do [Gestor Simples](https://gestorsimples.com.br).

> **Versão 2 — reescrita.** A v1 do SDK falava com o admin legado
> (`financial_entry`, `start_date`, `type`, ids inteiros) e não funciona
> contra a API do Gestor Simples. Veja *Migrando da v1* no fim.

## Instalação

O pacote não está no Packagist. Declare o repositório e instale:

```bash
composer config repositories.gestor-simples vcs https://github.com/tiagoknow/gestor-simples-sdk-php.git
composer require tiagoknow/gestor-simples-sdk-php:^2.1
```

## Configuração

A chave (`gsk_…`) é gerada em **Configurações → Chaves de API** e aparece em
claro uma única vez. Ela dá acesso total à empresa dona dela.

```php
use GestorSimples\GestorSimplesClient;

$client = new GestorSimplesClient([
    'api_token' => getenv('GESTOR_SIMPLES_TOKEN'),
    // opcional; o padrão já é a produção
    'base_url'  => 'https://gestorsimples.com.br/api/v1/',
    'timeout'   => 30,
]);
```

Em desenvolvimento, aponte para o servidor local:

```php
'base_url' => 'http://localhost:8584/api/v1/',
```

## Convenções

- **Ids são ULID** (string), nunca inteiros.
- **Dinheiro é string decimal com ponto**: `"149.90"`.
- **Datas são `YYYY-MM-DD`**.
- Método que devolve **um** recurso entrega o recurso já desembrulhado;
  método de **lista** entrega o envelope com `data` e `meta`.
- Limite de 60 requisições por minuto por chave.

## Uso

### Emitir a nota de um pagamento

O caso âncora: o gateway confirmou, o SaaS pede a NFS-e. Uma chamada só — o
Gestor Simples acha ou cria o tomador pelo documento, lança o recebimento no
contas a receber e põe a nota na fila.

```php
$invoice = $client->invoices()->issue([
    'external_id' => "consultafast:credit:{$payment->id}", // chave estável do pagamento
    'description' => 'ConsultaFast - Total de 100 créditos',
    'amount'      => '30.00',
    'person' => [
        'document' => '11.222.333/0001-81',
        'name'     => 'Café Aurora LTDA',
        'email'    => 'financeiro@cafeaurora.test',
        'zip_code' => '60160-250', 'street' => 'R Pereira Valente', 'number' => '578',
        'district' => 'Meireles', 'city' => 'Fortaleza', 'state' => 'CE',
    ],
    'entry' => ['category' => 'ConsultaFast'],
]);

$invoice['status']; // "queued" — a emissão é assíncrona
```

- **Idempotente por `external_id`**: repetir a chamada (webhook do gateway
  reentregue, retry do job) devolve a mesma nota — nunca emite outra.
- **A nota nunca derruba o dinheiro**: se não dá para emitir (tomador sem os
  dados que o município exige), a nota volta `status: "error"` com o motivo em
  `error` e o recebimento é lançado igual. Corrija e peça de novo:

```php
$client->invoices()->retry($invoice['id'], ['zip_code' => '60160250', 'number' => '578']);
```

Estado e documentos:

```php
$client->invoices()->find($id);                     // null se não existir
$client->invoices()->findByExternalId($chave);      // null se ainda não pediu
$client->invoices()->all(['status' => 'error', 'date_from' => '2026-10-01']);

$invoice['documents']['pdf_url'];  // link assinado, vale 60 min
$invoice['documents']['xml_url'];  // quando o XML oficial chegou (official_document)
```

Os links são para o cliente final baixar sem login. **Não grave a URL**:
peça a nota de novo quando precisar mostrar o arquivo.

### Webhooks

Em vez de consultar, cadastre um endpoint em **Configurações → Chaves de API
→ Webhooks**. O segredo `whsec_…` aparece uma única vez.

```php
use GestorSimples\Exceptions\InvalidSignatureException;
use GestorSimples\Webhooks\Webhook;

try {
    $event = Webhook::parse(
        $request->getContent(),                       // corpo CRU
        $request->header(Webhook::SIGNATURE_HEADER),
        env('GESTOR_SIMPLES_WEBHOOK_SECRET'),
    );
} catch (InvalidSignatureException) {
    abort(401);
}

match ($event['event']) {
    Webhook::INVOICE_AUTHORIZED        => /* PDF em $event['data']['documents']['pdf_url'] */,
    Webhook::INVOICE_OFFICIAL_DOCUMENT => /* XML oficial chegou */,
    Webhook::INVOICE_ERROR             => /* motivo em $event['data']['error'] */,
    Webhook::INVOICE_CANCELLED         => /* cancelada */,
    default                            => null, // ping
};
```

`$event['data']` é a nota, igual ao `find()`. A mesma entrega pode chegar mais
de uma vez — ignore `$event['id']` já processado. Responda 2xx rápido; o
Gestor Simples tenta de novo por ~7 h. Exemplo completo em
`examples/webhook_receiver.php`.

### Registrar só o pagamento

Para quem não emite nota, o lançamento direto:

```php
$customer = $client->people()->findOrCreateByDocument('11.222.333/0001-81', [
    'type'  => 'customer',
    'name'  => 'Café Aurora LTDA',
    'email' => 'financeiro@cafeaurora.test',
]);

$entry = $client->entries()->recordPayment(
    description: 'Assinatura Pro — set/2026',
    amount: '149.90',
    date: date('Y-m-d'),
    extra: [
        'person_id'   => $customer['id'],
        'category'    => 'MeetBox',
        'external_id' => "meetbox:invoice:{$payment->id}", // idempotência
    ],
);

$client->entries()->findByExternalId("meetbox:invoice:{$payment->id}");
```

`category` por **nome** cria ou reusa a categoria daquele tipo — cada sistema
integrado usa a própria sem configuração prévia. Quem preferir explícito manda
`category_id`; os dois juntos são rejeitados.

### Clientes e fornecedores

```php
$client->people()->all(['type' => 'customer', 'search' => 'aurora']);
$client->people()->find($id);                        // null se não existir
$client->people()->findByDocument('529.982.247-25'); // null se não existir
$client->people()->create([
    'type' => 'customer',
    'name' => 'Maria Souza',
    'document' => '529.982.247-25',
]);
```

O documento é validado pelos dígitos verificadores (CPF, CNPJ e CNPJ
alfanumérico) e é único por empresa.

### Lançamentos

```php
$client->entries()->all(['kind' => 'income', 'status' => 'pending']);
$client->entries()->find($id);
$client->entries()->create([...]);
$client->entries()->setPaid($id);          // marca pago
$client->entries()->setPaid($id, false);   // estorna

$client->entries()->income();
$client->entries()->expenses();
$client->entries()->pending();
$client->entries()->paid();
$client->entries()->overdue();
$client->entries()->betweenDueDates('2026-09-01', '2026-09-30');
```

### Categorias

```php
$client->categories()->all();
$client->categories()->findByName('MeetBox', 'income');
```

## Erros

```php
use GestorSimples\Exceptions\ApiException;
use GestorSimples\Exceptions\AuthenticationException;
use GestorSimples\Exceptions\ValidationException;

try {
    $client->entries()->create($dados);
} catch (ValidationException $e) {
    // 422 — $e->getErrors() traz as mensagens por campo
} catch (AuthenticationException $e) {
    // 401 — chave ausente, inválida ou revogada
} catch (ApiException $e) {
    // demais (404, 429, 5xx) — $e->getCode() tem o status
}
```

Recurso de outra empresa responde **404**, não 403: a chave nunca revela que
o registro existe.

## Novidades da 2.1

- `invoices()`: `issue()`, `find()`, `findByExternalId()`, `all()`, `retry()`.
- `Webhooks\Webhook::parse()` / `verify()` para o receptor dos avisos.
- `entries()->findByExternalId()` e `external_id` documentado no `create()`.
- **Correção**: a base padrão era `https://app.gestorsimples.com.br`, que não
  existe; agora é `https://gestorsimples.com.br/api/v1/`. Quem passava
  `base_url` explícito não é afetado.

## Migrando da v1

| v1 (admin legado) | v2 (API do Gestor Simples) |
|---|---|
| `$client->clients()` | `$client->people()` |
| `$client->financialEntries()` | `$client->entries()` |
| `find(int $id)` | `find(string $ulid)` |
| `update($id, $dados)` | não existe; use `setPaid()` |
| `filterByPeriod($ini, $fim)` | `betweenDueDates($ini, $fim)` |
| `findByType('income')` | `income()` |
| `findPending()` / `findPaid()` | `pending()` / `paid()` |
| base `/api/` | base `/api/v1/` |

## Testes

```bash
composer install
composer test
```

Os testes travam o **contrato** — caminho, verbo e nome de parâmetro de cada
chamada. Foi a ausência deles que deixou a v1 divergir da API sem ninguém ver.

## Licença

MIT.
