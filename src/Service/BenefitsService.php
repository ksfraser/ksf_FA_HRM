<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\BenefitRepository;

class BenefitsService
{
    private BenefitRepository $benefitRepo;

    public function __construct()
    {
        $this->benefitRepo = new BenefitRepository();
    }

    public function listAll(bool $activeOnly = false): array
    {
        return $this->benefitRepo->findAll($activeOnly);
    }

    public function getById(int $id): array
    {
        $benefit = $this->benefitRepo->findById($id);
        return $benefit ? $benefit->toArray() : [];
    }

    public function create(array $data): int
    {
        return $this->benefitRepo->save($data);
    }

    public function deactivate(int $id): void
    {
        $this->benefitRepo->deactivate($id);
    }

    public function getEmployeeBenefits(int $personId): array
    {
        return $this->benefitRepo->findEmployeeBenefits($personId);
    }

    public function assignBenefit(array $data): int
    {
        return $this->benefitRepo->saveEmployeeBenefit($data);
    }
}
