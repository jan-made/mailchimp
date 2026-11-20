<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Cases\Tests;

use Composer\CaBundle\CaBundle;
use GuzzleHttp;
use Nette\DI\Compiler;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Tester\Assert;
use Tester\TestCase;
use Tracy\Debugger;
use Tracy\ILogger;
use Trejjam\MailChimp\DI\MailChimpExtension;
use Trejjam\MailChimp\Logger\NullExceptionLogger;
use Trejjam\MailChimp\Logger\TracyExceptionLogger;

$container = require __DIR__ . '/../bootstrap.php';

final class DITest extends TestCase
{
    private const NAME = 'trejjam.mailchimp';

    public function testConfig() : void
    {
        $mailChimpExtension = new MailChimpExtension();

        $compiler = new Compiler();
        $compiler->addExtension(self::NAME, $mailChimpExtension);
        $compiler->addConfig([
            self::NAME => [
                'apiKey' => 'someApiKey123-us11',
                'lists' => [
                    'newsletter' => 'foo123',
                    'user' => '123',
                ],
                'segments' => [
                    'newsletter' => [
                        'segmentA' => 2,
                    ],
                ],
            ],
        ]);
        $compiler->processExtensions();

        $mailChimpConfig = (array)$mailChimpExtension->getConfig();

        Assert::same(true, $mailChimpConfig['findDataCenter']);
        Assert::same('https://us11.api.mailchimp.com/3.0/', $mailChimpConfig['apiUrl']);
        Assert::same('someApiKey123-us11', $mailChimpConfig['apiKey']);
        Assert::same(
            [
                'newsletter' => 'foo123',
                'user' => '123',
            ],
            $mailChimpConfig['lists']
        );
        Assert::same(
            [
                'newsletter' => [
                    'segmentA' => 2,
                ],
            ],
            $mailChimpConfig['segments']
        );
        Assert::null($mailChimpConfig['http']->clientFactory);
        Assert::same([
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ], $mailChimpConfig['http']->client);
    }

    public function testGuzzleFactory() : void
    {
        $mailChimpExtension = new MailChimpExtension();

        $compiler = new Compiler();
        $compiler->addExtension(self::NAME, $mailChimpExtension);
        $compiler->addConfig(
            [
                self::NAME => [
                    'apiKey' => 'someApiKey123-us11',
                    'http' => [
                        'clientFactory' => '@guzzleClassFactory',
                    ],
                ],
            ]
        );
        $containerBuilder = $compiler->getContainerBuilder();

        $guzzleClassFactory = $containerBuilder->addDefinition('guzzleClassFactory');
        $guzzleClassFactory->setType(GuzzleHttp\Client::class);

        $compiler->processExtensions();

        $mailChimpExtension->beforeCompile();

        $mailChimpConfig = (array)$mailChimpExtension->getConfig();

        Assert::same(true, $mailChimpConfig['findDataCenter']);
        Assert::same('https://us11.api.mailchimp.com/3.0/', $mailChimpConfig['apiUrl']);
        Assert::same('someApiKey123-us11', $mailChimpConfig['apiKey']);
        Assert::same([], $mailChimpConfig['lists']);
        Assert::same([], $mailChimpConfig['segments']);
        Assert::same('@guzzleClassFactory', $mailChimpConfig['http']->clientFactory);
        Assert::same([
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ], $mailChimpConfig['http']->client);

        $httpClient = $containerBuilder->getDefinition(self::NAME . '.http.client');

        Assert::type(ServiceDefinition::class, $httpClient);

        $httpClientServiceDefinition = $httpClient->getFactory()->getEntity();
        if ($httpClientServiceDefinition instanceof Reference) {
            Assert::same('guzzleClassFactory', $httpClientServiceDefinition->getValue());
        }
        else {
            // pre Nette 3.0 compatibility
            Assert::same('@guzzleClassFactory', $httpClientServiceDefinition);
        }
    }

    public function testGuzzleFactory2() : void
    {
        $mailChimpExtension = new MailChimpExtension();

        $compiler = new Compiler();
        $compiler->addExtension(self::NAME, $mailChimpExtension);
        $compiler->addConfig(
            [
                self::NAME => [
                    'apiKey' => 'someApiKey123-us11',
                    'http' => [
                        'clientFactory' => 'GuzzleHttp\Client([])',
                    ],
                ],
            ]
        );

        $compiler->processExtensions();

        $mailChimpExtension->beforeCompile();

        $mailChimpConfig = (array)$mailChimpExtension->getConfig();

        Assert::same(true, $mailChimpConfig['findDataCenter']);
        Assert::same('https://us11.api.mailchimp.com/3.0/', $mailChimpConfig['apiUrl']);
        Assert::same('someApiKey123-us11', $mailChimpConfig['apiKey']);
        Assert::same([], $mailChimpConfig['lists']);
        Assert::same([], $mailChimpConfig['segments']);
        Assert::same('GuzzleHttp\Client([])', $mailChimpConfig['http']->clientFactory);
        Assert::same([
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ], $mailChimpConfig['http']->client);

        $containerBuilder = $compiler->getContainerBuilder();
        $httpClient = $containerBuilder->getDefinition(self::NAME . '.http.client');

        Assert::type(ServiceDefinition::class, $httpClient);

        $httpClientServiceDefinition = $httpClient->getFactory()->getEntity();
        Assert::same('GuzzleHttp\Client([])', $httpClientServiceDefinition);
    }
    public function testExceptionLoggerWithoutTracy() : void
    {
        $compiler = $this->createCompiler();
        $compiler->processExtensions();

        $this->getExtension($compiler)->beforeCompile();

        Assert::same(NullExceptionLogger::class, $this->getExceptionLoggerEntity($compiler));
    }

    public function testExceptionLoggerWithTracy() : void
    {
        $compiler = $this->createCompiler();
        $compiler->getContainerBuilder()
            ->addDefinition('tracy.logger')
            ->setType(ILogger::class)
            ->setFactory([Debugger::class, 'getLogger']);
        $compiler->processExtensions();

        $this->getExtension($compiler)->beforeCompile();

        Assert::same(TracyExceptionLogger::class, $this->getExceptionLoggerEntity($compiler));
        Assert::same('tracy.logger', $this->getExceptionLoggerReference($compiler));
    }

    public function testExceptionLoggerWithSeveralTracyLoggers() : void
    {
        $compiler = $this->createCompiler();
        foreach (['applicationLogger', 'tracy.logger'] as $name) {
            $compiler->getContainerBuilder()
                ->addDefinition($name)
                ->setType(ILogger::class)
                ->setFactory([Debugger::class, 'getLogger']);
        }
        $compiler->processExtensions();

        $this->getExtension($compiler)->beforeCompile();

        Assert::same(TracyExceptionLogger::class, $this->getExceptionLoggerEntity($compiler));
        Assert::same('tracy.logger', $this->getExceptionLoggerReference($compiler));
    }

    public function testExceptionLoggerWithSeveralUnknownTracyLoggers() : void
    {
        $compiler = $this->createCompiler();
        foreach (['applicationLogger', 'secondApplicationLogger'] as $name) {
            $compiler->getContainerBuilder()
                ->addDefinition($name)
                ->setType(ILogger::class)
                ->setFactory([Debugger::class, 'getLogger']);
        }
        $compiler->processExtensions();

        $this->getExtension($compiler)->beforeCompile();

        Assert::same(NullExceptionLogger::class, $this->getExceptionLoggerEntity($compiler));
    }

    public function testExceptionLoggerFromApplication() : void
    {
        $compiler = $this->createCompiler();
        $compiler->getContainerBuilder()
            ->addDefinition('applicationLogger')
            ->setType(NullExceptionLogger::class);
        $compiler->processExtensions();

        $this->getExtension($compiler)->beforeCompile();

        Assert::false($compiler->getContainerBuilder()->hasDefinition(self::NAME . '.exceptionLogger'));
    }

    private function createCompiler() : Compiler
    {
        $compiler = new Compiler();
        $compiler->addExtension(self::NAME, new MailChimpExtension());
        $compiler->addConfig([
            self::NAME => [
                'apiKey' => 'someApiKey123-us11',
            ],
        ]);

        return $compiler;
    }

    private function getExtension(Compiler $compiler) : MailChimpExtension
    {
        $extension = $compiler->getExtensions(MailChimpExtension::class)[self::NAME];
        assert($extension instanceof MailChimpExtension);

        return $extension;
    }

    private function getExceptionLoggerEntity(Compiler $compiler) : mixed
    {
        $definition = $compiler->getContainerBuilder()->getDefinition(self::NAME . '.exceptionLogger');
        assert($definition instanceof ServiceDefinition);

        return $definition->getFactory()->getEntity();
    }

    private function getExceptionLoggerReference(Compiler $compiler) : string
    {
        $definition = $compiler->getContainerBuilder()->getDefinition(self::NAME . '.exceptionLogger');
        assert($definition instanceof ServiceDefinition);
        $reference = $definition->getFactory()->arguments[0];
        assert($reference instanceof Reference);

        return $reference->getValue();
    }
}

$test = new DITest();
$test->run();
