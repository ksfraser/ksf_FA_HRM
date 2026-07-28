<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Service;

use ksfraser\FrontAccounting\HRM\Repository\GradeRepository;

class GradeService
{
    private GradeRepository $gradeRepo;

    public function __construct()
    {
        $this->gradeRepo = new GradeRepository();
    }

    public function listAll(): array
    {
        return $this->gradeRepo->findAll();
    }

    public function getById(int $id): array
    {
        $grade = $this->gradeRepo->findById($id);
        return $grade ? $grade->toArray() : [];
    }

    public function create(array $data): int
    {
        return $this->gradeRepo->save($data);
    }

    public function update(int $id, array $data): void
    {
        $this->gradeRepo->update($id, $data);
    }

    public function deactivate(int $id): void
    {
        $this->gradeRepo->update($id, ['is_active' => 0]);
    }
}
