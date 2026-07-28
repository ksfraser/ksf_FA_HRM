<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\PositionRepository;
use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;
use ksfraser\FrontAccounting\HRM\Repository\TeamRepository;
use ksfraser\FrontAccounting\HRM\Repository\RoleRepository;

class PositionService
{
    private PositionRepository $positionRepo;
    private DepartmentRepository $deptRepo;
    private TeamRepository $teamRepo;
    private RoleRepository $roleRepo;

    public function __construct()
    {
        $this->positionRepo = new PositionRepository();
        $this->deptRepo = new DepartmentRepository();
        $this->teamRepo = new TeamRepository();
        $this->roleRepo = new RoleRepository();
    }

    public function listAll(): array
    {
        return $this->positionRepo->findActive();
    }

    public function getById(int $id): array
    {
        $position = $this->positionRepo->findById($id);
        return $position ? $position->toArray() : [];
    }

    public function getFormDropdowns(): array
    {
        return [
            'departments' => $this->deptRepo->findActive(),
            'teams' => [],
            'roles' => [],
        ];
    }

    public function getTeamsForDepartment(int $departmentId): array
    {
        return $this->teamRepo->findByDepartment($departmentId);
    }

    public function getRolesForDepartment(int $departmentId): array
    {
        return $this->roleRepo->findByDepartment($departmentId);
    }

    public function create(array $data): int
    {
        return $this->positionRepo->save($data);
    }

    public function update(int $id, array $data): void
    {
        $this->positionRepo->update($id, $data);
    }

    public function deactivate(int $id): void
    {
        $this->positionRepo->update($id, ['is_active' => 0]);
    }
}
