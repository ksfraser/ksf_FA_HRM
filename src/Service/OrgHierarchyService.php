<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;
use ksfraser\FrontAccounting\HRM\Repository\TeamRepository;
use ksfraser\FrontAccounting\HRM\Repository\RoleRepository;
use ksfraser\FrontAccounting\HRM\Repository\PositionRepository;

class OrgHierarchyService
{
    private DepartmentRepository $deptRepo;
    private TeamRepository $teamRepo;
    private RoleRepository $roleRepo;
    private PositionRepository $positionRepo;

    public function __construct()
    {
        $this->deptRepo = new DepartmentRepository();
        $this->teamRepo = new TeamRepository();
        $this->roleRepo = new RoleRepository();
        $this->positionRepo = new PositionRepository();
    }

    public function listDepartments(): array
    {
        return $this->deptRepo->findAll();
    }

    public function getDepartment(int $id): array
    {
        $dept = $this->deptRepo->findById($id);
        return $dept ? $dept->toArray() : [];
    }

    public function saveDepartment(array $data): int
    {
        return $this->deptRepo->save($data);
    }

    public function getTeamsForDepartment(int $departmentId): array
    {
        return $this->teamRepo->findByDepartment($departmentId);
    }

    public function saveTeam(array $data): int
    {
        return $this->teamRepo->save($data);
    }

    public function updateTeam(int $id, array $data): void
    {
        $this->teamRepo->update($id, $data);
    }

    public function getRolesForDepartment(int $departmentId): array
    {
        return $this->roleRepo->findByDepartment($departmentId);
    }

    public function getRoleDictionary(): array
    {
        return $this->roleRepo->findDictionary();
    }

    public function saveRole(array $data): int
    {
        return $this->roleRepo->save($data);
    }

    public function getPositionsForDepartment(int $departmentId): array
    {
        return $this->positionRepo->findByDepartment($departmentId);
    }
}
