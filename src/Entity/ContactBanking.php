<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class ContactBanking
{
    private int $bankingId;
    private int $personId;
    private ?string $bankName;
    private ?string $branchName;
    private ?string $accountNumber;
    private ?string $accountType;
    private ?string $routingNumber;
    private bool $isPrimary;

    public function __construct(array $data)
    {
        $this->bankingId = (int)$data['banking_id'];
        $this->personId = (int)$data['person_id'];
        $this->bankName = $data['bank_name'] ?? null;
        $this->branchName = $data['branch_name'] ?? null;
        $this->accountNumber = $data['account_number'] ?? null;
        $this->accountType = $data['account_type'] ?? null;
        $this->routingNumber = $data['routing_number'] ?? null;
        $this->isPrimary = (bool)($data['is_primary'] ?? 0);
    }

    public function getBankingId(): int { return $this->bankingId; }
    public function getPersonId(): int { return $this->personId; }
    public function getBankName(): ?string { return $this->bankName; }
    public function getBranchName(): ?string { return $this->branchName; }
    public function getAccountNumber(): ?string { return $this->accountNumber; }
    public function getAccountType(): ?string { return $this->accountType; }
    public function getRoutingNumber(): ?string { return $this->routingNumber; }
    public function isPrimary(): bool { return $this->isPrimary; }

    public function toArray(): array
    {
        return [
            'banking_id' => $this->bankingId,
            'person_id' => $this->personId,
            'bank_name' => $this->bankName,
            'branch_name' => $this->branchName,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType,
            'routing_number' => $this->routingNumber,
            'is_primary' => $this->isPrimary ? 1 : 0,
        ];
    }
}
