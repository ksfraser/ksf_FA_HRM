-- Retag contact types previously owned by ksf_FA_Common to their natural module.
-- Idempotent: safe to run on every activation.
UPDATE `0_ksf_contact_types` SET module = 'ksf_FA_HRM' WHERE name = 'employee' AND module = 'ksf_FA_Common';
UPDATE `0_ksf_contact_types` SET module = 'ksf_FA_HRM' WHERE name = 'team' AND module = 'ksf_FA_Common';
UPDATE `0_ksf_contact_types` SET module = 'ksf_FA_HRM' WHERE name = 'job_applicant' AND module = 'ksf_FA_Common';
