-- Tabla para los tokens de recuperación de contraseña.
-- Se aplica automáticamente al arrancar (app/core/Migrator.php).

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_prt_token (token_hash),
  INDEX idx_prt_user (user_id),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
