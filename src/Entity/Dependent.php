<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Dependent
{
    private int $dependentId;
    private int $personId;
    private string $dependentName;
    private ?string $relationship;
    private ?string $dateOfBirth;
    private bool $isBeneficiary;

    public function __construct(array $data)
    {
        $this->dependentId = (int)$data['dependent_id'];
        $this->personId = (int)$data['person_id'];
        $this->dependentName = $data['dependent_name'];
        $this->relationship = $data['relationship'] ?? null;
        $this->dateOfBirth = $data['date_of_birth'] ?? null;
        $this->isBeneficiary = (bool)($data['is_beneficiary'] ?? 0);
    }

    public function getDependentId(): int { return $this->dependentId; }
    public function getPersonId(): int { return $this->personId; }
    public function getDependentName(): string { return $this->dependentName; }
    public function getRelationship(): ?string { return $this->relationship; }
    public function getDateOfBirth(): ?string { return $this->dateOfBirth; }
    public function isBeneficiary(): bool { return $this->isBeneficiary; }

    public function toArray(): array
    {
        return [
            'dependent_id' => $this->dependentId,
            'person_id' => $this->personId,
            'dependent_name' => $this->dependentName,
            'relationship' => $this->relationship,
            'date_of_birth' => $this->dateOfBirth,
            'is_beneficiary' => $this->isBeneficiary ? 1 : 0,
        ];
    }
}
