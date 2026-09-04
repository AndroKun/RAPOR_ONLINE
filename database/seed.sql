USE raporonline;

INSERT INTO users (username, password_hash, role, is_active)
VALUES (
	'123',
	'$2y$12$AFmh9XMKjs3Y4WbIThJRDuGtYtAL9sVQBJP7H5UUiYJhjml3Bc.Vy',
	'admin',
	1
)
ON DUPLICATE KEY UPDATE
	password_hash = VALUES(password_hash),
	role = VALUES(role),
	is_active = VALUES(is_active);
