# Use Cases - ksf_FA_HRM

## Overview
ksf_FA_HRM provides the FrontAccounting UI and database integration for the ksf_HRM core module.

## Extension of Core Use Cases

This adapter extends the use cases defined in ksf_HRM/ProjectDcs/Use Case.md with FA-specific functionality.

---

## UC-FA-HRM-001: Employee Entry via FA Form
**Actor**: HR Manager (FA User)

**FA-Specific Flow**:
1. Navigate: HR > Employees > New Employee
2. FA form with:
   - Employee fields (from ksf_HRM)
   - FA dimension selection
   - FA bank account selection
3. Save creates:
   - Record in `fa_hrm_employees` table
   - Links to FA user (optional)
   - Links to FA dimension

---

## UC-FA-HRM-002: View Employee List
**Actor**: HR Manager

**FA-Specific Flow**:
1. Navigate: HR > Employees > List Employees
2. FA table view with:
   - Sorting, filtering
   - Quick search
   - Export to CSV
3. Links to FA customer/supplier views for employee-type contacts

---

## UC-FA-HRM-003: Payroll GL Integration
**Actor**: System, Finance

**FA-Specific Flow**:
1. Timesheet approved (ksf_Timesheets)
2. ksf_HRM calculates pay
3. ksf_FA_HRM creates:
   - GL journal entries for wages
   - Bank payment voucher
   - Leave liability entries
4. Posts to FA general ledger
5. Bank payment processed via FA

---

## UC-FA-HRM-004: T4/T4A Generation
**Actor**: HR, Finance

**FA-Specific Flow**:
1. Year-end processing
2. ksf_FA_HRM generates:
   - T4 slip data from FA payroll
   - T4A slip data from FA contractor payments
3. Links to FA tax tables
4. Export to CRA-compatible format

---

## UC-FA-HRM-005: Bank Account for Direct Deposit
**Actor**: HR Manager, Employee

**FA-Specific Flow**:
1. Employee enters banking info
2. ksf_FA_HRM:
   - Creates/links FA bank account
   - Sets up direct deposit configuration
3. Bank account usable for:
   - Payroll (FA bank payments)
   - Expense reimbursements

## Reference Use Cases
- Core UC: ksf_HRM/ProjectDcs/Use Case.md (UC-001 through UC-012)

*Document Version: 1.0.0*
*Last Updated: 2026-05-11*