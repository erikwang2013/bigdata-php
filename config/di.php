<?php
declare(strict_types=1);

use Erikwang2013\BigData\Integration\Manager;

/** @var array $params */

/*
 * Registered by yiisoft/di; inject Manager directly:
 *
 *     public function __construct(private Manager $bigdata) {}
 */
return [
    Manager::class => static fn (): Manager => new Manager($params['erikwang2013/bigdata-php'] ?? []),
];
