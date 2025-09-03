-- Add lead_classification column to leads table
ALTER TABLE leads 
ADD COLUMN lead_classification VARCHAR(50) DEFAULT NULL 
AFTER source;

-- Add comment to the column
ALTER TABLE leads 
MODIFY COLUMN lead_classification VARCHAR(50) 
COMMENT 'Employment classification: Locally/Internationally Employed, OFW, Self employed';
