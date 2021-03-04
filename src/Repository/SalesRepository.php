<?php

namespace CViniciusSDias\RecargaTvExpress\Repository;

use CViniciusSDias\RecargaTvExpress\Exception\NotEnoughCodesException;
use CViniciusSDias\RecargaTvExpress\Model\Code;
use CViniciusSDias\RecargaTvExpress\Model\Sale;
use CViniciusSDias\RecargaTvExpress\Service\EmailSalesReader;

class SalesRepository
{
    /** @var EmailSalesReader */
    private $emailSalesReader;
    /** @var CodeRepository */
    private $codeRepository;
    /** @var \PDO */
    private $con;

    public function __construct(EmailSalesReader $emailSalesReader, CodeRepository $codeRepository, \PDO $con)
    {
        $this->emailSalesReader = $emailSalesReader;
        $this->codeRepository = $codeRepository;
        $this->con = $con;
    }

    /**
     * @return Sale[]
     * @throws \PDOException
     * @throws NotEnoughCodesException
     */
    public function salesWithCodes(): array
    {
        $salesWithoutCode = $this->emailSalesReader->findSales();
        $annualSales = array_values(array_filter($salesWithoutCode, function (Sale $sale) {
            return $sale->product === 'anual';
        }));
        $monthlySales = array_values(array_filter($salesWithoutCode, function (Sale $sale) {
            return $sale->product === 'mensal';
        }));
        $annualMcSales = array_values(array_filter($salesWithoutCode, function (Sale $sale) {
            return $sale->product === 'anual-mc';
        }));
        $monthlyMcSales = array_values(array_filter($salesWithoutCode, function (Sale $sale) {
            return $sale->product === 'mensal-mc';
        }));

        $groupedCodes = $this->codeRepository->findUnusedCodes([
            'anual' => count($annualSales),
            'mensal' => count($monthlySales),
            'anual-mc' => count($annualMcSales),
            'mensal-mc' => count($monthlyMcSales),
        ]);
        if (count($annualSales) > count($groupedCodes['anual'])
            || count($monthlySales) > count($groupedCodes['mensal'])
            || count($monthlyMcSales) > count($groupedCodes['mensal-mc'])
            || count($annualMcSales) > count($groupedCodes['anual-mc'])) {
            throw new NotEnoughCodesException(
                count($annualMcSales),
                count($groupedCodes['anual-mc']),
                count($monthlyMcSales),
                count($groupedCodes['mensal-mc']),
                count($annualSales),
                count($groupedCodes['anual']),
                count($monthlySales),
                count($groupedCodes['mensal']),
            );
        }

        $this->con->beginTransaction();
        try {
            $this->attachCodesToSales($groupedCodes, $annualSales, $monthlySales, $annualMcSales, $monthlyMcSales);
            $this->con->commit();
        } catch (\PDOException $e) {
            $this->con->rollBack();
            throw $e;
        }

        return array_merge($annualSales, $monthlySales);
    }

    /**
     * @param array<string, Code[]> $grouppedCodes
     * @param Sale[] $annualSales
     * @param Sale[] $monthlySales
     */
    private function attachCodesToSales(array $grouppedCodes, array $annualSales, array $monthlySales, array $annualMcSales, array $monthlyMcSales): void
    {
        foreach ($grouppedCodes['anual'] as $i => $code) {
            $annualSales[$i]->attachCode($code);
            $this->codeRepository->attachCodeToSale($code, $annualSales[$i]);
        }

        foreach ($grouppedCodes['mensal'] as $i => $code) {
            $monthlySales[$i]->attachCode($code);
            $this->codeRepository->attachCodeToSale($code, $monthlySales[$i]);
        }

        foreach ($grouppedCodes['anual-mc'] as $i => $code) {
            $annualMcSales[$i]->attachCode($code);
            $this->codeRepository->attachCodeToSale($code, $annualMcSales[$i]);
        }

        foreach ($grouppedCodes['mensal-mc'] as $i => $code) {
            $monthlyMcSales[$i]->attachCode($code);
            $this->codeRepository->attachCodeToSale($code, $monthlyMcSales[$i]);
        }
    }
}
