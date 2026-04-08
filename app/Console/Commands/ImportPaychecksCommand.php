<?php

namespace App\Console\Commands;

use App\Enums\Employee;
use App\Models\Paycheck;
use App\Models\PaycheckYear;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('paychecks:import {file : Pot do CSV datoteke}')]
#[Description('Uvozi place iz CSV datoteke.')]
class ImportPaychecksCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $filePath = $this->validateFile((string) $this->argument('file'));
            $rows = $this->readRows($filePath);
            $employee = $this->promptForEmployee();

            $this->info("Izbran zaposleni: {$employee->label()}");

            $summary = $this->importRows($employee, $rows);

            $this->newLine();
            $this->info('Uvoz plač je uspešno zaključen.');
            $this->line("Obdelane vrstice: {$summary['processed_rows']}");
            $this->line("Ustvarjena leta plač: {$summary['created_years']}");
            $this->line("Ustvarjene plače: {$summary['created_paychecks']}");
            $this->line("Posodobljene plače: {$summary['updated_paychecks']}");

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function validateFile(string $filePath): string
    {
        if ($filePath === '' || ! is_file($filePath)) {
            throw new RuntimeException("CSV datoteka [{$filePath}] ne obstaja.");
        }

        if (! is_readable($filePath)) {
            throw new RuntimeException("CSV datoteke [{$filePath}] ni mogoče brati.");
        }

        return $filePath;
    }

    private function promptForEmployee(): Employee
    {
        $employeesByLabel = [];

        foreach (Employee::cases() as $employee) {
            $employeesByLabel[$employee->label()] = $employee;
        }

        $selectedLabel = $this->choice(
            'Izberi zaposlenega za uvoz plač',
            array_keys($employeesByLabel),
        );

        return $employeesByLabel[$selectedLabel];
    }

    /**
     * @return list<array{month: int, year: int, net: string|null, gross: string, contributions: string, taxes: string}>
     */
    private function readRows(string $filePath): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("CSV datoteke [{$filePath}] ni mogoče odpreti.");
        }

        try {
            $header = fgetcsv($handle);

            if (! is_array($header)) {
                throw new RuntimeException('CSV datoteka je prazna.');
            }

            $this->validateHeader($header);

            $rows = [];
            $lineNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                if (count($row) !== 5) {
                    throw new RuntimeException("Vrstica {$lineNumber}: pričakovanih je 5 stolpcev.");
                }

                $rows[] = $this->parseRow($row, $lineNumber);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $header
     */
    private function validateHeader(array $header): void
    {
        $normalizedHeader = array_map(
            fn (?string $value): string => $this->normalizeHeaderCell($value),
            $header,
        );

        $expectedHeader = ['date', 'net', 'gross', 'contributions', 'taxes'];

        if ($normalizedHeader !== $expectedHeader) {
            throw new RuntimeException('CSV glava mora biti: date,net,gross,contributions,taxes.');
        }
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array{month: int, year: int, net: string|null, gross: string, contributions: string, taxes: string}
     */
    private function parseRow(array $row, int $lineNumber): array
    {
        [$date, $net, $gross, $contributions, $taxes] = $row;

        ['month' => $month, 'year' => $year] = $this->parseDateValue((string) $date, $lineNumber);

        return [
            'month' => $month,
            'year' => $year,
            'net' => $this->parseMoneyValue($net, $lineNumber, 'neto znesek', true),
            'gross' => $this->parseMoneyValue($gross, $lineNumber, 'bruto znesek'),
            'contributions' => $this->parseMoneyValue($contributions, $lineNumber, 'prispevki'),
            'taxes' => $this->parseMoneyValue($taxes, $lineNumber, 'davki'),
        ];
    }

    private function normalizeHeaderCell(?string $value): string
    {
        $value = $value ?? '';
        $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeWhitespace($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{month: int, year: int}
     */
    private function parseDateValue(string $value, int $lineNumber): array
    {
        $value = $this->normalizeWhitespace($value);

        if (! preg_match('/^(?<day>\d{1,2})\.\s*(?<month>\d{1,2})\.\s*(?<year>\d{4})$/', $value, $matches)) {
            throw new RuntimeException("Vrstica {$lineNumber}: neveljaven datum [{$value}].");
        }

        $day = (int) $matches['day'];
        $month = (int) $matches['month'];
        $year = (int) $matches['year'];

        if (! checkdate($month, $day, $year)) {
            throw new RuntimeException("Vrstica {$lineNumber}: neveljaven datum [{$value}].");
        }

        return [
            'month' => $month,
            'year' => $year,
        ];
    }

    private function parseMoneyValue(?string $value, int $lineNumber, string $fieldLabel, bool $nullable = false): ?string
    {
        $normalizedValue = $this->normalizeWhitespace($value);

        if ($normalizedValue === '') {
            if ($nullable) {
                return null;
            }

            throw new RuntimeException("Vrstica {$lineNumber}: polje {$fieldLabel} je obvezno.");
        }

        $normalizedValue = str_replace('€', '', $normalizedValue);
        $normalizedValue = preg_replace('/\s+/u', '', $normalizedValue) ?? $normalizedValue;
        $normalizedValue = str_replace('.', '', $normalizedValue);
        $normalizedValue = str_replace(',', '.', $normalizedValue);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalizedValue)) {
            throw new RuntimeException("Vrstica {$lineNumber}: polje {$fieldLabel} vsebuje neveljaven znesek [{$value}].");
        }

        return number_format((float) $normalizedValue, 2, '.', '');
    }

    private function normalizeWhitespace(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  list<array{month: int, year: int, net: string|null, gross: string, contributions: string, taxes: string}>  $rows
     * @return array{processed_rows: int, created_years: int, created_paychecks: int, updated_paychecks: int}
     */
    private function importRows(Employee $employee, array $rows): array
    {
        return DB::transaction(function () use ($employee, $rows): array {
            $processedRows = 0;
            $createdYears = 0;
            $createdPaychecks = 0;
            $updatedPaychecks = 0;

            foreach ($rows as $row) {
                $paycheckYear = PaycheckYear::firstOrCreate(
                    [
                        'employee' => $employee->value,
                        'year' => $row['year'],
                    ],
                    [
                        'child1_months' => 0,
                        'child2_months' => 0,
                        'child3_months' => 0,
                    ],
                );

                if ($paycheckYear->wasRecentlyCreated) {
                    $createdYears++;
                }

                $paycheck = Paycheck::updateOrCreate(
                    [
                        'paycheck_year_id' => $paycheckYear->id,
                        'month' => $row['month'],
                    ],
                    [
                        'net' => $row['net'],
                        'gross' => $row['gross'],
                        'contributions' => $row['contributions'],
                        'taxes' => $row['taxes'],
                    ],
                );

                if ($paycheck->wasRecentlyCreated) {
                    $createdPaychecks++;
                } else {
                    $updatedPaychecks++;
                }

                $processedRows++;
            }

            return [
                'processed_rows' => $processedRows,
                'created_years' => $createdYears,
                'created_paychecks' => $createdPaychecks,
                'updated_paychecks' => $updatedPaychecks,
            ];
        });
    }
}
