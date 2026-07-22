<?php
// Fallback for Web Share Target if Service Worker is not active
header("Location: index.php?share_error=1");
exit;
