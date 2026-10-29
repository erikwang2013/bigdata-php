<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hive;

use Erikwang2013\BigData\Driver\Hive\HiveClient;
use Erikwang2013\BigData\Driver\Hive\HiveConfig;
use Erikwang2013\BigData\Driver\Hive\HiveTransport;
use PHPUnit\Framework\TestCase;

final class HiveClientTest extends TestCase
{
    public const COLUMNS = [
        ['name' => 'id', 'type' => 'int'],
        ['name' => 'name', 'type' => 'string'],
    ];

    public function test_execute_maps_columns_and_rows(): void
    {
        $transport = new FakeTransport(HiveConfig::fromArray(['host' => 'hive.local']), [
            ['rows' => [[1, 'alice'], [2, 'bob']], 'hasMore' => false],
        ]);

        $result = (new HiveClient($transport->config, $transport))->execute('SELECT id, name FROM users');

        self::assertSame(self::COLUMNS, $result->columns());
        self::assertSame([[1, 'alice'], [2, 'bob']], $result->rows());
        self::assertSame(2, $result->count());
        self::assertSame(['SELECT id, name FROM users'], $transport->executed);
    }

    public function test_fetch_handles_multiple_batches(): void
    {
        $transport = new FakeTransport(HiveConfig::fromArray(['host' => 'hive.local']), [
            ['rows' => [[1, 'alice']], 'hasMore' => true],
            ['rows' => [[2, 'bob']], 'hasMore' => true],
            ['rows' => [[3, 'carol']], 'hasMore' => false],
        ]);

        $result = (new HiveClient($transport->config, $transport))->execute('SELECT id, name FROM users');

        self::assertSame([[1, 'alice'], [2, 'bob'], [3, 'carol']], $result->rows());
        self::assertSame(3, $transport->fetchCalls);
        self::assertSame([1000, 1000, 1000], $transport->maxRows);
        self::assertSame(3, count($result->raw()));
    }

    public function test_null_values_preserved(): void
    {
        $transport = new FakeTransport(HiveConfig::fromArray(['host' => 'hive.local']), [
            ['rows' => [[null, 'x'], [1, null]], 'hasMore' => false],
        ]);

        $result = (new HiveClient($transport->config, $transport))->execute('SELECT id, name FROM users');

        self::assertSame([[null, 'x'], [1, null]], $result->rows());
    }

    public function test_plain_auth_sets_username(): void
    {
        $config = HiveConfig::fromArray([
            'host' => 'hive.local',
            'auth' => 'PLAIN',
            'username' => 'alice',
            'password' => 'secret',
        ]);
        $transport = new FakeTransport($config);

        (new HiveClient($config, $transport))->connect();

        self::assertTrue($transport->opened);
        self::assertSame('alice', $transport->openedAs);
        self::assertSame('secret', $transport->openedPassword);
        self::assertSame('PLAIN', $transport->config->auth);
    }

    public function test_close_calls_transport_close(): void
    {
        $transport = new FakeTransport(HiveConfig::fromArray(['host' => 'hive.local']));
        $client = new HiveClient($transport->config, $transport);

        $client->connect();
        $client->close();

        self::assertTrue($transport->closed);
    }

    public function test_execute_opens_transport_lazily(): void
    {
        $transport = new FakeTransport(HiveConfig::fromArray(['host' => 'hive.local']), [
            ['rows' => [], 'hasMore' => false],
        ]);

        (new HiveClient($transport->config, $transport))->execute('SELECT 1');

        self::assertTrue($transport->opened);
    }

    public function test_set_runs_a_set_statement(): void
    {
        $transport = new FakeTransport(HiveConfig::fromArray(['host' => 'hive.local']), [
            ['rows' => [], 'hasMore' => false],
        ]);

        (new HiveClient($transport->config, $transport))->set('hive.exec.dynamic.partition', 'true');

        self::assertSame(['SET hive.exec.dynamic.partition=true'], $transport->executed);
    }
}

final class FakeTransport implements HiveTransport
{
    public bool $opened = false;

    public bool $closed = false;

    public ?string $openedAs = null;

    public ?string $openedPassword = null;

    /** @var list<string> */
    public array $executed = [];

    /** @var list<int> */
    public array $maxRows = [];

    public int $fetchCalls = 0;

    /** @var list<array{rows: list<list<mixed>>, hasMore: bool}> */
    private array $batches;

    /**
     * @param list<array{rows: list<list<mixed>>, hasMore: bool}> $batches
     */
    public function __construct(public readonly HiveConfig $config, array $batches = [])
    {
        $this->batches = $batches;
    }

    public function open(): void
    {
        $this->opened = true;
        $this->openedAs = $this->config->username;
        $this->openedPassword = $this->config->password;
    }

    public function execute(string $sql): array
    {
        $this->executed[] = $sql;

        return ['columns' => \Erikwang2013\BigData\Tests\Driver\Hive\HiveClientTest::COLUMNS, 'handle' => 'handle-1'];
    }

    public function fetch(int $maxRows): array
    {
        ++$this->fetchCalls;
        $this->maxRows[] = $maxRows;

        return array_shift($this->batches) ?? ['rows' => [], 'hasMore' => false];
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
