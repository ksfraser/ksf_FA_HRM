<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\LookupRepository;

class LookupService
{
    private LookupRepository $lookupRepo;

    public function __construct()
    {
        $this->lookupRepo = new LookupRepository();
    }

    public function getEmploymentStatuses(): array
    {
        return $this->lookupRepo->getEmploymentStatuses();
    }

    public function getLeaveTypes(): array
    {
        return $this->lookupRepo->getLeaveTypes();
    }

    public function saveLeaveType(array $data): int
    {
        return $this->lookupRepo->saveLeaveType($data);
    }

    public function getCrmPersons(): array
    {
        return $this->lookupRepo->getCrmPersons();
    }

    public function getSeparationReasons(): array
    {
        return $this->lookupRepo->getSeparationReasons();
    }
}
