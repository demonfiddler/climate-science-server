<?php
if ($argc != 2) {
    echo "Supply the plaintext password as a command line argument";
    exit;
}
$hash = password_hash($argv[1],  PASSWORD_BCRYPT);
echo "Insert this password hash into the user table in the database:\n";
echo "$hash\n";
?>