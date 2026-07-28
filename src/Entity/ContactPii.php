<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class ContactPii
{
    private int $piiId;
    private int $personId;
    private ?string $dateOfBirth;
    private ?string $gender;
    private ?string $nationalId;
    private ?string $passportNumber;
    private ?string $taxNumber;
    private ?string $maritalStatus;

    public function __construct(array $data)
    {
        $this->piiId = (int)$data['pii_id'];
        $this->personId = (int)$data['person_id'];
        $this->dateOfBirth = $data['date_of_birth'] ?? null;
        $this->gender = $data['gender'] ?? null;
        $this->nationalId = $data['national_id'] ?? null;
        $this->passportNumber = $data['passport_number'] ?? null;
        $this->taxNumber = $data['tax_number'] ?? null;
        $this->maritalStatus = $data['marital_status'] ?? null;
    }

    public function getPiiId(): int { return $this->piiId; }
    public function getPersonId(): int { return $this->personId; }
    public function getDateOfBirth(): ?string { return $this->dateOfBirth; }
    public function getGender(): ?string { return $this->gender; }
    public function getNationalId(): ?string { return $this->nationalId; }
    public function getPassportNumber(): ?string { return $this->passportNumber; }
    public function getTaxNumber(): ?string { return $this->taxNumber; }
    public function getMaritalStatus(): ?string { return $this->maritalStatus; }

    public function toArray(): array
    {
        return [
            'pii_id' => $this->piiId,
            'person_id' => $this->personId,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'national_id' => $this->nationalId,
            'passport_number' => $this->passportNumber,
            'tax_number' => $this->taxNumber,
            'marital_status' => $this->maritalStatus,
        ];
    }
}
