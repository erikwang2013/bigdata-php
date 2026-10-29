<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Integration;

use Erikwang2013\BigData\Driver\Airflow\AirflowClient;
use Erikwang2013\BigData\Driver\Trino\TrinoClient;
use Erikwang2013\BigData\Integration\Hyperf\ConfigProvider as HyperfConfigProvider;
use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Integration\Yii3\ConfigProvider as Yii3ConfigProvider;
use PHPUnit\Framework\TestCase;

final class ManagerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'default' => 'trino',
            'drivers' => [
                'trino' => ['host' => 'trino.local'],
                'airflow' => ['baseUrl' => 'http://airflow.local'],
            ],
        ];
    }

    public function test_default_and_named_drivers_resolve(): void
    {
        $manager = new Manager($this->config());

        self::assertInstanceOf(TrinoClient::class, $manager->driver());
        self::assertInstanceOf(TrinoClient::class, $manager->driver('trino'));
        self::assertInstanceOf(AirflowClient::class, $manager->driver('airflow'));
    }

    public function test_default_falls_back_to_trino(): void
    {
        $manager = new Manager(['drivers' => ['trino' => ['host' => 'trino.local']]]);

        self::assertInstanceOf(TrinoClient::class, $manager->driver());
    }

    public function test_driver_instances_are_cached(): void
    {
        $manager = new Manager($this->config());

        self::assertSame($manager->driver(), $manager->driver('trino'));
    }

    public function test_unconfigured_driver_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BigData driver [doris] is not configured');

        // "doris" is a known BigData driver but is absent from this config.
        (new Manager($this->config()))->driver('doris');
    }

    public function test_drivers_and_available(): void
    {
        $manager = new Manager($this->config());

        self::assertSame(['trino', 'airflow'], $manager->drivers());
        self::assertContains('trino', $manager->available());
        self::assertSame([], array_diff($manager->available(), $manager->drivers()));
    }

    public function test_framework_adapters_extend_their_base_classes_when_installed(): void
    {
        $adapters = [
            \Illuminate\Support\ServiceProvider::class => \Erikwang2013\BigData\Integration\Laravel\BigDataServiceProvider::class,
            \think\Service::class => \Erikwang2013\BigData\Integration\ThinkPHP\BigDataService::class,
            \Webman\Bootstrap::class => \Erikwang2013\BigData\Integration\Webman\Bootstrap::class,
            \yii\base\Component::class => \Erikwang2013\BigData\Integration\Yii2\BigDataComponent::class,
        ];

        self::assertCount(4, $adapters);

        foreach ($adapters as $base => $adapter) {
            if (!class_exists($base)) {
                continue;
            }

            self::assertTrue(is_subclass_of($adapter, $base), $adapter);
        }
    }

    public function test_container_less_framework_providers_load(): void
    {
        self::assertTrue(class_exists(HyperfConfigProvider::class));
        self::assertTrue(class_exists(Yii3ConfigProvider::class));
    }

    public function test_yii3_provider_exposes_params_and_manager_definition(): void
    {
        $provider = new Yii3ConfigProvider();

        self::assertArrayHasKey('erikwang2013/bigdata-php', $provider->getParams());

        $di = $provider->getDi();
        self::assertArrayHasKey(Manager::class, $di);
        self::assertInstanceOf(TrinoClient::class, $di[Manager::class]()->driver());
    }
}
