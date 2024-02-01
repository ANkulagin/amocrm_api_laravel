<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<h4>{{ resource_path() }} | resources/views/amo/index.blade.php</h4>


<!-- Вставьте скрипт сюда -->
<script
    class="amocrm_oauth"
    charset="utf-8"
    data-client-id="{{ env('AMOCRM_CLIENT_ID') }}"
    data-title="Button"
    data-compact="false"
    data-class-name="className"
    data-color="default"
    data-state="state"
    data-error-callback="functionName"
    data-mode="popup"
    src="https://www.amocrm.ru/auth/button.min.js"
></script>

<!-- Ваш контент может находиться здесь -->

</body>
</html>
