<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ODM\MongoDB\Types\Type;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use stdClass;
use const PHP_INT_SIZE;
use function date;
use function strtotime;

class DateTypeTest extends TestCase
{
    public function testGetDateTime()
    {
        $type = Type::getType(Type::DATE);

        $timestamp = 100000000.001;
        $dateTime  = $type->getDateTime($timestamp);
        $this->assertEquals($timestamp, $dateTime->format('U.u'));

        $mongoDate = new UTCDateTime(100000000001);
        $dateTime  = $type->getDateTime($mongoDate);
        $this->assertEquals($timestamp, $dateTime->format('U.u'));
    }

    public function testConvertToDatabaseValue()
    {
        $type = Type::getType(Type::DATE);

        $this->assertNull($type->convertToDatabaseValue(null), 'null is not converted');

        $mongoDate = new UTCDateTime();
        $this->assertSame($mongoDate, $type->convertToDatabaseValue($mongoDate), 'MongoDate objects are not converted');

        $timestamp = 100000000.123;
        $dateTime  = DateTime::createFromFormat('U.u', (string) $timestamp);
        $mongoDate = new UTCDateTime(100000000123);
        $this->assertEquals($mongoDate, $type->convertToDatabaseValue($dateTime), 'DateTime objects are converted to MongoDate objects');
        $this->assertEquals($mongoDate, $type->convertToDatabaseValue($timestamp), 'Numeric timestamps are converted to MongoDate objects');
        $this->assertEquals($mongoDate, $type->convertToDatabaseValue('' . $timestamp), 'String dates are converted to MongoDate objects');
        $this->assertEquals($mongoDate, $type->convertToDatabaseValue($mongoDate), 'MongoDate objects are converted to MongoDate objects');
        $this->assertEquals(null, $type->convertToDatabaseValue(null), 'null are converted to null');
    }

    public function testConvertDateTimeImmutable()
    {
        $type = Type::getType(Type::DATE);

        $timestamp = 100000000.123;
        $mongoDate = new UTCDateTime(100000000123);

        $dateTimeImmutable = DateTimeImmutable::createFromFormat('U.u', (string) $timestamp);
        $this->assertEquals($mongoDate, $type->convertToDatabaseValue($dateTimeImmutable), 'DateTimeImmutable objects are converted to MongoDate objects');
    }

    public function testConvertOldDate()
    {
        $type = Type::getType(Type::DATE);

        $date      = new DateTime('1900-01-01 00:00:00.123', new DateTimeZone('UTC'));
        $timestamp = '-2208988800.123';
        $this->assertEquals($type->convertToDatabaseValue($timestamp), $type->convertToDatabaseValue($date));
    }

    /**
     * @dataProvider provideInvalidDateValues
     */
    public function testConvertToDatabaseValueWithInvalidValues($value)
    {
        $type = Type::getType(Type::DATE);
        $this->expectException(InvalidArgumentException::class);
        $type->convertToDatabaseValue($value);
    }

    public function provideInvalidDateValues()
    {
        return [
            'array'  => [[]],
            'string' => ['whatever'],
            'bool'   => [false],
            'object' => [new stdClass()],
            'invalid string' => ['foo'],
        ];
    }

    /**
     * @dataProvider provideDatabaseToPHPValues
     */
    public function testConvertToPHPValue($input, $output)
    {
        $type   = Type::getType(Type::DATE);
        $return = $type->convertToPHPValue($input);

        $this->assertInstanceOf('DateTime', $return);
        $this->assertTimestampEquals($output, $return);
    }

    public function testConvertToPHPValueDoesNotConvertNull()
    {
        $type = Type::getType(Type::DATE);

        $this->assertNull($type->convertToPHPValue(null));
    }

    /**
     * @dataProvider provideDatabaseToPHPValues
     */
    public function testClosureToPHP($input, $output)
    {
        $type = Type::getType(Type::DATE);

        $return = (static function ($value) use ($type) {
            $return = null;
            eval($type->closureToPHP());

            return $return;
        })($input);

        $this->assertInstanceOf('DateTime', $return);
        $this->assertTimestampEquals($output, $return);
    }

    public function provideDatabaseToPHPValues()
    {
        $yesterday = strtotime('yesterday');
        $mongoDate = new UTCDateTime($yesterday * 1000);
        $dateTime  = new DateTime('@' . $yesterday);

        return [
            [$dateTime, $dateTime],
            [$mongoDate, $dateTime],
            [$yesterday, $dateTime],
            [date('c', $yesterday), $dateTime],
            [new UTCDateTime(100000000123), DateTime::createFromFormat('U.u', '100000000.123')],
        ];
    }

    public function test32bit1900Date()
    {
        if (PHP_INT_SIZE !== 4) {
            $this->markTestSkipped('Platform is not 32-bit');
        }

        $type = Type::getType(Type::DATE);
        $this->expectException(InvalidArgumentException::class);
        $type->convertToDatabaseValue('1900-01-01');
    }

    public function test64bit1900Date()
    {
        if (PHP_INT_SIZE !== 8) {
            $this->markTestSkipped('Platform is not 64-bit');
        }

        $type   = Type::getType(Type::DATE);
        $return = $type->convertToDatabaseValue('1900-01-01');

        $this->assertInstanceOf(UTCDateTime::class, $return);
        $this->assertEquals(new UTCDateTime(strtotime('1900-01-01') * 1000), $return);
    }

    private function assertTimestampEquals(DateTime $expected, DateTime $actual)
    {
        $this->assertEquals($expected->format('U.u'), $actual->format('U.u'));
    }
}
