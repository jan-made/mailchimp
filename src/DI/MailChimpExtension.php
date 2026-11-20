<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\DI;

use Composer;
use GuzzleHttp;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator\Literal;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use stdClass;
use Tracy\ILogger;
use Trejjam\MailChimp;
use function sprintf;

/**
 * Inspired by
 * - https://github.com/zbycz/mailchimp-v3-php/blob/master/MailchimpService.php
 * - https://github.com/Kdyby/CsobPaymentGateway
 *
 * @property-read stdClass $config
 */
final class MailChimpExtension extends CompilerExtension
{
    private const findDataCenter = true;
    private const apiUrlTemplate = 'https://%s.api.mailchimp.com/%s/';
    private const tracyLoggerService = 'tracy.logger';

    public function getConfigSchema() : Schema
    {
        return Expect::structure([
            'findDataCenter' => Expect::bool()->default(self::findDataCenter),
            'apiUrlTemplate' => Expect::string()->default(self::apiUrlTemplate),
            'apiUrl' => Expect::string(),
            'apiKey' => Expect::string(),
            'lists' => Expect::arrayOf(
                Expect::string(), // mailchimp_list_id from https://<dc>.api.mailchimp.com/playground
                Expect::string() // name
            ),
            'segments' => Expect::anyOf(
                Expect::arrayOf(Expect::arrayOf(Expect::string())),
                Expect::arrayOf(Expect::arrayOf(Expect::int()))
            )->default([]),
            'http' => Expect::structure([
                'clientFactory' => Expect::anyOf(Expect::string(), Expect::array(), Expect::type(Statement::class))->nullable(),
                'caChain' => Expect::anyOf(Expect::string(), Expect::type(Statement::class))->nullable(),
                /** @phpstan-ignore method.notFound (Expect::array() returns Type which has dynamic()) */
                'client' => Expect::array()->default([])->dynamic(),
            ]),
        ])->before(function (mixed $config) {
            assert(is_array($config));
            if (($config['findDataCenter'] ?? self::findDataCenter) === true) {
                $config['apiUrl'] = $config['apiUrlTemplate'] ?? self::apiUrlTemplate;
            }

            return $config;
        })->assert(function (mixed $config) : bool {
            assert($config instanceof stdClass);
            foreach (array_keys((array) $config->segments) as $listName) {
                Validators::assertField((array) $config->lists, $listName);
            }

            return true;
        });
    }

    public function loadConfiguration() : void
    {
        $http = $this->config->http;
        if ($http->caChain === null) {
            $http->caChain = Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        }

        if ($http->client instanceof Literal) {
            $http->client = new Literal(
                "array_merge(['verify' => '{$http->caChain}'], {$http->client})"
            );
        }
        elseif ($http->caChain !== null && !array_key_exists('verify', $http->client)) {
            $http->client['verify'] = $http->caChain;
        }

        if ($this->config->findDataCenter === true) {
            // http://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3#resources
            $accountDataCenter = Strings::match($this->config->apiKey, '~-(us(?:\d+))$~');
            assert($accountDataCenter !== null);
            $this->config->apiUrl = sprintf($this->config->apiUrlTemplate, $accountDataCenter[1], MailChimp\Request::VERSION);
        }
    }

    public function beforeCompile() : void
    {
        parent::beforeCompile();

        $builder = $this->getContainerBuilder();

        $http = $this->config->http;

        if ($http->clientFactory !== null) {
            $httpClient = $this->registerFactory(
                'http.client',
                GuzzleHttp\Client::class,
                $http->clientFactory
            );
        }
        else {
            $httpClient = $builder->addDefinition($this->prefix('http.client'))->setType(GuzzleHttp\Client::class);
        }

        $httpClient
            ->setArguments([
                'config' => $http->client,
            ])
            ->setAutowired(false);

        $builder->addDefinition($this->prefix('request'))
            ->setType(MailChimp\Request::class)
            ->setArguments(
                [
                    'httpClient' => $this->prefix('@http.client'),
                    'apiUrl' => $this->config->apiUrl,
                    'apiKey' => $this->config->apiKey,
                ]
            );

        $builder->addDefinition($this->prefix('context'))
            ->setType(MailChimp\Context::class);
        $builder->addDefinition($this->prefix('group.root'))
            ->setType(MailChimp\Group\Root::class);
        $builder->addDefinition($this->prefix('group.lists'))
            ->setType(MailChimp\Group\Lists::class);

        if ($builder->getByType(MailChimp\Logger\IExceptionLogger::class) === null) {
            $exceptionLogger = $builder->addDefinition($this->prefix('exceptionLogger'))
                ->setType(MailChimp\Logger\IExceptionLogger::class);

            $tracyLogger = $this->findTracyLogger();
            if ($tracyLogger !== null) {
                $exceptionLogger->setFactory(MailChimp\Logger\TracyExceptionLogger::class, [new Reference($tracyLogger)]);
            }
            else {
                $exceptionLogger->setFactory(MailChimp\Logger\NullExceptionLogger::class);
            }
        }

        $builder->addDefinition($this->prefix('lists'))
            ->setType(MailChimp\Lists::class)
            ->setArguments(
                [
                    'lists' => $this->config->lists,
                ]
            );

        $builder->addDefinition($this->prefix('segments'))
            ->setType(MailChimp\Segments::class)
            ->setArguments(
                [
                    'segments' => $this->config->segments,
                ]
            );
    }

    /**
     * Of several autowired Tracy loggers, only the one of the Tracy extension is used.
     */
    private function findTracyLogger() : ?string
    {
        if (!interface_exists(ILogger::class)) {
            return null;
        }

        $builder = $this->getContainerBuilder();

        try {
            return $builder->getByType(ILogger::class);
        }
        /** @phpstan-ignore catch.neverThrown (ContainerBuilder::getByType() does not declare what its autowiring throws) */
        catch (ServiceCreationException) {
            return $builder->hasDefinition(self::tracyLoggerService) ? self::tracyLoggerService : null;
        }
    }

    /**
     * @param class-string $type
     */
    private function registerFactory(string $name, string $type, string|array|Statement $factory) : ServiceDefinition
    {
        $builder = $this->getContainerBuilder();

        if (is_string($factory) && str_starts_with($factory, '@')) {
            $factoryDefinition = $builder->addDefinition($this->prefix($name));

            $factoryDefinition->setFactory($factory);
        }
        else {
            $this->loadDefinitionsFromConfig([
                $name => $factory,
            ]);

            $factoryDefinition = $builder->getDefinition($this->prefix($name));
        }

        assert($factoryDefinition instanceof ServiceDefinition);

        $factoryDefinition->setType($type);

        return $factoryDefinition;
    }
}
