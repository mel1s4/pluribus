<?php

namespace App\Support;

class CsvImportResult
{
    public int $totalRows = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $failed = 0;

    /** @var list<array{row_number:int,sku:?string,field:string,message:string}> */
    public array $errors = [];

    public function addRowError(int $rowNumber, ?string $sku, string $field, string $message): void
    {
        $this->failed++;
        $this->errors[] = [
            'row_number' => $rowNumber,
            'sku' => $sku,
            'field' => $field,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'created' => $this->created,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'skipped' => 0,
            'errors' => $this->errors,
        ];
    }
}
