<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class SalaryStructure
{
    private int $structureId;
    private int $gradeId;
    private int $elementId;
    private float $defaultAmount;
    private bool $isActive;

    public function __construct(array $data)
    {
        $this->structureId = (int)$data['structure_id'];
        $this->gradeId = (int)$data['grade_id'];
        $this->elementId = (int)$data['element_id'];
        $this->defaultAmount = (float)($data['default_amount'] ?? 0);
        $this->isActive = (bool)($data['is_active'] ?? 1);
    }

    public function getStructureId(): int { return $this->structureId; }
    public function getGradeId(): int { return $this->gradeId; }
    public function getElementId(): int { return $this->elementId; }
    public function getDefaultAmount(): float { return $this->defaultAmount; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'structure_id' => $this->structureId,
            'grade_id' => $this->gradeId,
            'element_id' => $this->elementId,
            'default_amount' => $this->defaultAmount,
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
