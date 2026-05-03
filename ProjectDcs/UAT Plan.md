# UAT Plan - ksf_FA_HRM

## Overview

This document defines the User Acceptance Test (UAT) cases for the ksf_FA_HRM module - Human Resources Management extension for FrontAccounting.

---

## 1. UAT Objectives

### 1.1 Goal
Verify that the ksf_FA_HRM module meets business requirements and is ready for production deployment.

### 1.2 Success Criteria
- All critical business workflows function correctly
- Data integrity maintained
- Payroll GL integration works accurately
- Import functionality processes data correctly
- Module installs/uninstalls cleanly

---

## 2. UAT Scope

### 2.1 In Scope
- Employee management (CRUD)
- Compensation and benefits handling
- Payroll posting to General Ledger
- CSV import with field mapping
- Module lifecycle (install/uninstall)
- Permission-based access

### 2.2 Out of Scope
- Performance testing
- Security penetration testing
- Integration with external systems (ksf_Documents, ksf_FA_Timesheets)

---

## 3. User Roles

| Role | Responsibilities | Test Accounts |
|------|------------------|---------------|
| HR Administrator | Full HRM access, manage employees, run imports | TestAdmin |
| HR Manager | View employees, manage compensation | TestManager |
| HR Viewer | Read-only access to employee data | TestViewer |
| Payroll Accountant | Manage compensation, post payroll | TestPayroll |

---

## 4. Test Scenarios

### 4.1 Module Installation

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-INST-01 | Fresh installation of ksf_FA_HRM | Module installs without errors |
| UAT-INST-02 | Verify all database tables created | All 7 tables exist with correct schema |
| UAT-INST-03 | HRM menu item appears in navigation | Menu visible to authorized users |
| UAT-INST-04 | Default preferences set correctly | Company preferences contain HRM defaults |
| UAT-INST-05 | Module uninstallation | All tables dropped, no residue |

### 4.2 Employee Management

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-EMP-01 | Add new employee with all fields | Employee created, data stored correctly |
| UAT-EMP-02 | Search for employee by name | Correct employee found |
| UAT-EMP-03 | Search for employee by department | Correct employees returned |
| UAT-EMP-04 | Filter employees by status (Active) | Only active employees displayed |
| UAT-EMP-05 | Edit employee details | Changes saved successfully |
| UAT-EMP-06 | Change employee status to Terminated | Status updated, termination_date set |
| UAT-EMP-07 | Add emergency contact to employee | Contact saved under employee |
| UAT-EMP-08 | Add dependent to employee | Dependent saved under employee |
| UAT-EMP-09 | View employee comprehensive details | All related data displayed |

#### UAT-EMP-01 Add New Employee - Detailed Steps
1. Log in as HR Administrator
2. Navigate to HRM → Employees
3. Click "Add Employee"
4. Fill in required fields:
   - Employee Number: EMP001
   - First Name: John
   - Last Name: Doe
   - Email: john.doe@company.com
   - Department: Engineering
   - Job Title: Software Developer
   - Hire Date: 2024-01-15
5. Click "Save"
6. Verify employee appears in list with correct data

### 4.3 Compensation Management

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-COMP-01 | Create salary grade with range | Grade created, min/max stored |
| UAT-COMP-02 | Create hourly grade | Grade with hourly rates created |
| UAT-COMP-03 | Assign grade to employee | Grade linked to compensation record |
| UAT-COMP-04 | Set employee salary | Annual salary saved correctly |
| UAT-COMP-05 | Set employee hourly rate | Hourly rate saved (36.06 for $75k/yr) |
| UAT-COMP-06 | Enable overtime for employee | OT eligibility set with multiplier |
| UAT-COMP-07 | Validate salary within grade | System accepts/out-of-range rejected |
| UAT-COMP-08 | View compensation history | Historical records displayed |

#### UAT-COMP-01 Create Salary Grade - Detailed Steps
1. Navigate to HRM → Compensation → Grades
2. Click "Add Grade"
3. Fill in:
   - Code: GRADE01
   - Name: Junior Developer
   - Min Salary: 50000
   - Max Salary: 80000
   - Min Hourly: 24.00
   - Max Hourly: 38.50
   - Level: 1
4. Save grade
5. Verify grade appears in list

### 4.4 Benefits Administration

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-BEN-01 | Create percentage-based benefit | Benefit with rate created |
| UAT-BEN-02 | Create fixed-amount benefit | Benefit with fixed amount created |
| UAT-BEN-03 | Configure EI benefit (mandatory) | Mandatory flag set, EI rules applied |
| UAT-BEN-04 | Configure CPP benefit | CPP parameters stored |
| UAT-BEN-05 | Set benefit GL codes | Expense/liability codes assigned |
| UAT-BEN-06 | Mark benefit as tax deductible | Tax flag set correctly |
| UAT-BEN-07 | Deactivate benefit plan | active=0, no new enrollments |

### 4.5 Payroll Integration

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-PAY-01 | Generate payroll journal entry | Balanced journal created |
| UAT-PAY-02 | Verify journal reference format | PR-YYYYMM-XXX format correct |
| UAT-PAY-03 | Post salary entry to GL | Journal posted to general ledger |
| UAT-PAY-04 | Post overtime entry to GL | OT journal posted correctly |
| UAT-PAY-05 | Post employer benefits to GL | Liability accounts credited |
| UAT-PAY-06 | View payroll history | Past payroll entries displayed |
| UAT-PAY-07 | Verify GL balancing | Total debits = Total credits |

#### UAT-PAY-03 Post Salary to GL - Detailed Steps
1. Navigate to HRM → Payroll
2. Select employee(s) for payroll
3. Review generated journal entry:
   - Debit: Salary Expense (G01)
   - Credit: Cash/Bank
4. Click "Post to GL"
5. Verify Journal created in FA:
   - Check FA → Banking → Journal Entries
   - Entry with reference PR-YYYYMM-XXX exists

### 4.6 CSV Import

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-IMP-01 | Upload valid CSV file | File accepted, preview shown |
| UAT-IMP-02 | Auto-map common fields | Fields auto-matched correctly |
| UAT-IMP-03 | Manual field mapping | User can override mappings |
| UAT-IMP-04 | Import new employees | New records created |
| UAT-IMP-05 | Import existing employees (upsert) | Existing records updated |
| UAT-IMP-06 | Import with missing required field | Row skipped, error shown |
| UAT-IMP-07 | Import with all fields | Complete data imported |
| UAT-IMP-08 | Import large file (>1000 rows) | Successfully processed |

#### UAT-IMP-04 Import New Employees - Detailed Steps
1. Prepare CSV file with new employees:
   ```
   emp_no,first_name,last_name,email,department
   EMP100,Alice,Johnson,alice@company.com,Sales
   EMP101,Bob,Williams,bob@company.com,Marketing
   ```
2. Navigate to HRM → Import Employees
3. Click "Choose File" and select CSV
4. Click "Next"
5. Verify field mapping screen shows:
   - File columns on left
   - Target fields on right
6. Accept auto-mapped fields
7. Click "Import"
8. Verify success message
9. Check employee list: EMP100 and EMP101 now present

### 4.7 Configuration

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-CFG-01 | Change salary expense GL code | Code updated, used in new entries |
| UAT-CFG-02 | Configure overtime GL | Overtime posts to new GL |
| UAT-CFG-03 | Update work hours (2080→2088) | Hours saved for calculations |
| UAT-CFG-04 | Disable overtime | System respects disabled setting |

### 4.8 Permissions

| Scenario | Description | Expected Result |
|----------|-------------|-----------------|
| UAT-PERM-01 | HRM permission grants access | Menu visible when permission assigned |
| UAT-PERM-02 | No permission - no access | Menu hidden, direct URL rejected |
| UAT-PERM-03 | Read-only user sees data | Cannot modify employee records |
| UAT-PERM-04 | Admin can modify | Full CRUD access granted |

---

## 5. Test Data Requirements

### 5.1 Sample Employee Data

| Field | Value |
|-------|-------|
| Employee Number | EMP-UAT-001 |
| First Name | Test |
| Last Name | User |
| Email | test.user@company.com |
| Phone | 555-0001 |
| Department | QA |
| Job Title | UAT Tester |
| Status | Active |
| Hire Date | 2024-01-01 |

### 5.2 Sample Compensation

| Field | Value |
|-------|-------|
| Annual Salary | 60000.00 |
| Hourly Rate | 28.85 |
| Employee Type | Salary |
| OT Eligible | Yes |
| OT Multiplier | 1.5 |

### 5.3 Sample Benefits

| Code | Type | Employer Rate | Employee Rate |
|------|------|---------------|---------------|
| EI | Government | 2.28% | 1.66% |
| CPP | Government | 5.95% | 5.95% |
| HEALTH | Medical | 450.00 (monthly) | 150.00 (monthly) |

### 5.4 Test CSV File

```csv
emp_no,first_name,last_name,email,phone,department,position,joined_date,salary
UAT001,David,Brown,david.brown@company.com,555-1001,IT,Analyst,2024-02-01,55000
UAT002,Emma,Wilson,emma.wilson@company.com,555-1002,Sales,Representative,2024-02-15,48000
UAT003,Frank,Miller,frank.miller@company.com,555-1003,HR,Coordinator,2024-03-01,52000
```

---

## 6. UAT Workflow

### 6.1 Execution Steps

1. **Setup** (Pre-UAT)
   - Install module on test environment
   - Create test user accounts
   - Configure GL codes

2. **Execution** (During UAT)
   - Execute each scenario
   - Record results
   - Capture screenshots
   - Document issues

3. **Review** (Post-UAT)
   - Review test results
   - Document defects
   - Assess readiness

### 6.2 Sign-Off Criteria

- [ ] All critical scenarios pass
- [ ] All high-priority defects resolved
- [ ] Business user signs off
- [ ] Documentation complete

---

## 7. Defect Reporting

### 7.1 Template

```
UAT Defect Report
=================
Defect ID: [UAT-DEF-XXX]
Date: [YYYY-MM-DD]
Reported By: [Name]
Scenario: [UAT-XX-##]
Priority: [Critical/High/Medium/Low]

Description:
[Brief description of the issue]

Steps to Reproduce:
1. [Step 1]
2. [Step 2]
3. [Step 3]

Expected Result:
[What should happen]

Actual Result:
[What actually happened]

Screenshots:
[Attach screenshots]

Resolution:
[How it was fixed / Not applicable]
```

---

## 8. Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| GL posting fails | Medium | High | Test thoroughly, check FA logs |
| Import data corruption | Low | High | Backup data, test on copy |
| Permission issues | Medium | Medium | Test multiple user roles |
| Performance issues | Low | Low | Keep data sets reasonable |

---

## 9. UAT Schedule

| Day | Activity |
|-----|----------|
| Day 1 | Installation & Setup |
| Day 2 | Employee Management |
| Day 3 | Compensation & Benefits |
| Day 4 | Payroll & Import |
| Day 5 | Issue Resolution & Sign-off |

---

## 10. Sign-off

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Business Owner | | | |
| Project Manager | | | |
| QA Lead | | | |
| Technical Lead | | | |

---

## Appendix A: Test Environment Details

- FrontAccounting Version: 2.4.x
- PHP Version: 8.1.x
- Database: MySQL 5.7.x
- Test Company: UAT_TEST_CO
- Module Version: 1.0.0

---

## Appendix B: References

- Functional Requirements: `./Functional Requirements.md`
- Architecture: `./Architecture.md`
- Test Plan: `./Test Plan.md`
- Business Requirements: `./Business Requirements.md`
