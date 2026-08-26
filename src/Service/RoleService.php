<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\RoleRepository;
use ksfraser\FrontAccounting\HRM\Repository\DepartmentRepository;

class RoleService
{
    private RoleRepository $roleRepo;
    private DepartmentRepository $deptRepo;

    public function __construct()
    {
        $this->roleRepo = new RoleRepository();
        $this->deptRepo = new DepartmentRepository();
    }

    public function listAll(): array
    {
        return $this->roleRepo->findAll();
    }

    public function getById(int $id): ?array
    {
        $role = $this->roleRepo->findById($id);
        return $role ? $role->toArray() : null;
    }

    public function create(array $data): int
    {
        return $this->roleRepo->save($data);
    }

    public function update(int $id, array $data): void
    {
        $this->roleRepo->update($id, $data);
    }

    public function delete(int $id): void
    {
        $this->roleRepo->delete($id);
    }

    public function getFormDropdowns(): array
    {
        return [
            'departments' => $this->deptRepo->findActive(),
            'dictionary' => $this->roleRepo->findDictionary(),
        ];
    }

    public function getRolesForDepartment(int $departmentId): array
    {
        return $this->roleRepo->findByDepartment($departmentId);
    }
}
