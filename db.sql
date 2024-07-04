CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Adding indexes to frequently queried fields
CREATE INDEX idx_username ON users (username);
CREATE INDEX idx_email ON users (email);

CREATE TABLE characters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    character_name VARCHAR(100) NOT NULL,
    strength INT NOT NULL DEFAULT 0,
    dexterity INT NOT NULL DEFAULT 0,
    constitution INT NOT NULL DEFAULT 0,
    negotiation INT NOT NULL DEFAULT 0,
    level INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);