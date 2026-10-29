<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Integration\Yii3;

/**
 * For Yii3 applications that do not use yiisoft/config's composer plugin:
 * merge the arrays below into config/params.php and config/di.php, or return
 * them from your own config provider. With the composer plugin enabled the
 * package's config/params.php and config/di.php are picked up automatically.
 */
final class ConfigProvider
{
    /** @return array{params: array<string, mixed>, di: array<string, mixed>} */
    public function __invoke(): array
    {
        return [
            'params' => $this->getParams(),
            'di' => $this->getDi(),
        ];
    }

    /** @return array<string, mixed> */
    public function getParams(): array
    {
        return require dirname(__DIR__, 3) . '/config/params.php';
    }

    /** @return array<string, mixed> */
    public function getDi(): array
    {
        $params = $this->getParams();

        return require dirname(__DIR__, 3) . '/config/di.php';
    }
}
