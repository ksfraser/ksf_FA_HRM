<?php

declare(strict_types=1);

namespace ksfraser\FrontAccounting\HRM\Entity;

class PayElement
{
    /** @var int */
    private $elementId;
    /** @var string */
    private $elementCode;
    /** @var string */
    private $elementName;
    /** @var string */
    private $category;
    /** @var string */
    private $calculationType;
    /** @var float */
    private $defaultValue;
    /** @var string|null */
    private $glAccountCode;
    /** @var bool */
    private $isActive;

    public function __construct(array $data)
    {
        $this->elementId = (int)$data['element_id'];
        $this->elementCode = $data['element_code'];
        $this->elementName = $data['element_name'];
        $this->category = $data['category'];
        $this->calculationType = $data['calculation_type'] ?? 'fixed';
        $this->defaultValue = (float)($data['default_value'] ?? 0);
        $this->glAccountCode = $data['gl_account_code'] ?? null;
        $this->isActive = (bool)($data['is_active'] ?? 1);
    }

    public function getElementId(): int { return $this->elementId; }
    public function getElementCode(): string { return $this->elementCode; }
    public function getElementName(): string { return $this->elementName; }
    public function getCategory(): string { return $this->category; }
    public function getCalculationType(): string { return $this->calculationType; }
    public function getDefaultValue(): float { return $this->defaultValue; }
    public function getGlAccountCode(): ?string { return $this->glAccountCode; }
    public function isActive(): bool { return $this->isActive; }

    public function toArray(): array
    {
        return [
            'element_id' => $this->elementId,
            'element_code' => $this->elementCode,
            'element_name' => $this->elementName,
            'category' => $this->category,
            'calculation_type' => $this->calculationType,
            'default_value' => $this->defaultValue,
            'gl_account_code' => $this->glAccountCode,
            'is_active' => $this->isActive ? 1 : 0,
        ];
    }
}
