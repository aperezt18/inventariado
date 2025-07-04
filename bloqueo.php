<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Bloqueado</title>
    <style>
        body {
            background-color: #111;
            color: #f00;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .mensaje {
            text-align: center;
            border: 3px solid #f00;
            padding: 30px;
            border-radius: 15px;
            background-color: #222;
            font-size: 1.8rem;
            font-weight: bold;
            animation: parpadeo 1s infinite;
        }
        @keyframes parpadeo {
            0%, 100% {opacity: 1;}
            50% {opacity: 0.3;}
        }
        a {
            display: inline-block;
            margin-top: 20px;
            color: #f00;
            font-weight: normal;
            text-decoration: underline;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="mensaje">
        El período de uso de la aplicación ha <br> <span style="color:yellow;">expirado</span>.<br><br>
        Contacta con el administrador para <br> reactivar el sistema.<br>
        <a href="reactivar.php">Intentar reactivar</a>
    </div>
</body>
</html>
