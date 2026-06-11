<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RealtimeTokenFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:realtime:websocket-server', description: 'Diffuse les evenements applicatifs en WebSocket.')]
final class RealtimeWebsocketServerCommand extends Command
{
    private const WEBSOCKET_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function __construct(
        private readonly Connection $connection,
        private readonly RealtimeTokenFactory $tokenFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Adresse d’écoute.', '0.0.0.0')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port WebSocket.', '8090');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $server = stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errno, $errstr);

        if ($server === false) {
            $output->writeln(sprintf('<error>Impossible de demarrer le serveur WebSocket : %s (%d)</error>', $errstr, $errno));

            return Command::FAILURE;
        }

        stream_set_blocking($server, false);
        $clients = [];
        $lastEventId = $this->currentLastEventId();
        $lastPollAt = 0.0;
        $lastCleanupAt = time();

        $output->writeln(sprintf('<info>Serveur WebSocket actif sur %s:%d</info>', $host, $port));

        while (true) {
            $client = @stream_socket_accept($server, 0);
            if (is_resource($client)) {
                stream_set_blocking($client, false);
                $clients[(int) $client] = [
                    'socket' => $client,
                    'handshake' => false,
                    'buffer' => '',
                    'userId' => null,
                    'topics' => [],
                ];
            }

            foreach ($clients as $key => &$clientState) {
                $chunk = @fread($clientState['socket'], 8192);
                if ($chunk === false || ($chunk === '' && feof($clientState['socket']))) {
                    $this->closeClient($clients, $key);
                    continue;
                }

                if ($chunk === '') {
                    continue;
                }

                $clientState['buffer'] .= $chunk;

                if ($clientState['handshake'] === false) {
                    if (!str_contains($clientState['buffer'], "\r\n\r\n")) {
                        continue;
                    }

                    $userId = $this->handshake($clientState['socket'], $clientState['buffer']);
                    if ($userId === false) {
                        $this->closeClient($clients, $key);
                        continue;
                    }

                    $clientState['handshake'] = true;
                    $clientState['buffer'] = '';
                    $clientState['userId'] = $userId;
                    $this->sendJson($clientState['socket'], [
                        'type' => 'realtime.connected',
                        'payload' => ['authenticated' => $userId !== null],
                    ]);
                    continue;
                }

                while (($frame = $this->decodeFrame($clientState['buffer'])) !== null) {
                    if ($frame['opcode'] === 8) {
                        $this->closeClient($clients, $key);
                        continue 2;
                    }

                    if ($frame['opcode'] === 9) {
                        $this->sendFrame($clientState['socket'], $frame['payload'], 10);
                        continue;
                    }

                    if ($frame['opcode'] !== 1) {
                        continue;
                    }

                    $this->handleClientMessage($clientState, $frame['payload']);
                }
            }
            unset($clientState);

            $now = microtime(true);
            if ($now - $lastPollAt >= 0.5) {
                $lastPollAt = $now;
                $lastEventId = $this->broadcastEvents($clients, $lastEventId);
            }

            if (time() - $lastCleanupAt >= 3600) {
                $lastCleanupAt = time();
                $this->cleanupEvents();
            }

            usleep(20000);
        }
    }

    private function handshake(mixed $socket, string $request): string|false|null
    {
        $lines = preg_split("/\r\n/", $request) ?: [];
        $requestLine = array_shift($lines);
        if (!is_string($requestLine) || !preg_match('/^GET\s+(\S+)\s+HTTP\/1\.[01]$/', $requestLine, $matches)) {
            return false;
        }

        $headers = [];
        foreach ($lines as $line) {
            if (!is_string($line) || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        $key = $headers['sec-websocket-key'] ?? null;
        if ($key === null || $key === '') {
            return false;
        }

        $userId = null;
        $query = parse_url($matches[1], PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            if (isset($params['token']) && is_string($params['token']) && $params['token'] !== '') {
                $userId = $this->tokenFactory->userIdFromToken($params['token']);
                if ($userId === null) {
                    @fwrite($socket, "HTTP/1.1 401 Unauthorized\r\nConnection: close\r\n\r\n");

                    return false;
                }
            }
        }

        $accept = base64_encode(sha1($key.self::WEBSOCKET_GUID, true));
        @fwrite($socket, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n");

        return $userId;
    }

    private function handleClientMessage(array &$clientState, string $payload): void
    {
        try {
            $message = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (!is_array($message) || ($message['action'] ?? null) !== 'subscribe' || !isset($message['topics']) || !is_array($message['topics'])) {
            return;
        }

        $topics = [];
        foreach ($message['topics'] as $topic) {
            if (is_string($topic) && $topic !== '' && strlen($topic) <= 255) {
                $topics[$topic] = true;
            }
        }

        $clientState['topics'] = $topics;
    }

    private function broadcastEvents(array $clients, int $lastEventId): int
    {
        try {
            $events = $this->connection->fetchAllAssociative(
                'SELECT id, type, recipient_user_id, topic, payload, created_at FROM realtime_events WHERE id > :id ORDER BY id ASC LIMIT 200',
                ['id' => $lastEventId],
            );
        } catch (\Throwable) {
            $this->connection->close();

            return $lastEventId;
        }

        foreach ($events as $event) {
            $eventId = (int) $event['id'];
            $lastEventId = max($lastEventId, $eventId);
            $recipientUserId = is_string($event['recipient_user_id'] ?? null) ? $event['recipient_user_id'] : null;
            $topic = is_string($event['topic'] ?? null) ? $event['topic'] : null;
            $payload = $this->decodePayload($event['payload'] ?? []);

            $message = [
                'id' => $eventId,
                'type' => $event['type'],
                'topic' => $topic,
                'payload' => $payload,
                'createdAt' => $event['created_at'],
            ];

            foreach ($clients as $clientState) {
                if ($clientState['handshake'] !== true) {
                    continue;
                }

                if (!$this->shouldReceive($clientState, $recipientUserId, $topic)) {
                    continue;
                }

                $this->sendJson($clientState['socket'], $message);
            }
        }

        return $lastEventId;
    }

    private function shouldReceive(array $clientState, ?string $recipientUserId, ?string $topic): bool
    {
        if ($recipientUserId !== null && $clientState['userId'] === $recipientUserId) {
            return true;
        }

        if ($recipientUserId !== null) {
            return false;
        }

        if ($topic === null) {
            return true;
        }

        return isset($clientState['topics'][$topic], $clientState['topics']['*']);
    }

    private function decodeFrame(string &$buffer): ?array
    {
        $length = strlen($buffer);
        if ($length < 2) {
            return null;
        }

        $byte1 = ord($buffer[0]);
        $byte2 = ord($buffer[1]);
        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) === 0x80;
        $payloadLength = $byte2 & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            if ($length < 4) {
                return null;
            }
            $payloadLength = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if ($length < 10) {
                return null;
            }
            $parts = unpack('Nhigh/Nlow', substr($buffer, 2, 8));
            if ($parts['high'] !== 0) {
                $buffer = '';

                return null;
            }
            $payloadLength = $parts['low'];
            $offset = 10;
        }

        $mask = '';
        if ($masked) {
            if ($length < $offset + 4) {
                return null;
            }
            $mask = substr($buffer, $offset, 4);
            $offset += 4;
        }

        if ($length < $offset + $payloadLength) {
            return null;
        }

        $payload = substr($buffer, $offset, $payloadLength);
        $buffer = substr($buffer, $offset + $payloadLength);

        if ($masked) {
            $unmasked = '';
            for ($i = 0; $i < $payloadLength; ++$i) {
                $unmasked .= $payload[$i] ^ $mask[$i % 4];
            }
            $payload = $unmasked;
        }

        return ['opcode' => $opcode, 'payload' => $payload];
    }

    private function sendJson(mixed $socket, array $message): void
    {
        try {
            $payload = json_encode($message, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $this->sendFrame($socket, $payload, 1);
    }

    private function sendFrame(mixed $socket, string $payload, int $opcode = 1): void
    {
        $length = strlen($payload);
        $head = chr(0x80 | $opcode);

        if ($length <= 125) {
            $head .= chr($length);
        } elseif ($length <= 65535) {
            $head .= chr(126).pack('n', $length);
        } else {
            $head .= chr(127).pack('NN', 0, $length);
        }

        @fwrite($socket, $head.$payload);
    }

    private function closeClient(array &$clients, int $key): void
    {
        if (isset($clients[$key]['socket']) && is_resource($clients[$key]['socket'])) {
            @fclose($clients[$key]['socket']);
        }

        unset($clients[$key]);
    }

    private function currentLastEventId(): int
    {
        try {
            return (int) ($this->connection->fetchOne('SELECT COALESCE(MAX(id), 0) FROM realtime_events') ?: 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function cleanupEvents(): void
    {
        try {
            $this->connection->executeStatement("DELETE FROM realtime_events WHERE created_at < NOW() - INTERVAL '24 hours'");
        } catch (\Throwable) {
            $this->connection->close();
        }
    }

    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
