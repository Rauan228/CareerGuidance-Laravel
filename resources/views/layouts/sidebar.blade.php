<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }

        .sidebar button {
            background: #34495e;
            color: white;
            font-size: 16px;
            border: none;
            padding: 12px 15px;
            margin: 5px 0;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .sidebar button:hover {
            background: #2980b9;
            transform: translateX(5px);
        }

        .sidebar button.active {
            background: #2980b9;
            font-weight: bold;
            border-left: 4px solid #bc0404;
        }

        .sidebar button svg {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .sidebar a {
            text-decoration: none;
            display: block;
        }

        .sidebar button,
        .dropdown .btn-primary {
            margin: 8px 0;
        }

        .dropdown {
            position: relative;
            width: 100%;
        }

        .dropdown .btn-primary {
            background: #34495e;
            color: white;
            font-size: 16px;
            border: none;
            padding: 12px 15px;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .dropdown .btn-primary:hover {
            background: #2980b9;
            transform: translateX(5px);
        }

        .dropdown .arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
        }

        .dropdown.show .arrow {
            transform: rotate(90deg);
        }

        .dropdown-menu {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 8px 0;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            transform: translateY(-10px);
            position: absolute;
            z-index: 1000;
            display: none;
            top: 100%;
            left: 0;
        }

        .dropdown.show .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            display: block;
        }

        .dropdown-menu li {
            list-style: none;
            margin: 0;
        }

        .dropdown-menu .dropdown-item {
            color: #333;
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .dropdown-menu .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #2980b9;
            transform: translateX(5px);
        }

        .dropdown-menu .dropdown-item.active {
            background-color: #2980b9;
            color: white;
        }

        .dropdown-menu .dropdown-item svg {
            width: 16px;
            height: 16px;
            margin-right: 10px;
        }

        .sidebar .logout-button {
            background: #bc0404;
            color: white;
            font-weight: bold;
            border-radius: 6px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .sidebar .logout-button:hover {
            background: #a00000;
            transform: translateX(5px);
        }

        .navbar {
            display: flex;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
        }

        .navbar img {
            width: 35px;
            height: 35px;
            object-fit: contain;
        }

        .navbar span {
            font-size: 20px;
            font-weight: bold;
            color: #bc0404;
            margin-left: 12px;
        }

        .btn-icon {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-icon svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .nested-dropdown {
            position: relative;
        }

        .nested-dropdown > .dropdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nested-dropdown > .dropdown-menu {
            position: absolute;
            top: 0;
            left: 100%;
            margin-left: 1px;
            display: none;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .nested-dropdown.show > .dropdown-menu {
            display: block;
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .nested-dropdown > .dropdown-item .arrow {
            margin-left: auto;
        }

        /* Explicitly disable hover effects */
        .nested-dropdown:hover > .dropdown-menu,
        .dropdown:hover > .dropdown-menu,
        .dropdown-menu:hover {
            display: none;
            opacity: 0;
            visibility: hidden;
        }

        /* Only show dropdowns when .show class is present */
        .dropdown.show > .dropdown-menu,
        .nested-dropdown.show > .dropdown-menu {
            display: block;
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="navbar">
            <img src="{{ asset('B_B.png') }}" alt="logo">
            <span>Torap</span>
        </div>

        <a href="{{ route('home') }}">
            <button class="btn-icon {{ request()->routeIs('home') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5ZM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5 5 5Z"/>
                </svg>
                Главная
            </button>
        </a>

        <!-- Applications Dropdown -->
        <div class="dropdown">
            <button class="btn-icon btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 1.99-2.181h4.017a2 2 0 0 1 1.936 1.488 2 2 0 0 1 1.688-1.488zm.471 5.746a.5.5 0 0 0-.708.708L10.293 10H7.5a.5.5 0 0 0 0 1h2.793l-.647.646a.5.5 0 0 0 .708.708l1.5-1.5a.5.5 0 0 0 0-.708l-1.5-1.5zM2.19 4l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 14.806 4H3.194a1 1 0 0 0-.995.91z"/>
                </svg>
                Заявки
                <span class="arrow">›</span>
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item {{ request()->routeIs('applications.user_applications.*') ? 'active' : '' }}" 
                       href="{{ route('applications.user_applications.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                        </svg>
                        Заявки пользователей
                    </a>
                </li>
                <li>
                    <a class="dropdown-item {{ request()->routeIs('applications.institution_applications.*') ? 'active' : '' }}" 
                       href="{{ route('applications.institution_applications.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022Z"/>
                        </svg>
                        Заявки учреждений
                    </a>
                </li>
            </ul>
        </div>

        <a href="{{ route('users.index') }}">
            <button class="btn-icon {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                </svg>
                Пользователи
            </button>
        </a>

        <a href="{{ route('institutions.index') }}">
            <button class="btn-icon {{ request()->routeIs('institutions.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M14.763.075A.5.5 0 0 1 15 .5v15a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5V14h-1v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .342-.474L6 7.64V4.5a.5.5 0 0 1 .276-.447l8-4a.5.5 0 0 1 .487.022ZM6 8.694 1 10.36V15h5V8.694ZM7 15h2v-1.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 .5.5V15h2V1.309l-7 3.5V15Z"/>
                </svg>
                Учреждения
            </button>
        </a>

        <a href="{{ route('events.index') }}">
            <button class="btn-icon {{ request()->routeIs('events.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M4 .5a.5.5 0 0 0-1 0V1H2a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-1V.5a.5.5 0 0 0-1 0V1H4V.5zM2.545 3h10.91c.3 0 .545.224.545.5v1c0 .276-.244.5-.546.5H2.545C2.245 5 2 4.776 2 4.5v-1c0-.276.244-.5.545-.5z"/>
                </svg>
                Календарь событий
            </button>
        </a>

        <a href="{{ route('reviews.index') }}">
            <button class="btn-icon {{ request()->routeIs('reviews.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M2 15.5V2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.74.439L8 13.069l-5.26 2.87A.5.5 0 0 1 2 15.5zm8.854-9.646a.5.5 0 0 0-.708-.708L7.5 7.793 6.354 6.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3z"/>
                </svg>
                Отзывы
            </button>
        </a>

        <a href="{{ route('likes.index') }}">
            <button class="btn-icon {{ request()->routeIs('likes.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M8.864.046C7.908-.193 7.02.53 6.956 1.466c-.072 1.051-.23 2.016-.428 2.59-.125.36-.479 1.013-1.04 1.639-.557.623-1.282 1.178-2.131 1.41C2.685 7.288 2 7.87 2 8.72v4.001c0 .845.682 1.464 1.448 1.545 1.07.114 1.564.415 2.068.723l.048.03c.272.165.578.348.97.484.397.136.861.217 1.466.217h3.5c.937 0 1.599-.477 1.934-1.064a1.86 1.86 0 0 0 .254-.912c0-.152-.023-.312-.077-.464.201-.263.38-.578.488-.901.11-.33.172-.762.004-1.149.069-.13.12-.269.159-.403.077-.27.113-.568.113-.857 0-.288-.036-.585-.113-.856a2.144 2.144 0 0 0-.138-.362 1.9 1.9 0 0 0 .234-1.734c-.206-.592-.682-1.1-1.2-1.272-.847-.282-1.803-.276-2.516-.211a9.84 9.84 0 0 0-.443.05 9.365 9.365 0 0 0-.062-4.509A1.38 1.38 0 0 0 9.125.111L8.864.046zM11.5 14.721H8c-.51 0-.863-.069-1.14-.164-.281-.097-.506-.228-.776-.393l-.04-.024c-.555-.339-1.198-.731-2.49-.868-.333-.036-.554-.29-.554-.55V8.72c0-.254.226-.543.62-.65 1.095-.3 1.977-.996 2.614-1.708.635-.71 1.064-1.475 1.238-1.978.243-.7.407-1.768.482-2.85.025-.362.36-.594.667-.518l.262.066c.16.04.258.143.288.255a8.34 8.34 0 0 1-.145 4.725.5.5 0 0 0 .595.644l.003-.001.014-.003.058-.014a8.908 8.908 0 0 1 1.036-.157c.663-.06 1.457-.054 2.11.164.175.058.45.3.57.65.107.308.087.67-.266 1.022l-.353.353.353.354c.043.043.105.141.154.315.048.167.075.37.075.581 0 .212-.027.414-.075.582-.05.174-.111.272-.154.315l-.353.353.353.354c.047.047.109.177.005.488a2.224 2.224 0 0 1-.505.805l-.353.353.353.354c.006.005.041.05.041.17a.866.866 0 0 1-.121.416c-.165.288-.503.56-1.066.56z"/>
                </svg>
                Лайки
            </button>
        </a>

        <!-- Education Dropdown -->
        <div class="dropdown">
            <button class="btn-icon btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5Z"/>
                    <path fill="currentColor" d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466 4.176 9.032Z"/>
                </svg>
                Образование
                <span class="arrow">›</span>
            </button>
            <ul class="dropdown-menu">
                <!-- Universities Section -->
                <li class="nested-dropdown">
                    <a class="dropdown-item" data-type="universities">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5Z"/>
                        </svg>
                        Университеты
                    </a>
                </li>
                <!-- Colleges Section -->
                <li class="nested-dropdown">
                    <a class="dropdown-item" data-type="colleges">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M2 1a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5v1H12v12H4V2h2.5V1H2z"/>
                        </svg>
                        Колледжи
                    </a>
                </li>
            </ul>
            <!-- Global Education Lists -->
            <ul class="dropdown-menu global-lists" style="display: none;">
                <li>
                    <a class="dropdown-item" id="global-specialties">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
                        </svg>
                        Направления (глоб.)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" id="global-qualifications">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5c0 .538-.012 1.05-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33.076 33.076 0 0 1 2.5.5zm.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935zm10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935z"/>
                        </svg>
                        Квалификации
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" id="global-specializations">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                            <path fill="currentColor" d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5Z"/>
                        </svg>
                        Специализации
                    </a>
                </li>
            </ul>
        </div>

        <a href="{{ route('notifications.index') }}">
            <button class="btn-icon {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/>
                </svg>
                Уведомления
            </button>
        </a>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="logout-button btn-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path fill="currentColor" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                    <path fill="currentColor" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                </svg>
                Выход
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown');
            const nestedDropdowns = document.querySelectorAll('.nested-dropdown');
            
            // Find Education dropdown by checking button text content
            const educationDropdown = Array.from(dropdowns).find(dropdown => {
                const button = dropdown.querySelector('.btn-primary');
                return button && button.textContent.trim().includes('Образование');
            });
            
            const mainMenu = educationDropdown ? educationDropdown.querySelector('.dropdown-menu:not(.global-lists)') : null;
            const globalLists = educationDropdown ? educationDropdown.querySelector('.dropdown-menu.global-lists') : null;

            // Toggle main dropdown
            dropdowns.forEach(dropdown => {
                const button = dropdown.querySelector('.btn-primary');
                
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close all other dropdowns first
                    dropdowns.forEach(d => {
                        if (d !== dropdown) {
                            d.classList.remove('show');
                        }
                    });
                    
                    // Close all nested dropdowns
                    nestedDropdowns.forEach(nested => {
                        nested.classList.remove('show');
                    });
                    
                    // Hide global lists
                    if (globalLists) {
                        globalLists.style.display = 'none';
                    }
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('show');
                });
            });

            // Toggle nested dropdowns and handle global lists
            nestedDropdowns.forEach(nested => {
                const item = nested.querySelector('.dropdown-item');
                
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close other nested dropdowns
                    nestedDropdowns.forEach(other => {
                        if (other !== nested) {
                            other.classList.remove('show');
                        }
                    });
                    
                    const type = item.getAttribute('data-type');
                    const isOpen = nested.classList.toggle('show');
                    
                    if (globalLists) {
                        if (isOpen && type) {
                            // Show global lists and update links based on type
                            globalLists.style.display = 'block';
                            const specialtiesLink = globalLists.querySelector('#global-specialties');
                            const qualificationsLink = globalLists.querySelector('#global-qualifications');
                            const specializationsLink = globalLists.querySelector('#global-specializations');
                            
                            if (type === 'universities') {
                                specialtiesLink.setAttribute('href', '{{ route("specialties.index") }}?type=university');
                                qualificationsLink.setAttribute('href', '{{ route("qualifications.index") }}?type=university');
                                specializationsLink.setAttribute('href', '{{ route("specializations.index") }}?type=university');
                            } else if (type === 'colleges') {
                                specialtiesLink.setAttribute('href', '{{ route("specialties.index") }}?type=college');
                                qualificationsLink.setAttribute('href', '{{ route("qualifications.index") }}?type=college');
                                specializationsLink.setAttribute('href', '{{ route("specializations.index") }}?type=college');
                            }
                        } else {
                            globalLists.style.display = 'none';
                        }
                    }
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', (e) => {
                const isDropdownClick = e.target.closest('.dropdown');
                const isNestedDropdownClick = e.target.closest('.nested-dropdown');
                
                if (!isDropdownClick && !isNestedDropdownClick) {
                    dropdowns.forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                    nestedDropdowns.forEach(nested => {
                        nested.classList.remove('show');
                    });
                    if (globalLists) {
                        globalLists.style.display = 'none';
                    }
                }
            });
        });
    </script>
</body>
</html>