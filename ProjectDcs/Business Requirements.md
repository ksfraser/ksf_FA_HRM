# Business Requirements - HRM Access Control (BR-001-HRM-Access-RBAC.md)

## Overview
Implement Role-Based Access Control (RBAC) for the HRM module to enforce granular permissions based on department, team, and position hierarchies.

## Scope
- Define access levels for HRM modules (Admin, Team Members, Regular Users)
- Establish permission matrix linking roles to departments and teams
- Configure default access policies (READ ONLY, TEAM, FULL)

## Requirements

### 1. Role Hierarchy
- **Administrator** - Full access to all HRM records
- **Team Member** - Access limited to own team/department
- **Regular User** - Read-only access to HRM records

### 2. Access Levels
- **FULL** - Complete access to all HRM entities (employees, positions, benefits)
- **TEAM** - Access restricted to own team/department
- **READ_ONLY** - Read-only access to HRM records

### 3. Default Behavior
- If RBAC is not installed → Default to READ_ONLY
- HRM users with Admin FA security → Full access
- All other users → READ_ONLY or TEAM based on assignment

### 4. Integration Points
- CRM module: Apply RBAC when accessing employee records
- HRM module: Enforce RBAC for all internal operations
- Project Management: Apply RBAC for cross-module access

## Implementation Notes
- Leverage existing HRM entity hierarchy (Department → Position → Role)
- Use RBAC grid helper for consistent permission mapping
- Cache RBAC queries to improve performance
- Document permission matrix in RbacGridHelpers.php

## Acceptance Criteria
- [ ] HRM Admins can access all records
- [ ] Team members can only access their assigned team/department
- [ ] Regular users have READ_ONLY access
- [ ] RBAC queries are cached for performance
- [ ] Default fallback to READ_ONLY when RBAC not configured
