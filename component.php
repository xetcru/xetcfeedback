<?
$waitTime = 1 * 60; // интервал 1 мин.
$currentTime = time(); // Получаем текущее время
// Путь к лог-файлу
$logFilePath = $_SERVER["DOCUMENT_ROOT"] . "/log/feederrormsg.txt";

// Функция для записи в лог-файл
function writeToLog($logFilePath, $arMailFields) {
    // Создаем строку с данными для лог-файла
    $logData = ":\n" . "ERROR_MESSAGE: " . (!empty($arMailFields["ERROR_MESSAGE"]) ? mb_convert_encoding($arMailFields["ERROR_MESSAGE"], 'UTF-8') : "") . "\n";
    $logData .= "ERROR_DESCRIPTION: " . (!empty($arMailFields["ERROR_DESCRIPTION"]) ? mb_convert_encoding($arMailFields["ERROR_DESCRIPTION"], 'UTF-8') : "") . "\n";
    $logData .= "ERROR_URL: " . (!empty($arMailFields["ERROR_URL"]) ? mb_convert_encoding($arMailFields["ERROR_URL"], 'UTF-8') : "") . "\n";
    $logData .= "ERROR_REFERER: " . (!empty($arMailFields["ERROR_REFERER"]) ? $arMailFields["ERROR_REFERER"] : "") . "\n";
    $logData .= "ERROR_USERAGENT: " . (!empty($arMailFields["ERROR_USERAGENT"]) ? mb_convert_encoding($arMailFields["ERROR_USERAGENT"], 'UTF-8') : "") . "\n";

    // Запись данных в лог-файл
    file_put_contents($logFilePath, "\n".date("Y-m-d H:i:s") . $logData, FILE_APPEND);
}

// Обработка POST-запроса
// Проверяем, если у пользователя есть информация о времени последней отправки
if ($_SERVER['REQUEST_METHOD'] == "POST" && !empty($_POST["error_message"]) && !empty($_POST["error_url"])) {
    $errorDesc = (!empty($_POST["error_desc"]) ? trim($_POST["error_desc"]) : "");
    if (strlen($errorDesc) < 3 || strlen($errorDesc) > 1000) {
        // Если текст не соответствует условиям, выводим сообщение об ошибке
        $arMailFields["ERROR_REFERER"] = "слишком мало или много.";
        writeToLog($logFilePath, $arMailFields);
        //exit; // Останавливаем выполнение скрипта
    } else {
        if (isset($_SESSION['last_feedback_time'])) {
            // Получаем время последней отправки пользователя
            $lastSubmissionTime = $_SESSION['last_feedback_time'];
            if ($currentTime - $lastSubmissionTime < $waitTime) { // если слишком часто
                $arMailFields = Array();
                $arMailFields["ERROR_REFERER"] = "слишком часто. жди.";
                writeToLog($logFilePath, $arMailFields);
            } else {
                // Прошло достаточно времени, можно отправить сообщение
                $arMailFields = Array();
                $arMailFields["ERROR_MESSAGE"] = (!empty($_POST["error_message"]) ? htmlspecialchars(trim($_POST["error_message"])) : "");
                $arMailFields["ERROR_DESCRIPTION"] = (!empty($_POST["error_desc"]) ? htmlspecialchars(trim($_POST["error_desc"])) : "");
                $arMailFields["ERROR_URL"] = (!empty($_POST["error_url"]) ? htmlspecialchars($_POST["error_url"]) : "");
                $arMailFields["ERROR_REFERER"] = (!empty($_POST["error_referer"]) ? htmlspecialchars($_POST["error_referer"]) : "");
                $arMailFields["ERROR_USERAGENT"] = (!empty($_POST["error_useragent"]) ? htmlspecialchars($_POST["error_useragent"]) : "");
                
                writeToLog($logFilePath, $arMailFields);
                $_SESSION['last_feedback_time'] = $currentTime; // Обновляем время последней отправки

                // Отправка почтового события
                CEvent::Send("BX", SITE_ID, $arMailFields);
            }
        } else {
            $arMailFields = Array();
            $arMailFields["ERROR_MESSAGE"] = (!empty($_POST["error_message"]) ? htmlspecialchars(trim($_POST["error_message"])) : "");
            $arMailFields["ERROR_DESCRIPTION"] = (!empty($_POST["error_desc"]) ? htmlspecialchars(trim($_POST["error_desc"])) : "");
            $arMailFields["ERROR_URL"] = (!empty($_POST["error_url"]) ? htmlspecialchars($_POST["error_url"]) : "");
            $arMailFields["ERROR_REFERER"] = (!empty($_POST["error_referer"]) ? htmlspecialchars($_POST["error_referer"]) : "");
            $arMailFields["ERROR_USERAGENT"] = (!empty($_POST["error_useragent"]) ? htmlspecialchars($_POST["error_useragent"]) : "");
            
            writeToLog($logFilePath, $arMailFields);
            $_SESSION['last_feedback_time'] = $currentTime; // Записываем текущее время

            // Отправка почтового события
            CEvent::Send("BX", SITE_ID, $arMailFields);
        }
    }
}
?>
<script>
BX.bind(document, "keypress", SendError);

var isSelectingText = false; // Флаг, указывающий, что пользователь выделил текст

document.onmouseup = function() {
    if (isSelectingText) {
        // Проверяем, есть ли выделенный текст
        var selectedText = getSelectedText();
        if (selectedText) {
            // Показываем предупреждение
            var warningElement = document.createElement("div");
            warningElement.className = 'err-wrn-alert';
            warningElement.innerHTML = "Нашли ошибку? Нажмите Ctrl+Enter";
            warningElement.style.position = "fixed";
            warningElement.style.display = "block";
            document.body.appendChild(warningElement);

            // Устанавливаем таймер на удаление предупреждения через 3 сек
            setTimeout(function() {
                document.body.removeChild(warningElement);
            }, 3000);
        }
    }
    isSelectingText = false;
};

document.onselectionchange = function() {
    isSelectingText = (getSelectedText() !== "");
};

var isModalOpen = false; // Флаг, указывающий, открыто ли модальное окно

function SendError(event, formElem)
{
    event = event || window.event;

    if((event.ctrlKey) && ((event.keyCode == 0xA)||(event.keyCode == 0xD)) && !isModalOpen)
    {
        isModalOpen = true; // Устанавливаем флаг, что окно открыто
        // создаем modal overlay
        var modalOverlay = document.createElement('div');
        modalOverlay.className = 'err-modal-overlay';
        modalOverlay.style.position = 'fixed';
        modalOverlay.style.display = 'block';
        modalOverlay.addEventListener('click', closeModal);

        // создаем a modal dialog
        var modalDialog = document.createElement('div');
        modalDialog.className = 'err-modal-dialog';
        modalDialog.style.position = 'fixed';
        modalDialog.style.display = 'block';
        modalDialog.innerHTML = '<h3>Сообщите об ошибке</h3>';
        modalDialog.innerHTML += '<span>В чем заключается ошибка?</span>';

        // создаем close button
        var closeButton = document.createElement('a');
        closeButton.className = 'err-close-dialog';
        closeButton.addEventListener('click', closeModal);

        // создаем form
        var form = document.createElement('form');
        form.method = 'POST';
        form.id = 'help_form';

        // добавляем form elements
        form.innerHTML = `
            <textarea name="error_desc" style="" rows="6" oninput="updateCharCount(this);"></textarea>
            <span id="charCount">0 / 1000</span>
            <input type="hidden" name="error_message" value="${getSelectedText()}">
            <input type="hidden" name="error_url" value="${window.location}">
            <input type="hidden" name="error_referer" value="${document.referrer}">
            <input type="hidden" name="error_useragent" value="${navigator.userAgent}">
        `;

        var textarea = form.querySelector('textarea'); // Получаем ссылку на текстовое поле
        // слушаем ввод в текстовом поле и обновляем счетчик символов
        textarea.addEventListener('input', function () {
            updateCharCount(this);
        });

        // создаем action buttons
        var closeButtonActionDiv = document.createElement('div');
        closeButtonActionDiv.className = 'err-modal-action';

        // создаем Send button
        var sendButton = document.createElement('button');
        sendButton.className = 'err-modal-action';
        sendButton.type = 'submit';
        //sendButton.style.color = '#fff';
        //sendButton.style.background = '#CC2035';
        sendButton.textContent = 'Отправить';
        sendButton.disabled = true; // Сначала делаем кнопку неактивной

        // Обработка отправки формы
        sendButton.addEventListener('click', function() {
            event.preventDefault(); // Предотвращаем отправку формы по умолчанию
            // Получаем данные из формы
            var formData = new FormData(form);
            // Создаем объект XMLHttpRequest для отправки POST-запроса
            var xhr = new XMLHttpRequest();
            // Укажите путь к обработчику на сервере
            var endpoint = '';
            // Настраиваем POST-запрос
            xhr.open('POST', endpoint, true);
            // Установливаем обработчик события для успешного завершения запроса
            xhr.onload = function () {
                console.log(formData);
                console.log('Статус код: ' + xhr.status);
                if (xhr.status >= 200 && xhr.status < 300) {
                    closeModal();
                } else {
                    // Вывод информации об ошибке
                    console.error('Ошибка при отправке данных на сервер. Статус код: ' + xhr.status);
                }
            };
            // Отправляем данные на сервер
            xhr.send(formData);
            closeModal();
        });

        // слушаем ввод в текстовом поле и обновляем счетчик символов
        function updateCharCount(textarea) {
            var charCount = textarea.value.length;
            document.getElementById("charCount").textContent = charCount + " / 1000";
            // Проверяем длину и активируем/деактивируем кнопку отправки
            sendButton.disabled = charCount < 3 || charCount > 1000;
        }

		// создаем close button
		var closeButtonAction = document.createElement('button');
            closeButtonAction.className = 'err-modal-action';
            closeButtonAction.textContent = 'Отмена';
            closeButtonAction.addEventListener('click', closeModal);

        // добавляем элементы в the modal dialog
        modalDialog.appendChild(closeButton);
        modalDialog.appendChild(form);
        modalDialog.appendChild(closeButtonActionDiv);
        closeButtonActionDiv.appendChild(sendButton);
        closeButtonActionDiv.appendChild(closeButtonAction);

        // добавляем modal overlay и dialog в документ
        document.body.appendChild(modalOverlay);
        document.body.appendChild(modalDialog);

        // слушаем ESC для закрытия модалки
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }

    function closeModal() {
        isModalOpen = false; // Устанавливаем флаг, что окно закрыто
        var modalOverlay = document.querySelector('.err-modal-overlay');
        var modalDialog = document.querySelector('.err-modal-dialog');
        modalOverlay.remove();
        modalDialog.remove();
    }
}

function getSelectedText() {
    if (window.getSelection) {
        return window.getSelection().toString();
    } else if (document.getSelection) {
        return document.getSelection().toString();
    } else if (document.selection) {
        return document.selection.createRange().text;
    }
    return '';
}
</script>
<?php
$this->IncludeComponentTemplate();
?>
<?
// не забываем добавить в футер, что бы компонент работал:
/*<?$APPLICATION->IncludeComponent(
    "xetcfeedback",
    "",
    Array()
);?>
//*/
?>
