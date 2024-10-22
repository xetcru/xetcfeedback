<?php

use Bitrix\Main\Application;

function up()
{
    // Подключаем модуль инфоблоков
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        echo "Ошибка: модуль инфоблоков не подключен.\n";
        return;
    }
    $eventName = 'FEEDBACK_ERROR';
    $siteLid = 's1'; // Укажите явный идентификатор сайта

    // --- Создание инфоблока ---
    $iblock = new CIBlock;
    $iblockFields = [
        'ACTIVE' => 'Y',
        'NAME' => 'Сообщения об ошибках',
        'CODE' => 'feedback_errors',
        'IBLOCK_TYPE_ID' => 'references', // Укажите тип инфоблока
        'SITE_ID' => [$siteLid],
        'SORT' => 500,
        'DESCRIPTION' => 'Инфоблок для хранения сообщений об ошибках, отправленных с сайта.',
        'GROUP_ID' => ['2' => 'R'], // Права на инфоблок для группы пользователей
    ];

    $iblockID = $iblock->Add($iblockFields);

    if ($iblockID) {
        echo "Создан инфоблок для сообщений об ошибках (ID: $iblockID)\n";

        // --- Добавление полей в инфоблок ---
        $arFields = [
            ['NAME' => 'Сообщение об ошибке', 'CODE' => 'ERROR_MESSAGE', 'IS_REQUIRED' => 'Y', 'TYPE' => 'text'],
            ['NAME' => 'Описание ошибки', 'CODE' => 'ERROR_DESCRIPTION', 'IS_REQUIRED' => 'N', 'TYPE' => 'text'],
            ['NAME' => 'URL страницы', 'CODE' => 'ERROR_URL', 'IS_REQUIRED' => 'N', 'TYPE' => 'text'],
            ['NAME' => 'Referer страницы', 'CODE' => 'ERROR_REFERER', 'IS_REQUIRED' => 'N', 'TYPE' => 'text'],
            ['NAME' => 'User-Agent', 'CODE' => 'ERROR_USERAGENT', 'IS_REQUIRED' => 'N', 'TYPE' => 'text']
        ];

        foreach ($arFields as $field) {
            $iblockField = new CIBlockProperty;
            $arProperty = [
                "NAME" => $field['NAME'],
                "ACTIVE" => "Y",
                "SORT" => "500",
                "CODE" => $field['CODE'],
                "PROPERTY_TYPE" => "S", // Тип: строка
                "IBLOCK_ID" => $iblockID,
                "IS_REQUIRED" => $field['IS_REQUIRED']
            ];
            if (!$iblockField->Add($arProperty)) {
                echo "Ошибка при добавлении поля {$field['NAME']}: " . $iblockField->LAST_ERROR . "\n";
            } else {
                echo "Поле {$field['NAME']} добавлено в инфоблок.\n";
            }
        }

    } else {
        echo "Ошибка при создании инфоблока: " . $iblock->LAST_ERROR . "\n";
    }

    // Проверяем наличие события
    $eventType = CEventType::GetList([
        'TYPE_ID' => $eventName
    ])->Fetch();

    if (!$eventType) {
        // Создаем почтовое событие
        $et = new CEventType;
        $result = $et->Add([
            'LID' => 'ru',
            'EVENT_NAME' => $eventName,
            'NAME' => 'Сообщение об ошибке',
            'DESCRIPTION' => "#ERROR_MESSAGE# - Сообщение об ошибке\n#ERROR_DESCRIPTION# - Описание ошибки\n#ERROR_URL# - URL страницы\n#ERROR_REFERER# - Страница откуда пришел запрос\n#ERROR_USERAGENT# - User-Agent браузера\n",
        ]);

        if ($result) {
            echo "Добавлено почтовое событие $eventName\n";
        } else {
            echo "Ошибка при создании почтового события $eventName\n";
        }
    } else {
        echo "Почтовое событие $eventName уже существует\n";
    }

    // Проверяем наличие почтового шаблона
    $template = CEventMessage::GetList(
        ($by = "site_id"),
        ($order = "desc"),
        ['EVENT_NAME' => $eventName]
    )->Fetch();

    if (!$template) {
        // Создаем почтовый шаблон
        $em = new CEventMessage;
        $result = $em->Add([
            'ACTIVE' => 'Y',
            'EVENT_NAME' => $eventName,
            'LID' => $siteLid, // Укажите явный LID (например, 's1')
            'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
            'EMAIL_TO' => '#DEFAULT_EMAIL_TO#',
            'SUBJECT' => 'Сообщение об ошибке на сайте',
            'BODY_TYPE' => 'text',
            'MESSAGE' => "Сообщение об ошибке: #ERROR_MESSAGE#\nОписание ошибки: #ERROR_DESCRIPTION#\nURL: #ERROR_URL#\nReferer: #ERROR_REFERER#\nUser-Agent: #ERROR_USERAGENT#",
        ]);

        if ($result) {
            echo "Добавлен почтовый шаблон для события $eventName\n";
        } else {
            echo "Ошибка при создании почтового шаблона для события $eventName\n";
        }
    } else {
        echo "Почтовый шаблон для события $eventName уже существует\n";
    }
}

function down()
{
    // Подключаем модуль инфоблоков
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        echo "Ошибка: модуль инфоблоков не подключен.\n";
        return;
    }

    $eventName = 'FEEDBACK_ERROR';

    // --- Удаление инфоблока ---
    $iblockCode = 'feedback_errors';
    $iblock = CIBlock::GetList([], ['CODE' => $iblockCode])->Fetch();

    if ($iblock) {
        CIBlock::Delete($iblock['ID']);
        echo "Удален инфоблок для сообщений об ошибках\n";
    } else {
        echo "Инфоблок для сообщений об ошибках не найден\n";
    }

    // Удаляем почтовое событие по его имени
    $et = new CEventType;
    $result = $et->Delete($eventName);

    if ($result) {
        echo "Удалено почтовое событие $eventName\n";
    } else {
        echo "Ошибка при удалении почтового события $eventName\n";
    }

    // Удаляем почтовый шаблон
    $template = CEventMessage::GetList(
        ($by = "site_id"),
        ($order = "desc"),
        ['EVENT_NAME' => $eventName]
    )->Fetch();

    if ($template) {
        $result = CEventMessage::Delete($template['ID']);
        if ($result) {
            echo "Удален почтовый шаблон для события $eventName\n";
        } else {
            echo "Ошибка при удалении почтового шаблона для события $eventName\n";
        }
    } else {
        echo "Почтовый шаблон для события $eventName не найден\n";
    }
}

/*
include($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/migrations/feedback_migration.php");
up(); // для применения миграции

include($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/migrations/feedback_migration.php");
down(); // для отката миграции
*/
