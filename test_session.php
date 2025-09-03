<?php
require_once 'config.php';

echo "<h2>Session Debug Information</h2>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Data:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><strong>Login Functions Test:</strong></p>";
echo "<p>isLoggedIn(): " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "</p>";
echo "<p>getUserId(): " . (getUserId() ?? 'NULL') . "</p>";
echo "<p>getUserRole(): " . getUserRole() . "</p>";
echo "<p>getUsername(): " . getUsername() . "</p>";
?>
