<?php

// Start-up report for the hosting log: whether the mail relay and the database answer from this
// container. Reads the service's environment only and prints no host, account or password.
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
