CREATE TABLE IF NOT EXISTS push_subscriptions (
  id       CHAR(36)     NOT NULL,
  user_id  CHAR(36)     NOT NULL,
  endpoint TEXT         NOT NULL,
  p256dh   VARCHAR(300) NOT NULL,
  auth     VARCHAR(100) NOT NULL,
  created_at DATETIME   NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY  uq_endpoint (endpoint(255)),
  KEY         idx_user_id (user_id),
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
