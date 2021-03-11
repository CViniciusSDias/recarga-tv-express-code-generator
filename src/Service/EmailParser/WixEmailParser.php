<?php

namespace CViniciusSDias\RecargaTvExpress\Service\EmailParser;

use CViniciusSDias\RecargaTvExpress\Model\Sale;
use CViniciusSDias\RecargaTvExpress\Model\VO\Email;
use PhpImap\IncomingMail;

class WixEmailParser extends EmailParser
{
    protected function canParse(IncomingMail $email): bool
    {
        return $email->fromAddress === 'no-reply@mystore.wix.com';
    }

    /**
     * @param IncomingMail $email
     * @return Sale[]
     */
    protected function parseEmail(IncomingMail $email): array
    {
        $domDocument = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($email->textHtml);
        $xPath = new \DOMXPath($domDocument);

        $infoNodes = $xPath
            ->query('/html/body/table[@id="backgroundTable"]//td[@class="section-content"]');

        $emailAddress = $this->retrieveEmailAddress($infoNodes);
        $products = $this->retrieveProducts($infoNodes);

        return array_map(function (string $product) use ($emailAddress) {
            return new Sale(new Email($emailAddress), $product);
        }, $products);
    }

    private function retrieveEmailAddress(\DOMNodeList $infoNodes): string
    {
        $contactInfo = $infoNodes->item(1)
            ->textContent;

        $emailArray = array_filter(
            explode("\n", $contactInfo),
            function (string $line) {
                return filter_var(trim($line), FILTER_VALIDATE_EMAIL);
            }
        );

        return trim(array_values($emailArray)[0]);
    }

    private function retrieveProducts(\DOMNodeList $infoNodes): array
    {
        $productInfo = $infoNodes->item(2);
        $xPath = new \DOMXPath($productInfo->ownerDocument);
        $items = $xPath->query('//span[@class="item-name"]', $productInfo);
        $products = [];

        /** @var \DOMNode $item */
        foreach ($items as $item) {
            $productName = $item->textContent;
            preg_match('/pacote (mensal-mc|anual-mc|mensal|anual)/i', $productName, $productMatches);

            $quantity = intval($item->parentNode->nextSibling->nextSibling->textContent);
            // todo Understand why + operator doesn't do the trick
            $products = array_merge($products, array_fill(0, $quantity, $productMatches[1]));
        }

        return $products;
    }
}
