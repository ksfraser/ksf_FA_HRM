<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\TeamRepository;
use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;

class TeamService
{
    private TeamRepository $teamRepo;
    private DepartmentRepository $deptRepo;

    public function __construct()
    {
        $this->teamRepo = new TeamRepository();
        $this->deptRepo = new DepartmentRepository();
    }

    public function listAll(): array
    {
        return $this->teamRepo->findAll();
    }

    public function getById(int $id): ?array
    {
        $team = $this->teamRepo->findById($id);
        return $team ? $team->toArray() : null;
    }

    public function create(array $data): int
    {
        return $this->teamRepo->save($data);
    }

    public function update(int $id, array $data): void
    {
        $this->teamRepo->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->teamRepo->delete($id);
    }

    public function getFormDropdowns(): array
    {
        return [
            'departments' => $this->deptRepo->findActive(),
        ];
    }

    public function getTeamsForDepartment(int $departmentId): array
    {
        return $this->teamRepo->findActiveByDepartment($departmentId);
    }

    public function getParentTeams(int $departmentId): array
    {
        return $this->teamRepo->findByDepartment($departmentId);
    }
}
