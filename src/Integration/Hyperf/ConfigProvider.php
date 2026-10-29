<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Hyperf;

use Erikwang2013\BigData\Integration\Manager;
use Hyperf\Contract\ConfigInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                Manager::class => static function ($container): Manager {
                    return new Manager($container->get(ConfigInterface::class)->get('bigdata', []));
                },
            ],
            'publish' => [
                [
                    'id' => 'bigdata',
                    'description' => 'BigData driver configuration',
                    'source' => __DIR__ . '/config/bigdata.php',
                    'destination' => BASE_PATH . '/config/autoload/bigdata.php',
                ],
            ],
        ];
    }
}
