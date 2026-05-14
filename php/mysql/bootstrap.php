<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/php/lib/autoload.php';

use Hlquery\Client;

/**
 * @return Client
 */
function mysql_hlqueryclient(array $config): Client
{
    $options = [];
    if (!empty($config['HLQUERY_TOKEN'])) {
        $options['token'] = $config['HLQUERY_TOKEN'];
        $options['auth_method'] = $config['HLQUERY_AUTH_METHOD'] ?? 'bearer';
    }

    return new Client(
        $config['HLQUERY_URL'] ?? 'http://127.0.0.1:9200',
        $options
    );
}
