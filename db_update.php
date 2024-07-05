<?php
include 'db.php';

$stmt = $conn->prepare("
    ALTER TABLE characters
    ADD COLUMN xp INT NOT NULL DEFAULT 0,
    ADD COLUMN money INT NOT NULL DEFAULT 0;
");
$stmt->execute();

echo "Database updated successfully.";
?>