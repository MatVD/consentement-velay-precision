<?php
namespace App\Dto;

final class ClientImportReport
{
    /** @param list<array{line: int, reason: string}> $errors */
    public function __construct(
        public readonly int $added,
        public readonly int $duplicates,
        public readonly int $rejected,
        public readonly array $errors,
    ) {}

    /** @return array{added:int, duplicates:int, rejected:int, errors:list<array{line:int, reason:string}>} */
    public function toArray(): array
    {
        return [
            'added' => $this->added,
            'duplicates' => $this->duplicates,
            'rejected' => $this->rejected,
            'errors' => $this->errors,
        ];
    }
}
