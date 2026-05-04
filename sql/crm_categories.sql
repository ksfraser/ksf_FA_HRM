-- Extend FA's crm_categories with employee/lead/opportunity contact types
-- These are HRM contact types (not CRM - CRM only handles customer contacts)
-- All contacts are persons in 0_crm_persons, linked via 0_crm_contacts

INSERT IGNORE INTO `0_crm_categories` (`type`, `action`, `name`, `description`, `system`) VALUES
('employee', 'general', 'Employee', 'Employee contact record', 0),
('employee', 'emergency', 'Emergency Contact', 'Emergency contact for employee', 0),
('employee', 'dependent', 'Dependent', 'Employee dependent for benefits', 0);
