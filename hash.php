<?php
echo password_hash('Admin@123456', PASSWORD_BCRYPT);
echo "<br>";
echo password_hash('Platform@123', PASSWORD_BCRYPT);
echo "<br>";
echo password_hash('Doctor@123456', PASSWORD_BCRYPT);
echo "<br>";
echo password_hash('Nurse@123', PASSWORD_BCRYPT);
?>
<!-- http://localhost/healthcare-project/hash.php -->
