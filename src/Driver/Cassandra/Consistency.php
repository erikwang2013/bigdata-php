<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Driver\Cassandra;

final class Consistency
{
    public const ANY = 0x0000;
    public const ONE = 0x0001;
    public const TWO = 0x0002;
    public const THREE = 0x0003;
    public const QUORUM = 0x0004;
    public const ALL = 0x0005;
    public const LOCAL_QUORUM = 0x0006;
    public const EACH_QUORUM = 0x0007;
    public const SERIAL = 0x0008;
    public const LOCAL_SERIAL = 0x0009;
    public const LOCAL_ONE = 0x000A;
}
