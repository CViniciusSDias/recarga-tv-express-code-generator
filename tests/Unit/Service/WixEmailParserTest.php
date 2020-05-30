<?php

namespace CViniciusSDias\RecargaTvExpress\Tests\Unit\Service;

use CViniciusSDias\RecargaTvExpress\Model\Sale;
use CViniciusSDias\RecargaTvExpress\Service\EmailParser\WixEmailParser;
use PhpImap\IncomingMail;
use PHPUnit\Framework\TestCase;

class WixEmailParserTest extends TestCase
{
    public function testShouldParseCorrectlyASaleFromWixEmail()
    {
        // arrange
        $emailBody = file_get_contents(__DIR__ . '/../../data/email-from-wix.html');
        $parser = new WixEmailParser();

        $incomingMailMock = $this->createStub(IncomingMail::class);
        $incomingMailMock->fromAddress = 'no-reply@mystore.wix.com';
        $incomingMailMock->subject = 'ÓTIMO! VOCÊ ACABOU DE RECEBER UM PEDIDO (#10001)';
        $incomingMailMock->method('__get')
            ->willReturn($emailBody);

        // act
        $sales = $parser->parse($incomingMailMock);

        // assert
        $this->assertCount(1, $sales);
        $this->assertInstanceOf(Sale::class, $sales[0]);
        $this->assertSame('anual', $sales[0]->product);
        $this->assertEquals('email@example.com', $sales[0]->costumerEmail);
    }

    public function testShouldRaiseErrorWhenTryingToParseUnsupportedEmail()
    {
        $this->expectException(\Error::class);

        // arrange
        $parser = new WixEmailParser();
        $incomingMailMock = $this->createStub(IncomingMail::class);
        $incomingMailMock->fromAddress = 'email@example.com';

        // act
        $parser->parse($incomingMailMock);
    }

    public function testShouldParseCorrectlyEmailWithMoreThanOneSale()
    {
        // arrange
        $emailBody = file_get_contents(__DIR__ . '/../../data/email-from-wix-with-three-sales.html');
        $parser = new WixEmailParser();

        $incomingMailMock = $this->createStub(IncomingMail::class);
        $incomingMailMock->fromAddress = 'no-reply@mystore.wix.com';
        $incomingMailMock->subject = 'ÓTIMO! VOCÊ ACABOU DE RECEBER UM PEDIDO (#10001)';
        $incomingMailMock->method('__get')
            ->willReturn($emailBody);

        // act
        $sales = $parser->parse($incomingMailMock);

        // assert
        $this->assertIsArray($sales);
        $this->assertCount(3, $sales);
        $this->assertContainsOnlyInstancesOf(Sale::class, $sales);

        foreach ($sales as $sale) {
            $this->assertSame('mensal', $sale->product);
            $this->assertEquals('email@example.com', $sale->costumerEmail);
        }
    }
}
