<?php

use Aws\S3\S3Client;

// Start-up report for the hosting log: whether the mail relay, the database and the file buckets
// answer from this container. Reads the service's environment only and prints no host, account,
// key or password.
$say = fn (string $text) => fwrite(STDERR, '[elancer] '.$text.PHP_EOL);

$host = (string) getenv('MAIL_HOST');
$port = (int) getenv('MAIL_PORT');
if ($host === '' || $port === 0) {
    $say('mail relay: MAIL_HOST or MAIL_PORT is not set');
} else {
    $socket = @fsockopen($host, $port, $code, $error, 10);
    $greeting = $socket ? (string) fgets($socket, 512) : '';
    if ($socket) {
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }
    $say('mail relay on port '.$port.': '.(str_starts_with($greeting, '220') ? 'reachable' : 'NOT reachable'.($socket ? '' : ' ('.$error.')')));
}

if (getenv('DB_CONNECTION') !== 'pgsql') {
    $say('database: DB_CONNECTION is not pgsql');
} else {
    $secrets = array_filter([(string) getenv('DB_HOST'), (string) getenv('DB_USERNAME'), (string) getenv('DB_PASSWORD')]);
    try {
        $pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;sslrootcert=%s;connect_timeout=15', getenv('DB_HOST'), getenv('DB_PORT') ?: '5432',
            getenv('DB_DATABASE'), getenv('DB_SSLMODE') ?: 'verify-full', getenv('DB_SSLROOTCERT')), (string) getenv('DB_USERNAME'), (string) getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = $pdo->prepare('SELECT count(*) FROM information_schema.tables WHERE table_schema = ?');
        $tables->execute([getenv('DB_SEARCH_PATH') ?: 'elancer']);
        $say('database: reachable over '.(getenv('DB_SSLMODE') ?: 'verify-full').', '.$tables->fetchColumn().' tables visible');
    } catch (Throwable $error) {
        $say('database: NOT reachable ('.str_replace($secrets, '<hidden>', $error->getMessage()).')');
    }
}

$storage = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_ENDPOINT', 'AWS_BUCKET', 'AWS_IDENTITY_BUCKET'];
$unset = array_values(array_filter($storage, fn (string $name) => (string) getenv($name) === ''));
if (getenv('UPLOADS_DRIVER') !== 's3') {
    $say('file storage: UPLOADS_DRIVER is not s3, so uploaded files stay in this container and are lost when it restarts');
} elseif ($unset) {
    $say('file storage: '.implode(', ', $unset).' not set');
} else {
    require __DIR__.'/../vendor/autoload.php';
    $secrets = [(string) getenv('AWS_ACCESS_KEY_ID'), (string) getenv('AWS_SECRET_ACCESS_KEY'), (string) getenv('AWS_ENDPOINT'), (string) parse_url((string) getenv('AWS_ENDPOINT'), PHP_URL_HOST)];
    try {
        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) getenv('AWS_DEFAULT_REGION'),
            'endpoint' => (string) getenv('AWS_ENDPOINT'),
            'use_path_style_endpoint' => filter_var(getenv('AWS_USE_PATH_STYLE_ENDPOINT'), FILTER_VALIDATE_BOOL),
            'credentials' => ['key' => $secrets[0], 'secret' => $secrets[1]],
            'http' => ['connect_timeout' => 10, 'timeout' => 15],
        ]);
        $client->headBucket(['Bucket' => (string) getenv('AWS_BUCKET')]);
        $client->headBucket(['Bucket' => (string) getenv('AWS_IDENTITY_BUCKET')]);
        $say('file storage: both buckets reachable');
    } catch (Throwable $error) {
        $say('file storage: NOT reachable ('.substr(str_replace($secrets, '<hidden>', (string) preg_replace('/\s+/', ' ', $error->getMessage())), 0, 300).')');
    }
}
