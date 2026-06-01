-- Update existing tickets to set created_by based on client_id where it's null
UPDATE tickets 
SET created_by = client_id 
WHERE created_by IS NULL AND client_id IS NOT NULL;

-- Update any tickets with string issue values to match issue_list IDs
-- This handles cases where issue column still has string values
UPDATE tickets t
INNER JOIN issue_list il ON t.issue = il.issue
SET t.issue_id = il.id
WHERE t.issue_id IS NULL AND t.issue IS NOT NULL;

-- Clean up: Set issue column to NULL since we now use issue_id
UPDATE tickets SET issue = NULL WHERE issue_id IS NOT NULL;
