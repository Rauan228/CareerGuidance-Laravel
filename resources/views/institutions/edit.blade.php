<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать институт</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
            height: 100vh;
            margin: 0;
        }

        .container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            text-align: center;
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            outline: none;
            font-size: 16px;
            margin-bottom: 10px;
            transition: 0.3s;
            background-color: #dcdddf;
        }

        input:focus,
        textarea:focus {
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.1);
        }

        button {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            background: #ffc107;
            color: white;
        }

        button:hover {
            background: #e0a800;
            transform: scale(1.05);
        }

        .file-input-container {
            margin: 15px 0;
            text-align: left;
        }

        .file-input-label {
            display: inline-block;
            padding: 8px 12px;
            background: #4a90e2;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }

        .file-input-label:hover {
            background: #357abd;
        }

        .file-input {
            display: none;
        }

        .file-name {
            margin-left: 10px;
            font-size: 14px;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .current-image {
            margin: 10px 0;
        }

        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
        }

        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .cancel-button {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            background: #dc3545;
            color: white;
            text-decoration: none;
            text-align: center;
        }

        .cancel-button:hover {
            background: #c82333;
            transform: scale(1.05);
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Редактировать институт</h2>
        <form action="{{ route('institutions.update', $institution->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <label for="name">Название:</label>
            <input type="text" name="name" value="{{ old('name', $institution->name) }}" required>

            <div class="file-input-container">
                <label>Текущее фото:</label>
                @if($institution->photo_url)
                    <div class="current-image">
                        <img src="{{ $institution->photo_url }}" alt="Current photo">
                    </div>
                @else
                    <p>Фото не загружено</p>
                @endif
                
                <label class="file-input-label">
                    Изменить фото
                    <input type="file" name="photo" class="file-input" accept="image/*" onchange="previewImage(this, 'photoPreview')">
                </label>
                <span class="file-name" id="photoName"></span>
                <img id="photoPreview" class="preview-image" style="display: none;">
            </div>

            <div class="file-input-container">
                <label>Текущий логотип:</label>
                @if($institution->logo_url)
                    <div class="current-image">
                        <img src="{{ $institution->logo_url }}" alt="Current logo">
                    </div>
                @else
                    <p>Логотип не загружен</p>
                @endif
                
                <label class="file-input-label">
                    Изменить логотип
                    <input type="file" name="logo" class="file-input" accept="image/*" onchange="previewImage(this, 'logoPreview')">
                </label>
                <span class="file-name" id="logoName"></span>
                <img id="logoPreview" class="preview-image" style="display: none;">
            </div>

            <label for="description">Описание:</label>
            <textarea name="description" rows="3">{{ old('description', $institution->description1) }}</textarea>

            <label for="location">Локация:</label>
            <input type="text" name="location" value="{{ old('location', $institution->location) }}">

            <label for="website">Сайт:</label>
            <input type="url" name="website" value="{{ old('website', $institution->website) }}">

            <div class="button-container">
            <button type="submit">Обновить</button>
                <a href="{{ route('institutions.index') }}" class="cancel-button">Отмена</a>
            </div>
        </form>
    </div>

    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const fileName = input.files[0]?.name;
            const nameSpan = document.getElementById(input.name + 'Name');
            
            if (fileName) {
                nameSpan.textContent = fileName;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                nameSpan.textContent = '';
                preview.style.display = 'none';
            }
        }
    </script>
</body>

</html>
