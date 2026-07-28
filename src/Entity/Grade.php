<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class Grade
{
    private int $gradeId;
    private ?string $gradeCode;
    private string $gradeName;
    private float $minSalary;
    private float $maxSalary;
    private ?string $description;
    private bool $isActive;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->gradeId = (int)$data['grade_id'];
        $this->gradeCode = $data['grade_code'] ?? null;
        $this->gradeName = $data['grade_name'];
        $this->minSalary = (float)($data['min_salary'] ?? 0);
        $this->maxSalary = (float)($data['max_salary'] ?? 0);
        $this->description = $data['description'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
    }

    public function getGradeId(): int { return $this->gradeId; }
    public function getGradeCode(): ?string { return $this->gradeCode; }
    public function getGradeName(): string { return $this->gradeName; }
    public function getMinSalary(): float { return $this->minSalary; }
    public function getMaxSalary(): float { return $this->maxSalary; }
    public function getDescription(): ?string { return $this->description; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'grade_id' => $this->gradeId,
            'grade_code' => $this->gradeCode,
            'grade_name' => $this->gradeName,
            'min_salary' => $this->minSalary,
            'max_salary' => $this->maxSalary,
            'description' => $this->description,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function update(array $data): void
    {
        if (isset($data['grade_code'])) $this->gradeCode = $data['grade_code'];
        if (isset($data['grade_name'])) $this->gradeName = $data['grade_name'];
        if (isset($data['min_salary'])) $this->minSalary = (float)$data['min_salary'];
        if (isset($data['max_salary'])) $this->maxSalary = (float)$data['max_salary'];
        if (isset($data['description'])) $this->description = $data['description'];
        if (isset($data['is_active'])) $this->isActive = (bool)$data['is_active'];
        $this->updatedAt = date('Y-m-d H:i:s');
    }
}
