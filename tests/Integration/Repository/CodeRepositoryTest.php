<?php

namespace CViniciusSDias\RecargaTvExpress\Tests\Integration\Repository;

use CViniciusSDias\RecargaTvExpress\Repository\CodeRepository;
use PHPUnit\Framework\TestCase;

class CodeRepositoryTest extends TestCase
{
    /** @var \PDO $con */
    private static $con;
    /** @var CodeRepository  */
    private $codeRepository;

    public static function setUpBeforeClass(): void
    {
        $con = new \PDO('sqlite::memory:');
        $con->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $con->exec('CREATE TABLE serial_codes (
            id INTEGER PRIMARY KEY,
            serial TEXT NOT NULL,
            user_email TEXT DEFAULT NULL,
            product TEXT
        );');

        self::$con = $con;
    }

    public static function tearDownAfterClass(): void
    {
        self::$con = null;
    }

    protected function setUp(): void
    {
        $this->codeRepository = new CodeRepository(self::$con);
    }

    protected function tearDown(): void
    {
        self::$con->exec('DELETE FROM serial_codes;');
    }

    public function testShouldFindExactNumberOfAvailableCodes()
    {
        // Arrange
        $this->insertCode('1111', 'anual');
        $this->insertCode('2222', 'anual');
        $this->insertCode('3333', 'mensal');
        $this->insertCode('4444', 'mensal');
        $this->insertCode('5555', 'mensal');

        $numberOfAvailableCodes = $this->codeRepository->findNumberOfAvailableCodes();

        self::assertEquals(2, $numberOfAvailableCodes['anual']);
        self::assertEquals(3, $numberOfAvailableCodes['mensal']);
    }

    public function testThereShouldBeNoAvailableCodeIfTheRepositoryIsEmpty()
    {
        $numberOfAvailableCodes = $this->codeRepository->findNumberOfAvailableCodes();

        self::assertEmpty($numberOfAvailableCodes);
    }

    public function testSearchForSpecificNumberOfCodesShouldReturnGrouppedArray()
    {
        $this->insertCode('1111', 'anual');
        $this->insertCode('2222', 'anual');
        $this->insertCode('3333', 'mensal');
        $this->insertCode('4444', 'mensal');
        $codes = $this->codeRepository->findUnusedCodes(['mensal' => 2, 'anual' => 2]);

        self::assertArrayHasKey('anual', $codes);
        self::assertArrayHasKey('mensal', $codes);
        self::assertCount(2, $codes['anual']);
        self::assertCount(2, $codes['mensal']);
        self::assertSame('1111', $codes['anual'][0]->serial);
        self::assertSame('2222', $codes['anual'][1]->serial);
        self::assertSame('3333', $codes['mensal'][0]->serial);
        self::assertSame('4444', $codes['mensal'][1]->serial);
    }

    public function testSearchingForUnusedCodesShouldReturnGroupedEmptyArraysIfTheRepositoryIsEmpty()
    {
        $codes = $this->codeRepository->findUnusedCodes(['anual' => 1, 'mensal' => 1]);

        self::assertCount(0, $codes['anual']);
        self::assertCount(0, $codes['mensal']);
    }

    private function insertCode(string $serial, string $product): void
    {
        /** @var \PDOStatement $stm */
        $stm = self::$con->prepare('INSERT INTO serial_codes (serial, product) VALUES (?, ?)');
        $stm->bindValue(1, $serial);
        $stm->bindValue(2, $product);
        $stm->execute();
    }
}
