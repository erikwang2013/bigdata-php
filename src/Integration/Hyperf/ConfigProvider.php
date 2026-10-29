<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Hyperf;

use Erikwang2013\BigData\Integration\Manager;
use Erikwang2013\BigData\Support\ConfigPublisher;
use Hyperf\Contract\ConfigInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        $template = __DIR__ . '/config/bigdata.php';

        if (defined('BASE_PATH')) {
            ConfigPublisher::publishIfMissing(
                $template,
                BASE_PATH . '/config/autoload/bigdata.php',
            );
        }

        return [
            'dependencies' => [
                Manager::class => static function ($container) use ($template): Manager {
                    $config = $container->get(ConfigInterface::class)->get('bigdata', []);
                    if (!is_array($config) || $config === []) {
                        $config = require $template;
                    }

                    return new Manager($config);
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
