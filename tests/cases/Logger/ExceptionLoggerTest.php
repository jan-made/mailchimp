<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Tests\Cases\Logger;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nette\DI\Container;
use Tester\Assert;
use Tester\TestCase;
use Throwable;
use Tracy\ILogger;
use Trejjam\MailChimp\Entity\Lists\Member\MemberItem;
use Trejjam\MailChimp\Exception\MemberNotFoundException;
use Trejjam\MailChimp\Group\Lists;
use Trejjam\MailChimp\Logger\IExceptionLogger;
use Trejjam\MailChimp\Logger\TracyExceptionLogger;
use Trejjam\MailChimp\Request;

$container = require __DIR__ . '/../../bootstrap.php';

final class ExceptionLoggerTest extends TestCase
{
    public function __construct(
        private readonly Container $container
    ) {
    }

    public function testContainerUsesTracy() : void
    {
        Assert::type(TracyExceptionLogger::class, $this->container->getByType(IExceptionLogger::class));
    }

    public function testTracyExceptionLogger() : void
    {
        $tracyLogger = new class() implements ILogger {
            /**
             * @var list<mixed>
             */
            public array $logged = [];

            public function log(mixed $value, mixed $level = self::INFO) : void
            {
                $this->logged[] = $value;
            }
        };
        $exception = new \RuntimeException('rejected');

        (new TracyExceptionLogger($tracyLogger))->log($exception);

        Assert::same([$exception], $tracyLogger->logged);
    }

    public function testAddSegmentMemberLogsRejectedRequest() : void
    {
        $exceptionLogger = new class() implements IExceptionLogger {
            /**
             * @var list<Throwable>
             */
            public array $logged = [];

            public function log(Throwable $exception) : void
            {
                $this->logged[] = $exception;
            }
        };
        $httpClient = new Client([
            'handler' => HandlerStack::create(new MockHandler([new Response(400, [], '{}')])),
        ]);
        $groupLists = new Lists(new Request($httpClient, 'https://mailchimp.invalid/3.0/', 'someApiKey123-us11'), $exceptionLogger);
        $memberItem = MemberItem::create('rejected@trejbal.land', 'foo123', MemberItem::STATUS_SUBSCRIBED);

        $memberNotFoundException = Assert::exception(
            fn () => $groupLists->addSegmentMember(123, $memberItem),
            MemberNotFoundException::class
        );

        Assert::count(1, $exceptionLogger->logged);
        Assert::type(ClientException::class, $exceptionLogger->logged[0]);
        Assert::same($memberNotFoundException?->getPrevious(), $exceptionLogger->logged[0]);
    }
}

$test = new ExceptionLoggerTest($container);
$test->run();
