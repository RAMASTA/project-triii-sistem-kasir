<?php
require __DIR__ . '/includes/bootstrap.php';
redirect(uid() ? 'dashboard.php' : 'login.php');
