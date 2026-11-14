-- Safe to re-run. Does not create accounts or predictable passwords.
INSERT INTO services (service_code,service_name,description,queue_prefix,estimated_minutes,is_active) VALUES
('TOR','Transcript of Records','Request an official transcript of records.','TOR',15,1),
('COE','Certificate of Enrollment','Obtain proof of current enrollment.','COE',8,1),
('COG','Certificate of Grades','Request a certificate of grades.','COG',12,1),
('ENR','Enrollment Concerns','Enrollment-related questions and assistance.','ENR',15,1),
('VER','Student Records Verification','Verify student records and personal information.','VER',10,1),
('AUTH','Document Authentication','Authenticate official school documents.','AUT',12,1),
('DIP','Diploma Requests','Diploma release and request concerns.','DIP',15,1),
('GEN','General Inquiries','General registrar inquiries and assistance.','GEN',6,1)
ON DUPLICATE KEY UPDATE service_name=VALUES(service_name);

INSERT INTO counters(counter_number,counter_name,is_active) VALUES
('1','Records Counter',1),('2','Enrollment Counter',1),('3','General Assistance',1)
ON DUPLICATE KEY UPDATE counter_name=VALUES(counter_name);

INSERT IGNORE INTO counter_services (counter_id,service_id)
SELECT c.id,s.id FROM counters c CROSS JOIN services s
WHERE (c.counter_number='1' AND s.service_code IN('TOR','COG','VER','AUTH','DIP'))
   OR (c.counter_number='2' AND s.service_code IN('COE','ENR'))
   OR (c.counter_number='3' AND s.service_code IN('GEN','ENR','COE'));

INSERT INTO system_settings(setting_key,setting_value) VALUES
('office_name','University Registrar'),
('opening_time','08:00'),
('closing_time','17:00'),
('queue_reset_daily','1'),
('allow_unverified_students','1'),
('announcement_ticker','Welcome to the Registrar Office. Please watch your queue number.'),
('queue_suspended','0'),
('enforce_office_hours','0')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);
