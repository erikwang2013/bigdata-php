<?php
declare(strict_types=1);

namespace Erikwang2013\BigData\Tests\Driver\Hive;

use Erikwang2013\BigData\Driver\Hive\Thrift\{
    TColumn,
    TFetchResultsResp,
    TI32Column,
    TRowSet,
    TStatus,
    TStatusCode,
};
use PHPUnit\Framework\TestCase;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TMemoryBuffer;

/**
 * Guards the thrift 0.19-generated classes against the installed 0.24/0.25 runtime.
 */
final class GeneratedThriftClassesTest extends TestCase
{
    public function test_generated_classes_round_trip_through_binary_protocol(): void
    {
        $response = new TFetchResultsResp([
            'status' => new TStatus(['statusCode' => TStatusCode::SUCCESS_STATUS]),
            'hasMoreRows' => true,
            'results' => new TRowSet([
                'startRowOffset' => 0,
                'rows' => [],
                'columns' => [
                    new TColumn(['i32Val' => new TI32Column([
                        'values' => [1, 2],
                        'nulls' => "\x02",
                    ])]),
                ],
            ]),
        ]);

        $buffer = new TMemoryBuffer();
        $response->write(new TBinaryProtocol($buffer));

        $decoded = new TFetchResultsResp();
        $decoded->read(new TBinaryProtocol(new TMemoryBuffer($buffer->getBuffer())));

        self::assertTrue($decoded->hasMoreRows);
        self::assertSame(TStatusCode::SUCCESS_STATUS, $decoded->status->statusCode);
        self::assertSame([1, 2], $decoded->results->columns[0]->i32Val->values);
        self::assertSame("\x02", $decoded->results->columns[0]->i32Val->nulls);
    }
}
