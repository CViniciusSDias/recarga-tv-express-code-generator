<?php

namespace CViniciusSDias\RecargaTvExpress\Repository;

use CViniciusSDias\RecargaTvExpress\Model\Code;
use CViniciusSDias\RecargaTvExpress\Model\Sale;
use PDO;

class CodeRepository
{
    private $con;

    public function __construct(PDO $con)
    {
        $this->con = $con;
    }

    public function attachCodeToSale(Code $serialCode, Sale $sale): bool
    {
        $costumerEmail = $sale->costumerEmail;
        $sql = 'UPDATE serial_codes SET user_email = ? WHERE id = ?;';
        $stm = $this->con->prepare($sql);
        $stm->bindValue(1, $costumerEmail);
        $stm->bindValue(2, $serialCode->id, PDO::PARAM_INT);

        return $stm->execute();
    }

    public function findNumberOfAvailableCodes(): array
    {
        $sql = 'SELECT product, COUNT(id) AS total FROM serial_codes WHERE user_email IS NULL GROUP BY product;';
        $stm = $this->con->query($sql);

        return $stm->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * @param array<string, int> $numberOfSales
     * @return array[]
     */
    public function findUnusedCodes(array $numberOfSales): array
    {
        $queries = [];
        foreach ($numberOfSales as $product => $number) {
            $escapedProduct = $this->con->quote($product);
            $queries[] = <<<SQL
            SELECT * FROM (
                SELECT product, id, serial
                  FROM serial_codes
                 WHERE user_email IS NULL
                   AND product = $escapedProduct
                 LIMIT ?
            ) AS $product
            SQL;
        }

        $stmt = $this->con->prepare(implode(' UNION ', $queries));
        $i = 1;
        foreach ($numberOfSales as $number) {
            $stmt->bindParam($i++, $number, \PDO::PARAM_INT);
        }
        $stmt->execute();

        $groupedCodes = $stmt->fetchAll(\PDO::FETCH_GROUP);
        $groupedSerialCodes = [
            'anual' => [],
            'mensal' => [],
            'anual-mc' => [],
            'mensal-mc' => [],
        ];
        foreach ($groupedCodes as $product => $codes) {
            $groupedSerialCodes[$product] = array_map(function (array $code) {
                return new Code($code['id'], $code['serial']);
            }, $codes);
        }

        return $groupedSerialCodes;
    }
}
