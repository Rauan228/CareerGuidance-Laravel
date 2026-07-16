<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* Основной стиль страницы */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Центрирование формы на странице */
        .vh-100 {
            height: 100vh;
        }

        /* Стилизация карточки с формой */
        .card {
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Стиль заголовка */
        .card h4 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #495057;
        }

        /* Формы и кнопки */
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .alert {
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
        }

        /* Плавная анимация для кнопки */
        .btn-primary {
            position: relative;
            overflow: hidden;
            border-radius: 25px;
        }

        .btn-primary:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 300%;
            height: 300%;
            background: #0056b3;
            transition: all 0.4s ease;
            border-radius: 50%;
            transform: translate(-25%, -25%) scale(1);
            opacity: 0;
        }

        .btn-primary:hover:after {
            opacity: 0.3;
            transform: translate(-50%, -50%) scale(0);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    <div class="card p-4 shadow-sm" style="width: 350px;">
        <div class="navbar"><svg width="40" height="40" viewBox="0 0 65 40" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path
                d="M0.512 36V3.36H10.448C12.88 3.36 14.8 3.712 16.208 4.416C17.648 5.12 18.672 6.112 19.28 7.392C19.92 8.672 20.24 10.176 20.24 11.904V13.248C20.24 15.776 19.152 17.712 16.976 19.056C18.512 19.664 19.68 20.544 20.48 21.696C21.28 22.848 21.68 24.224 21.68 25.824V27.168C21.68 28.928 21.36 30.48 20.72 31.824C20.08 33.136 19.024 34.16 17.552 34.896C16.08 35.632 14.112 36 11.648 36H0.512ZM10.448 8.64H6.272V16.8H10.448C11.728 16.8 12.688 16.512 13.328 15.936C14 15.328 14.336 14.512 14.336 13.488V11.904C14.336 10.88 14 10.08 13.328 9.504C12.688 8.928 11.728 8.64 10.448 8.64ZM11.648 22.08H6.272V30.72H11.648C12.992 30.72 14.016 30.4 14.72 29.76C15.424 29.12 15.776 28.256 15.776 27.168V25.584C15.776 24.496 15.424 23.648 14.72 23.04C14.016 22.4 12.992 22.08 11.648 22.08ZM28.5901 0.48H34.1101V46.224H28.5901V0.48ZM43.1214 36V3.36H53.0574C55.4894 3.36 57.4094 3.712 58.8174 4.416C60.2574 5.12 61.2814 6.112 61.8894 7.392C62.5294 8.672 62.8494 10.176 62.8494 11.904V13.248C62.8494 15.776 61.7614 17.712 59.5854 19.056C61.1214 19.664 62.2894 20.544 63.0894 21.696C63.8894 22.848 64.2894 24.224 64.2894 25.824V27.168C64.2894 28.928 63.9694 30.48 63.3294 31.824C62.6894 33.136 61.6334 34.16 60.1614 34.896C58.6894 35.632 56.7214 36 54.2574 36H43.1214ZM53.0574 8.64H48.8814V16.8H53.0574C54.3374 16.8 55.2974 16.512 55.9374 15.936C56.6094 15.328 56.9454 14.512 56.9454 13.488V11.904C56.9454 10.88 56.6094 10.08 55.9374 9.504C55.2974 8.928 54.3374 8.64 53.0574 8.64ZM54.2574 22.08H48.8814V30.72H54.2574C55.6014 30.72 56.6254 30.4 57.3294 29.76C58.0334 29.12 58.3854 28.256 58.3854 27.168V25.584C58.3854 24.496 58.0334 23.648 57.3294 23.04C56.6254 22.4 55.6014 22.08 54.2574 22.08Z"
                fill="#BC0404" />
        </svg>
        <span style="color: #b08d4f; font-size:larger; font-weight: bold;">Torap</span>
    </div>
        <h4 class="text-center mb-3">Login to the admin panel</h4>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="border-radius: 8px">Войти</button>
        </form>
    </div>
</body>
</html>
