<?php

namespace Shanginn\TelegramBotApiFramework;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use React\EventLoop\Loop;
use Shanginn\TelegramBotApiBindings\TelegramBotApi;
use Shanginn\TelegramBotApiBindings\TelegramBotApiClientInterface;
use Shanginn\TelegramBotApiBindings\Types\Update;

class TelegramBot
{
    public TelegramBotApi $api;

    // TODO: remove?
    protected TelegramBotApiClientInterface $botClient;

    /**
     * @var array<UpdateHandlerInterface>
     */
    protected array $handlers = [];

    private LoggerInterface $logger;

    public function __construct(
        protected readonly string $token,
        TelegramBotApiClientInterface $botClient = null,
        LoggerInterface $logger = null,
    ) {
        $this->botClient = $botClient ?? new TelegramBotApiClient($token);
        $this->api = new TelegramBotApi($this->botClient);
        $this->logger = $logger ?? Discover::log() ?? new NullLogger();
    }

    public function run(): void
    {
        $loop = Loop::get();
        $timeout = 15;

        $loop->addPeriodicTimer($timeout, function () use ($timeout) {
            foreach ($this->pollUpdates(timeout: $timeout) as $update) {
                $this->handleUpdate($update);
            }
        });

        $loop->run();
    }

    public function pollUpdates(
        int $offset = null,
        ?int $limit = 100,
        int $timeout = null,
        array $allowedUpdates = null,
    ): \Generator {
        $offset = $offset ?? 1;
        $timeout = $timeout ?? 15;

        $updates = $this->api->getUpdates(
            offset: $offset,
            limit: $limit,
            timeout: $timeout,
            allowedUpdates: $allowedUpdates,
        );

        foreach ($updates as $update) {
            yield $update;

            $offset = max($offset, $update->updateId + 1);
        }
    }

    public function addHandler(UpdateHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;

        return $this;
    }

    public function handleUpdate(Update $update)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($update)) {
                try {
                    $handler->handle($update, $this);
                } catch (\Throwable $e) {
                    $this->logger->error('Error while handling update', [
                        'update' => $update,
                        'handler' => get_class($handler),
                        'exception' => $e,
                    ]);
                }
            }
        }
    }
}
