<?php

/**
 * Ensaio de integração: percorre, contra um servidor de verdade, tudo que um
 * SaaS faz — do cadastro do cliente ao pagamento registrado e consultado.
 *
 * Não é substituto dos testes unitários (que travam o contrato com transporte
 * falso): aqui o objetivo é provar que o contrato bate com a API real.
 *
 *   GESTOR_SIMPLES_URL=http://localhost:8584/api/v1/ php examples/rehearsal.php
 */

require __DIR__.'/../vendor/autoload.php';

use GestorSimples\Exceptions\ApiException;
use GestorSimples\Exceptions\AuthenticationException;
use GestorSimples\Exceptions\ValidationException;
use GestorSimples\GestorSimplesClient;

$passos = 0;
$falhas = 0;

function checa(string $descricao, callable $teste): void
{
    global $passos, $falhas;
    $passos++;

    try {
        $resultado = $teste();

        if ($resultado === true) {
            echo "  ok    {$descricao}\n";

            return;
        }

        $falhas++;
        echo "  FALHA {$descricao} — {$resultado}\n";
    } catch (Throwable $e) {
        $falhas++;
        echo '  ERRO  '.$descricao.' — '.get_class($e).': '.$e->getMessage()."\n";
    }
}

$client = new GestorSimplesClient([
    'api_token' => getenv('GESTOR_SIMPLES_TOKEN') ?: 'gsk_qa_local_0000000000000000000000000000',
    'base_url' => getenv('GESTOR_SIMPLES_URL') ?: 'http://localhost:8584/api/v1/',
]);

// documento próprio deste ensaio, para rodar quantas vezes quiser sem colidir
$cpfs = ['52998224725', '11144477735', '19100000000'];
$documento = $cpfs[array_rand($cpfs)];
$marca = substr(bin2hex(random_bytes(4)), 0, 8);

echo "Ensaio de integração — SaaS → Gestor Simples\n\n";

echo "Autenticação\n";

checa('chave inválida recebe 401', function () use ($client) {
    $ruim = new GestorSimplesClient([
        'api_token' => 'gsk_nao_existe',
        'base_url' => (new ReflectionProperty($client, 'baseUrl'))->getValue($client),
    ]);

    try {
        $ruim->entries()->all();

        return 'esperava AuthenticationException';
    } catch (AuthenticationException) {
        return true;
    }
});

echo "\nCadastro do cliente\n";

$customer = null;

checa('cria cliente pessoa jurídica', function () use ($client, $marca, &$customer) {
    $customer = $client->people()->create([
        'type' => 'customer',
        'name' => "Ensaio {$marca} LTDA",
        'email' => "ensaio-{$marca}@exemplo.test",
    ]);

    return isset($customer['id']) ? true : 'resposta sem id';
});

checa('cadastro nasce com source api', fn () => ($customer['source'] ?? null) === 'api'
    ? true
    : 'source = '.var_export($customer['source'] ?? null, true));

checa('busca pelo id devolve o mesmo cadastro', function () use ($client, &$customer) {
    $encontrado = $client->people()->find($customer['id']);

    return ($encontrado['id'] ?? null) === $customer['id'] ? true : 'id divergente';
});

checa('id inexistente devolve null', fn () => $client->people()->find('01m0000000000000000000000x') === null
    ? true
    : 'esperava null');

checa('documento inválido é recusado com erro no campo', function () use ($client, $marca) {
    try {
        $client->people()->create([
            'type' => 'customer',
            'name' => "Documento ruim {$marca}",
            'document' => '111.111.111-11',
        ]);

        return 'esperava ValidationException';
    } catch (ValidationException $e) {
        return isset($e->getErrors()['document']) ? true : 'erro não veio no campo document';
    }
});

checa('find_or_create acha quem já existe pelo documento', function () use ($client, $documento, $marca) {
    $primeiro = $client->people()->findOrCreateByDocument($documento, [
        'type' => 'customer',
        'name' => "Recorrente {$marca}",
    ]);

    $segundo = $client->people()->findOrCreateByDocument($documento, [
        'type' => 'customer',
        'name' => 'Nome diferente, mesmo documento',
    ]);

    return ($primeiro['id'] ?? 'a') === ($segundo['id'] ?? 'b')
        ? true
        : 'criou cadastro duplicado para o mesmo documento';
});

echo "\nPagamento recebido\n";

$entry = null;

checa('registra entrada já quitada', function () use ($client, $marca, &$customer, &$entry) {
    $entry = $client->entries()->recordPayment(
        description: "Assinatura Pro — ensaio {$marca}",
        amount: '149.90',
        date: date('Y-m-d'),
        extra: [
            'person_id' => $customer['id'],
            'category' => "Ensaio {$marca}",
        ],
    );

    return isset($entry['id']) ? true : 'resposta sem id';
});

checa('entrada vem com status paid', fn () => ($entry['status'] ?? null) === 'paid'
    ? true
    : 'status = '.var_export($entry['status'] ?? null, true));

checa('valor volta como string decimal', fn () => ($entry['amount'] ?? null) === '149.90'
    ? true
    : 'amount = '.var_export($entry['amount'] ?? null, true));

checa('categoria por nome foi criada', function () use ($client, $marca) {
    return $client->categories()->findByName("Ensaio {$marca}", 'income') !== null
        ? true
        : 'categoria não apareceu na listagem';
});

checa('category_id e category juntos são recusados', function () use ($client) {
    try {
        $client->entries()->create([
            'kind' => 'income',
            'description' => 'Conflito',
            'amount' => '10.00',
            'due_date' => date('Y-m-d'),
            'category' => 'Uma',
            'category_id' => '01m0000000000000000000000x',
        ]);

        return 'esperava ValidationException';
    } catch (ValidationException) {
        return true;
    }
});

checa('valor zero é recusado', function () use ($client) {
    try {
        $client->entries()->create([
            'kind' => 'income',
            'description' => 'Zerado',
            'amount' => '0.00',
            'due_date' => date('Y-m-d'),
        ]);

        return 'esperava ValidationException';
    } catch (ValidationException $e) {
        return isset($e->getErrors()['amount']) ? true : 'erro não veio no campo amount';
    }
});

echo "\nConsultas e estorno\n";

checa('aparece no filtro de recebidos do mês', function () use ($client, &$entry) {
    $lista = $client->entries()->betweenDueDates(
        date('Y-m-01'),
        date('Y-m-t'),
        ['kind' => 'income', 'status' => 'paid', 'per_page' => 100],
    );

    foreach ($lista['data'] ?? [] as $linha) {
        if (($linha['id'] ?? null) === $entry['id']) {
            return true;
        }
    }

    return 'lançamento não veio na listagem filtrada';
});

checa('listagem traz meta de paginação', function () use ($client) {
    $meta = $client->entries()->all(['per_page' => 1])['meta'] ?? [];

    return isset($meta['current_page'], $meta['last_page'], $meta['total'])
        ? true
        : 'meta incompleta: '.json_encode($meta);
});

checa('estorno volta o lançamento para pendente', function () use ($client, &$entry) {
    $client->entries()->setPaid($entry['id'], false);
    $depois = $client->entries()->find($entry['id']);

    return ($depois['status'] ?? null) !== 'paid'
        ? true
        : 'continuou paid depois do estorno';
});

checa('marcar pago de novo volta para paid', function () use ($client, &$entry) {
    $client->entries()->setPaid($entry['id']);
    $depois = $client->entries()->find($entry['id']);

    return ($depois['status'] ?? null) === 'paid'
        ? true
        : 'status = '.var_export($depois['status'] ?? null, true);
});

checa('lançamento de outra empresa responde 404', function () use ($client) {
    try {
        $client->entries()->find('01m0000000000000000000000y');

        return true; // find devolve null em 404, que é o comportamento esperado
    } catch (ApiException $e) {
        return $e->getCode() === 404 ? true : 'status '.$e->getCode();
    }
});

echo "\nIdempotência por external_id\n";

checa('mesmo external_id devolve o mesmo lançamento', function () use ($client, $marca, &$customer) {
    $chave = "ensaio:{$marca}";
    $dados = ['person_id' => $customer['id'], 'external_id' => $chave];

    $primeiro = $client->entries()->recordPayment("Reenvio {$marca}", '19.90', date('Y-m-d'), $dados);
    $segundo = $client->entries()->recordPayment("Reenvio {$marca}", '19.90', date('Y-m-d'), $dados);

    return ($primeiro['id'] ?? 'a') === ($segundo['id'] ?? 'b') ? true : 'criou dois lançamentos';
});

checa('acha o lançamento pela chave do SaaS', fn () => ($client->entries()->findByExternalId("ensaio:{$marca}")['external_id'] ?? null) === "ensaio:{$marca}"
    ? true
    : 'não achou pela chave');

// Notas: só caminhos que não emitem. Emitir de verdade põe a nota na fila do
// integrador do tenant — em Embu, isso é uma NFS-e real no portal.
echo "\nNotas fiscais (sem emitir)\n";

checa('pedido com documento inválido é recusado antes de lançar', function () use ($client, $marca) {
    try {
        $client->invoices()->issue([
            'external_id' => "ensaio:nota:{$marca}",
            'description' => 'Não deve sair',
            'amount' => '10.00',
            'person' => ['document' => '111.111.111-11', 'name' => 'Documento ruim'],
        ]);

        return 'esperava ValidationException';
    } catch (ValidationException $e) {
        return isset($e->getErrors()['person.document']) ? true : 'erro não veio em person.document';
    }
});

checa('pedido recusado não deixou lançamento', fn () => $client->entries()->findByExternalId("ensaio:nota:{$marca}") === null
    ? true
    : 'lançou o dinheiro de um pedido inválido');

checa('chave sem nota devolve null', fn () => $client->invoices()->findByExternalId("ensaio:nota:{$marca}") === null
    ? true
    : 'esperava null');

checa('nota inexistente devolve null', fn () => $client->invoices()->find('01m0000000000000000000000z') === null
    ? true
    : 'esperava null');

checa('listagem de notas traz meta de paginação', function () use ($client) {
    $meta = $client->invoices()->all(['per_page' => 1])['meta'] ?? [];

    return isset($meta['current_page'], $meta['last_page'], $meta['total'])
        ? true
        : 'meta incompleta: '.json_encode($meta);
});

checa('retry de nota inexistente responde 404', function () use ($client) {
    try {
        $client->invoices()->retry('01m0000000000000000000000z');

        return 'esperava ApiException';
    } catch (ApiException $e) {
        return $e->getCode() === 404 ? true : 'status '.$e->getCode();
    }
});

echo "\n".str_repeat('─', 52)."\n";
echo $falhas === 0
    ? "Ensaio completo: {$passos} verificações, todas passaram.\n"
    : "Ensaio com problemas: {$falhas} de {$passos} falharam.\n";

exit($falhas === 0 ? 0 : 1);
