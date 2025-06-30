<?php
session_start();

// Eliminar variables de sesión
session_unset();

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: login.php");
exit;
