ALTER TABLE `bf_leads`
    ADD COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'Em preparação interna' AFTER `total_savings`;

UPDATE `bf_leads`
    SET `status` = COALESCE(`status`, 'Em preparação interna');
