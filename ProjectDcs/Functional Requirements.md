# Functional Requirements - ksf_FA_HRM

## Overview

This document outlines the functional requirements for the Human Resources Management (HRM) module for FrontAccounting. The module provides comprehensive employee management, compensation tracking, benefits administration, and payroll GL integration capabilities.

---

## 1. Employee Management

### 1.1 Employee Master Data

**FR-HRM-001**: The system shall allow users to create new employee records with the following fields:
- Employee Number (unique identifier)
- First Name (required)
- Last Name (required)
- Email Address
- Phone Number
- Department
- Job Title
- Hire Date
- Status (Active/Inactive/Terminated)
- Termination Date (optional)

**FR-HRM-002**: The system shall support employee organizational hierarchy:
- Manager ID (self-referencing)
- Career Manager ID
- Operations Manager ID
- Team ID

**FR-HRM-003**: The system shall allow editing of employee records with audit trail of changes.

**FR-HRM-004**: The system shall support employee status changes:
- Active: Currently employed
- Inactive: On leave or temporary status
- Terminated: Employment ended

### 1.2 Employee Search and Listing

**FR-HRM-005**: The system shall provide a searchable list of all employees with filtering by:
- Department
- Job Title
- Status
- Manager

**FR-HRM-006**: The system shall support quick search by employee name or number.

### 1.3 Emergency Contacts

**FR-HRM-007**: The system shall allow storing multiple emergency contacts per employee:
- Contact Name
- Relationship
- Phone Number
- Alternate Phone
- Email
- Address
- Primary Contact Flag

### 1.4 Dependents/Beneficiaries

**FR-HRM-008**: The system shall track employee dependents:
- First Name
- Last Name
- Relationship
- Date of Birth
- SIN (Social Insurance Number)
- Tax Credit Eligibility
- Insurance Eligibility
- Effective/End Dates

---

## 2. Compensation Management

### 2.1 Salary Grades

**FR-HRM-010**: The system shall support salary grade definitions:
- Grade Code (unique)
- Grade Name
- Minimum Salary
- Maximum Salary
- Minimum Hourly Rate
- Maximum Hourly Rate
- Description
- Level
- Active Status

**FR-HRM-011**: The system shall validate compensation falls within grade ranges.

### 2.2 Employee Compensation

**FR-HRM-012**: The system shall store employee compensation details:
- Employee ID (foreign key)
- Grade ID (optional)
- Percent of Grade
- Annual Salary
- Hourly Rate
- Employee Type (Salary/Hourly)
- Effective Date
- End Date
- Overtime Eligibility
- Overtime Multiplier (default 1.5)
- GL Code for Salary
- GL Code for Overtime
- Bonus Target

**FR-HRM-013**: The system shall support historical compensation records with effective dating.

### 2.3 Overtime Management

**FR-HRM-014**: The system shall support overtime eligibility flag per employee.

**FR-HRM-015**: The system shall allow configurable overtime multiplier (default 1.5x).

---

## 3. Benefits Administration

### 3.1 Benefit Plans

**FR-HRM-020**: The system shall define benefit plans with:
- Benefit Name
- Benefit Code (unique)
- Benefit Type
- Employer Rate (percentage)
- Employee Rate (percentage)
- Fixed Amount (if not percentage-based)
- Calculation Period (Monthly/Yearly/etc.)
- GL Code for Expense
- GL Code for Liability
- Provider Name
- Description
- Mandatory Flag
- Tax Deductible Flag
- Active Status

**FR-HRM-021**: The system shall support both percentage-based and fixed-amount benefits.

**FR-HRM-022**: The system shall distinguish between mandatory benefits (EI, CPP) and optional benefits.

### 3.2 Employee Benefits Assignment

**FR-HRM-023**: The system shall allow assignment of benefits packages to employees.

**FR-HRM-024**: The system shall calculate employer and employee contributions based on rates.

---

## 4. Payroll Integration

### 4.1 GL Code Configuration

**FR-HRM-030**: The system shall provide configurable GL codes for:
- Salary Expense (default: G01)
- Overtime Expense (default: O01)
- Vacation Expense
- Sick Leave Expense
- EI Expense
- CPP Expense
- EI Liability
- CPP Liability
- Pension Expense
- Pension Liability
- Health Benefit Expense
- Health Benefit Liability

### 4.2 Journal Entry Generation

**FR-HRM-031**: The system shall generate journal entries for payroll transactions.

**FR-HRM-032**: Journal entries shall include:
- Date
- Reference Number (auto-generated: PR-YYYYMM-XXX)
- Description
- Employee ID
- GL Lines (debit/credit)
- Total Debit
- Total Credit

**FR-HRM-033**: Journal entries shall balance (total debits = total credits).

### 4.3 Payroll Posting

**FR-HRM-034**: The system shall post payroll entries to the general ledger.

**FR-HRM-035**: The system shall track posted payroll with GL reference.

### 4.4 Work Hours Configuration

**FR-HRM-036**: The system shall support configurable parameters:
- Annual Work Hours (default: 2080)
- Weekly Work Hours (default: 40)

---

## 5. Data Import

### 5.1 CSV Import

**FR-HRM-040**: The system shall support CSV file import for employee data.

**FR-HRM-041**: The system shall display a two-step import wizard:
- Step 1: File upload
- Step 2: Field mapping

**FR-HRM-042**: The system shall support auto-mapping of common field names.

**FR-HRM-043**: Import shall support upsert (update existing or create new).

**FR-HRM-044**: Target fields for import:
- emp_no
- first_name
- last_name
- email
- phone
- department
- position
- location
- joined_date
- salary
- hourly_rate

### 5.2 Import Validation

**FR-HRM-045**: Import shall validate required fields (emp_no).

**FR-HRM-046**: Import shall skip rows with missing required fields.

---

## 6. Module Installation

### 6.1 Installation Process

**FR-HRM-050**: The system shall create all required database tables on installation.

**FR-HRM-051**: The system shall add menu items on installation.

**FR-HRM-052**: The system shall set default company preferences on installation.

### 6.2 Uninstallation Process

**FR-HRM-053**: The system shall remove all created tables on uninstallation.

---

## 7. User Interface

### 7.1 Menu Structure

**FR-HRM-060**: The module shall add an "HRM" menu entry to the main navigation.

**FR-HRM-061**: The menu shall be accessible based on FA permission system.

### 7.2 Forms and Views

**FR-HRM-062**: The system shall provide forms for:
- Employee creation/editing
- Compensation management
- Benefits configuration
- Import wizard

---

## 8. Integration Requirements

### 8.1 ksf_Documents Integration

**FR-HRM-070**: The system shall support attachment of documents to employee records via ksf_Documents module.

### 8.2 ksf_FA_Timesheets Integration

**FR-HRM-071**: The system shall integrate with timesheet module for time tracking.

### 8.3 GPG Key Management for Employees

**FR-HRM-072**: The system shall provide a link to manage GPG keys for each employee.
**FR-HRM-073**: The system shall call the common GPG key management screen with `contact_type=employee`.
**FR-HRM-074**: The system shall display GPG key status on employee summary.
**FR-HRM-075**: The system shall support GPG signing of employee documents.

#### Implementation TODO
```php
// TODO: Add GPG key management link to employee page
// In employee detail view:
echo "<a href='modules/ksf_FA_GPG/pages/key_management.php?contact_type=employee&contact_id=" . $person_id . "'>";
echo _("Manage GPG Key");
echo "</a>";

// TODO: Display GPG key status on employee summary
$data = ['contact_type' => 'employee', 'contact_id' => $person_id];
$hasKey = hook_invoke('ksf_FA_GPG', 'hasCapability', $data, ['capability' => 'sign']);
```

### 8.4 GPG Email Integration

**FR-HRM-076**: The system shall support GPG signing of employee communications.
**FR-HRM-077**: The system shall support GPG encryption when encrypt flag is set.

#### Implementation TODO
```php
// TODO: Add GPG signing to employee email sending
$data = [
    'contact_type' => 'employee',
    'contact_id' => $person_id,
    'email' => $employeeEmail,
    'file_path' => $attachmentPath,
];
hook_invoke_all('gpg_sign', $data);
```

---

## 9. Non-Functional Requirements

### 9.1 Performance

**FR-HRM-080**: Employee list shall load within 2 seconds for up to 1000 records.

**FR-HRM-081**: Import shall process 500 records within 30 seconds.

### 9.2 Security

**FR-HRM-090**: Salary data shall be restricted based on permission levels.

**FR-HRM-091**: All data access shall be filtered by company in multi-company setup.

### 9.3 Data Integrity

**FR-HRM-100**: Employee numbers shall be unique.

**FR-HRM-101**: Benefit codes shall be unique.

**FR-HRM-102**: Grade codes shall be unique.

---

## 10. Acceptance Criteria Summary

| Requirement ID | Acceptance Criteria |
|----------------|---------------------|
| FR-HRM-001 | User can create employee with all specified fields |
| FR-HRM-005 | Search returns filtered results correctly |
| FR-HRM-012 | Compensation records are stored with all fields |
| FR-HRM-031 | Journal entries are generated with correct structure |
| FR-HRM-034 | Payroll posts to GL successfully |
| FR-HRM-042 | Field auto-mapping works for common field names |
| FR-HRM-053 | Tables removed on uninstall |
