<?php
$secret = bin2hex(random_bytes(32));
echo "\nCopy this key to the .env file like this:\n";
echo "JWT_SECRET=$secret\n";
?>