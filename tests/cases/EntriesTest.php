<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Tests\Cases;

use GuzzleHttp;
use Nette\Utils\Json;
use Tester;
use Tester\Assert;
use Trejjam\MailChimp;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Tester\Environment::setup();

final class EntriesTest extends Tester\TestCase
{
    public function testIterateTypedEntries() : void
    {
        $handler = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
            new GuzzleHttp\Psr7\Response(200, [], Json::encode([
                'lists' => [
                    [
                        'id' => 'a1',
                        'name' => 'First',
                        '_links' => [[
                            'rel' => 'self',
                        ]],
                    ],
                    [
                        'id' => 'b2',
                        'name' => 'Second',
                        '_links' => [],
                    ],
                ],
                'total_items' => 2,
                '_links' => [[
                    'rel' => 'self',
                ], [
                    'rel' => 'parent',
                ]],
            ])),
        ]));
        $request = new MailChimp\Request(new GuzzleHttp\Client([
            'handler' => $handler,
        ]), 'https://us1.api.mailchimp.com/3.0/', 'key-us1');

        $lists = $request->getTyped('lists', MailChimp\Entity\Lists\Lists::class);

        $items = $lists->getLists();
        Assert::count(2, $items);
        Assert::type(MailChimp\Entity\Lists\ListItem::class, $items[0]);
        Assert::same('Second', $items[1]->name);
        Assert::count(1, $items[0]->getLinks());
        Assert::count(2, $lists->getLinks());
    }

    public function testIterateAEntityEntries() : void
    {
        $handler = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([
            new GuzzleHttp\Psr7\Response(200, [], Json::encode([
                'members' => [
                    [
                        'id' => 'm1',
                        'list_id' => 'a1',
                        '_links' => [],
                    ],
                ],
                'list_id' => 'a1',
                'total_items' => 1,
                '_links' => [],
            ])),
        ]));
        $request = new MailChimp\Request(new GuzzleHttp\Client([
            'handler' => $handler,
        ]), 'https://us1.api.mailchimp.com/3.0/', 'key-us1');

        $members = $request->getTyped('lists/a1/members', MailChimp\Entity\Lists\Member\Lists::class);

        $items = $members->getMembers();
        Assert::count(1, $items);
        Assert::type(MailChimp\Entity\Lists\Member\MemberItem::class, $items[0]);
        Assert::same('a1', $items[0]->list_id);
    }
}

(new EntriesTest())->run();
