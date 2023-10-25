<?php

namespace Shanginn\TelegramBotApiFramework;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiFramework\Exception\TelegramBotApiException;

use function React\Promise\reject;

final class TelegramBotApiClient implements TelegramBotApiClientInterface
{
    private LoggerInterface $logger;
    private Browser $client;

    public function __construct(
        private readonly string $token,
        private readonly string $apiUrl = 'https://api.telegram.org',
        LoggerInterface $logger = null,
    ) {
        $this->client = new Browser();
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function sendRequest(string $method, string $json): PromiseInterface
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        $this->logger->debug("Request [$method]", [
            'json' => $json,
        ]);

        return $this
            ->postJson($url, $json)
            ->then(function (ResponseInterface $response) use ($method) {
                $responseContent = $response->getBody()->getContents();
                $responseData = json_decode($responseContent, true);

                $this->logger->debug("Response [$method]", [
                    'response' => $responseData,
                ]);

                if (!$responseData || !isset($responseData['ok'], $responseData['result']) || !$responseData['ok']) {
                    $this->logger->error(sprintf(
                        'Telegram API response is not successful: %s',
                        $responseContent
                    ));

                    return reject(new TelegramBotApiException(sprintf(
                        'Telegram bot API error: %s',
                        $responseData['description'] ?? 'Unknown error'
                    )));
                }

                return json_encode($responseData['result']);
            });
    }

    private function postJson(string $url, string $json): PromiseInterface
    {
        return $this->client->post($url, [
            'Content-Type' => 'application/json',
        ], $json);
    }
}
