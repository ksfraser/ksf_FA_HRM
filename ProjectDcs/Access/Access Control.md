# FA HRM Module - Access Control Specification

## Document Information

| Field | Value |
|-------|-------|
| Document Title | Access Control Specification |
| Module | ksf_FA_HRM |
| Version | 1.0.0 |
| Author | KSF Development Team |
| Last Updated | May 2026 |

---

## 1. Access Control Overview

### 1.1 Purpose

This document defines the access control rules for the ksf_FA_HRM module, implementing the KSF unified access control model where:
- **Employees** see their own HR records
- **Managers** see their team's HR data
- **HR Admins** have full access to all HR records
- **System Administrators** have unrestricted access

### 1.2 Key Principles

| Principle | Description |
|-----------|-------------|
| Self-Service | Employees can view their own HR data |
| Manager View | Managers see records for their direct reports |
| Data Privacy | Personal data only visible to authorized personnel |
| Audit Trail | All access attempts logged for compliance |

---

## 2. Role Definitions

### 2.1 HR Module Roles

| Role | Code | Access Level |
|------|------|--------------|
| Employee | `employee` | Own records only |
| Manager | `manager` | Direct reports + own records |
| HR Manager | `hr_manager` | All employees |
| HR Admin | `hr_admin` | Full access + configuration |
| System Admin | `system_admin` | Unrestricted |

### 2.2 FrontAccounting Role Mapping

| HR Role | FA Security Role | Permissions |
|---------|------------------|-------------|
| Employee | `security_areas[SA_EMPLOYEES]` | View own |
| Manager | `security_areas[SA_EMPLOYEES] + view_team` | View own + team |
| HR Manager | `security_areas[SA_HRM_SETUP]` | View all |
| HR Admin | `security_areas[SA_HRM_SETUP + SA_HRM_TRANS]` | Full access |
| System Admin | All FA permissions | Unrestricted |

---

## 3. Record-Level Access Rules

### 3.1 Employee Records

| Field | Self (Employee) | Manager | HR Manager | HR Admin |
|-------|-----------------|---------|-------------|----------|
| Basic Info (name, dept) | Read | Read | Read | Read/Write |
| Contact Details | Read (own) | Read (team) | Read | Read/Write |
| Salary/Compensation | Hidden | Hidden | Read | Read/Write |
| Performance Ratings | Hidden | Read (team) | Read | Read/Write |
| Personal Documents | Read (own) | Hidden | Read | Read/Write |
| Emergency Contacts | Read (own) | Hidden | Read | Read/Write |
| Bank Details | Hidden | Hidden | Hidden | Read/Write |

### 3.2 Dependent Records

| Field | Self | Manager | HR Manager | HR Admin |
|-------|------|---------|-------------|----------|
| Dependent Name | Read | Hidden | Read | Read/Write |
| Relationship | Read | Hidden | Read | Read/Write |
| Date of Birth | Read | Hidden | Read | Read/Write |
| ID Documents | Hidden | Hidden | Read | Read/Write |

---

## 4. Manager Hierarchy

### 4.1 Team Visibility Rules

```
System Admin
    │
    └── HR Admin
            │
            └── HR Manager
                    │
                    └── Manager A ──────┬──▶ Employee A1 (direct report)
                            │          └──▶ Employee A2 (direct report)
                            │
                            └── Manager B
                                    │
                                    └── Employee B1 (reports to B)
```

### 4.2 Visibility Resolution

1. **Direct Report Check**: Is the employee a direct report of the current user?
2. **Skip-Level Check**: Does the user have skip-level visibility enabled?
3. **Department Check**: Does the user's department have cross-team visibility?

---

## 5. Access Control Implementation

### 5.1 Access Checker Service

```php
namespace Ksfraser\FA\HRM\Security;

class HRMAccessChecker {
    
    /**
     * Check if user can view a specific employee record
     */
    public function canViewEmployee(int $currentUserId, int $targetEmployeeId): bool {
        // System Admin or HR Admin can view all
        if ($this->hasRole($currentUserId, ['system_admin', 'hr_admin'])) {
            return true;
        }
        
        // HR Manager can view all employees
        if ($this->hasRole($currentUserId, ['hr_manager'])) {
            return true;
        }
        
        // Employee can view own record
        if ($currentUserId === $targetEmployeeId) {
            return true;
        }
        
        // Manager can view direct reports
        if ($this->isDirectReport($currentUserId, $targetEmployeeId)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can edit a specific employee record
     */
    public function canEditEmployee(int $currentUserId, int $targetEmployeeId): bool {
        if ($this->hasRole($currentUserId, ['system_admin', 'hr_admin'])) {
            return true;
        }
        
        // Employees can update some own fields (contact info, emergency contacts)
        if ($currentUserId === $targetEmployeeId) {
            return $this->canEditOwnField($targetEmployeeId);
        }
        
        return false;
    }
    
    /**
     * Get all employee IDs visible to the current user
     */
    public function getVisibleEmployeeIds(int $currentUserId): array {
        if ($this->hasRole($currentUserId, ['system_admin', 'hr_admin', 'hr_manager'])) {
            return $this->getAllEmployeeIds();
        }
        
        if ($this->hasRole($currentUserId, ['manager'])) {
            return array_merge(
                [$currentUserId],
                $this->getDirectReportIds($currentUserId)
            );
        }
        
        // Regular employee sees only self
        return [$currentUserId];
    }
}
```

### 5.2 Query Filter Hook

```php
/**
 * Hook to filter employee queries based on access rights
 */
function hrm_access_sql_filter(string $sql, int $userId): string {
    $accessChecker = new HRMAccessChecker();
    $visibleIds = $accessChecker->getVisibleEmployeeIds($userId);
    
    if (empty($visibleIds)) {
        return '1=0'; // No access
    }
    
    return 'e.id IN (' . implode(',', $visibleIds) . ')';
}
```

---

## 6. Sensitive Data Handling

### 6.1 Protected Fields

The following fields require elevated permissions to access:

| Field | Required Role |
|-------|---------------|
| Salary/Wages | HR Manager+ |
| Bank Account Details | HR Admin only |
| Social Security Numbers | HR Admin only |
| Tax Information | HR Manager+ |
| Performance Reviews | Manager+ (team), HR Manager+ (all) |

### 6.2 Data Masking

For users with partial access, sensitive fields are masked:

```php
public function maskSensitiveField(string $value, string $fieldType): string {
    return match($fieldType) {
        'bank_account' => substr($value, 0, 4) . '****',
        'ssn' => '***-**-' . substr($value, -4),
        'salary' => 'Confidential',
        default => $value
    };
}
```

---

## 7. Audit Logging

### 7.1 Logged Events

| Event | Data Logged |
|-------|-------------|
| Record Viewed | user_id, employee_id, fields_accessed, timestamp |
| Record Edited | user_id, employee_id, changes, timestamp |
| Bulk Export | user_id, record_count, filters, timestamp |
| Access Denied | user_id, attempted_employee_id, timestamp |

### 7.2 Audit Query

HR Managers and System Admins can query audit logs:

```php
public function getAuditLog(array $filters): array {
    // Only HR Admin or System Admin can query audit logs
    // Returns paginated audit records
}
```

---

## 8. Family Company Considerations

### 8.1 Family Employee Visibility

For family-owned companies where multiple family members are employees:

| Scenario | Visibility |
|----------|------------|
| Family member employee | Sees own records only |
| Parent as Manager | Sees child's HR data per manager rules |
| Spouse as HR Admin | Full visibility per HR Admin rules |
| Gift Flagged Transactions | Hidden from all except HR Admin |

### 8.2 Gift Flag for HR Benefits

HR transactions (bonuses, gifts, special allowances) can be marked with a `gift_flag`:

- Default visibility: Same as other HR data
- With `gift_flag=true`: Only visible to HR Admin

---

## 9. WordPress Integration (WP_ESS)

### 9.1 Employee Self-Service Portal

Employees accessing ksf_WP_ESS see only their own data:

```
WP_ESS Access:
├── My Profile (read own)
├── My Leave (read/write own)
├── My Timesheets (read/write own)
├── My Documents (read own)
└── My Emergency Contacts (read/write own)
```

### 9.2 ESS Access Control

```php
class WPEssAccessControl {
    public function getEssAllowedRecords(int $employeeId): array {
        // Always filter to the logged-in employee only
        return [
            'profile' => [$employeeId],
            'leave' => $this->getLeaveForEmployee($employeeId),
            'timesheets' => $this->getTimesheetsForEmployee($employeeId),
            'documents' => $this->getDocumentsForEmployee($employeeId)
        ];
    }
}
```

---

## 10. Compliance Considerations

### 10.1 GDPR Compliance

- Right to access: Employees can request their data
- Right to rectification: Employees can update some fields
- Data minimization: Only necessary data collected
- Retention: Data retained per policy, then anonymized

### 10.2 SOX Compliance

- All compensation changes audited
- Manager approval required for salary updates
- Segregation of duties enforced

---

## 11. Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | May 2026 | KSF Development Team | Initial specification |