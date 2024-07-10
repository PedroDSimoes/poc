CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
    block CHAR(1) NOT NULL,
    cell INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE (block, cell)
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

ALTER TABLE characters
ADD COLUMN xp INT NOT NULL DEFAULT 0,
ADD COLUMN money INT NOT NULL DEFAULT 0;

ALTER TABLE users
ADD COLUMN work_lock_time INT DEFAULT 0;

ALTER TABLE users
ADD COLUMN current_workplace VARCHAR(50) DEFAULT NULL;

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('weapon', 'armor', 'carry') NOT NULL
);

CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES items(id)
);

CREATE TABLE equipped_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    slot ENUM('weapon', 'armor') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (item_id) REFERENCES items(id)
);

INSERT INTO items (name, description, type) VALUES ('Knife', 'A sharp knife.', 'weapon');
INSERT INTO items (name, description, type) VALUES ('Bulletproof Vest', 'Provides protection.', 'armor');

ALTER TABLE items ADD COLUMN price INT NOT NULL DEFAULT 0;

-- Update existing items with prices
UPDATE items SET price = 10 WHERE id = 1;  -- Knife price
UPDATE items SET price = 20 WHERE id = 2;  -- Bulletproof Vest price

CREATE TABLE quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    reward_xp INT NOT NULL
);

CREATE TABLE user_quests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quest_id INT NOT NULL,
    status ENUM('accepted', 'in_progress', 'completed') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (quest_id) REFERENCES quests(id)
);


-- Insert demo quest
INSERT INTO quests (name, description, reward_xp) VALUES ('Demo Quest', 'this quest is a test, you can accept, go to the kitchen and pick up a cooking pot, then come back and give me the pot.', 100);
INSERT INTO items (name, description, type, price) VALUES ('Cooking Pot', 'A pot used for cooking.', 'carry', 0);
ALTER TABLE characters ADD COLUMN unallocated_points INT NOT NULL DEFAULT 0;

ALTER TABLE items ADD COLUMN damage INT NOT NULL DEFAULT 0;
ALTER TABLE items ADD COLUMN armor INT NOT NULL DEFAULT 0;

-- Update existing items with stats
UPDATE items SET damage = 2 WHERE id = 1;  -- Knife gives 2 damage
UPDATE items SET armor = 2 WHERE id = 2;   -- Bulletproof Vest gives 2 armor

ALTER TABLE characters
ADD COLUMN damage INT NOT NULL DEFAULT 0,
ADD COLUMN armor INT NOT NULL DEFAULT 0;

ALTER TABLE characters ADD COLUMN hp INT NOT NULL DEFAULT 50;
ALTER TABLE characters ADD COLUMN last_attack_time TIMESTAMP NULL DEFAULT NULL;
