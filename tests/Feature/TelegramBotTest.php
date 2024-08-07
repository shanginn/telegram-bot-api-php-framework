<?php

declare(strict_types=1);

namespace Shanginn\TelegramBotApiFramework\Tests\Feature;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Loop;
use Shanginn\TelegramBotApiBindings\Types\Update;
use Shanginn\TelegramBotApiFramework\Handler\UpdateHandlerInterface;
use Shanginn\TelegramBotApiFramework\TelegramBot;
use Shanginn\TelegramBotApiFramework\Tests\Mock\MockTelegramBotApiClient;
use Shanginn\TelegramBotApiFramework\Tests\TestCase;

use function React\Async\await;

final class TelegramBotTest extends TestCase
{
    public function testUpdateHandlersAreWorkingInPulling()
    {
        $logger = new Logger('test', [
            new StreamHandler('php://stdout'),
        ]);

        $client = new MockTelegramBotApiClient(
            1.5,
            '[]'
        );

        $updateResponse = '[{"update_id":437567765}]';
        $client->addResponse(
            $updateResponse,
            'getUpdates'
        );

        $bot = new TelegramBot(
            token: 'token',
            logger: $logger,
            botClient: $client,
        );

        $counter = 0;

        $bot->addHandler(
            new class($counter) implements UpdateHandlerInterface {
                public function __construct(
                    private int &$counter
                ) {
                }

                public function handle(Update $update, TelegramBot $bot)
                {
                    await(
                        \React\Promise\Timer\sleep(0.5),
                    );

                    ++$this->counter;

                    $bot->stop();
                }
            }
        );

        $bot->run();

        $this->assertEquals(1, $counter);
    }

    //    public function testCanHandleSingleUpdateWithoutEventLoop()
    //    {
    //        $bot = new TelegramBot(
    //            token: 'token',
    //        );
    //
    //        $counter = 0;
    //
    //        $bot->addHandler(
    //            new class($counter) implements UpdateHandlerInterface {
    //                public function __construct(
    //                    private int &$counter
    //                ) {
    //                }
    //
    //                public function handle(Update $update, TelegramBot $bot): void
    //                {
    //                    await(\React\Promise\Timer\sleep(1));
    //
    //                    ++$this->counter;
    //                }
    //            }
    //        );
    //
    //        $bot->handleUpdateSync(
    //            new Update(
    //                updateId: 1,
    //            )
    //        );
    //
    //        $this->assertEquals(1, $counter);
    //    }

    public function testExceptionInUpdateHandlerIsCaught()
    {
        $logger = new Logger('test', [
            new StreamHandler('php://stdout'),
        ]);

        $client = new MockTelegramBotApiClient(
            10,
            '[]'
        );

        $updateResponse = '[{"update_id":437567765}]';
        $client->addResponse(
            $updateResponse,
            'getUpdates'
        );

        $bot = new TelegramBot(
            token: 'token',
            logger: $logger,
            botClient: $client,
        );

        $counter = 0;

        $bot->addHandler(fn () => throw new \Exception('test1'));
        $bot->addHandler(function (Update $update, TelegramBot $bot) use (&$counter) {
            ++$counter;

            $bot->stop();
        });

        Loop::addTimer(3, fn () => $bot->stop());

        $bot->run();

        $this->assertEquals(0, $counter);
    }
}
