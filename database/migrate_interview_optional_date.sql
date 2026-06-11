-- Migration: make interviews.scheduled_date optional
-- Compatible con MySQL 5.7+

ALTER TABLE interviews MODIFY scheduled_date DATE NULL;
