<?php

declare(strict_types=1);

namespace Phenogram\Framework\Tests\Feature;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phenogram\Bindings\Types\Update;
use Phenogram\Framework\Handler\UpdateHandlerInterface;
use Phenogram\Framework\TelegramBot;
use Phenogram\Framework\Tests\Mock\MockTelegramBotApiClient;
use Phenogram\Framework\Tests\TestCase;

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
        self::markTestIncomplete('This test is not working as expected. Event loop is not stopping');

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

        $bot->addHandler(function (Update $update, TelegramBot $bot) use (&$counter) {
            ++$counter;

            $bot->stop();
        });
        $bot->addHandler(fn () => throw new \Exception('test1'));

        $bot->run();

        $this->assertEquals(1, $counter);
    }
}
