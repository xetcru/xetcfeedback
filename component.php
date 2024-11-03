<?php
/**
 * Отрефакторенная версия компонента. Примененный паттерн: Template Method.
 */
// Подключение нужных модулей
if (!CModule::IncludeModule("iblock")) {
    die("Модуль Инфоблоков не подключен");
}

class FeedbackHandler {
    protected $waitTime = 1 * 60; // Интервал 1 мин.
    protected $logFilePath;
    protected $currentTime;
    protected $iblockId;

    public function __construct($iblockCode = 'feedback_errors') {
        $this->currentTime = time();
        $this->logFilePath = $_SERVER["DOCUMENT_ROOT"] . "/log/feederrormsg.txt";
        $this->iblockId = $this->getIblockId($iblockCode);
    }

    // Шаблонный метод для обработки запросов
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === "POST" && !empty($_POST["error_message"]) && !empty($_POST["error_url"])) {
            $arMailFields = $this->prepareData();

            if ($this->validateDescription($arMailFields["ERROR_DESCRIPTION"])) {
                $this->logError("слишком мало или много.");
                return;
            }

            if ($this->isFrequentRequest()) {
                $this->logError("слишком часто. жди.");
                return;
            }

            $this->writeToLog($arMailFields);
            $_SESSION['last_feedback_time'] = $this->currentTime;
            $this->sendEvent($arMailFields);
            $this->addToIblock($arMailFields);
        }
    }

    // Подготовка данных для записи и отправки
    protected function prepareData() {
        return [
            "ERROR_MESSAGE" => htmlspecialchars(trim($_POST["error_message"])),
            "ERROR_DESCRIPTION" => htmlspecialchars(trim($_POST["error_desc"] ?? "")),
            "ERROR_URL" => htmlspecialchars($_POST["error_url"]),
            "ERROR_REFERER" => htmlspecialchars($_POST["error_referer"] ?? ""),
            "ERROR_USERAGENT" => htmlspecialchars($_POST["error_useragent"] ?? "")
        ];
    }

    // Проверка частоты отправки запросов
    protected function isFrequentRequest() {
        return isset($_SESSION['last_feedback_time']) && ($this->currentTime - $_SESSION['last_feedback_time'] < $this->waitTime);
    }

    // Проверка длины описания ошибки
    protected function validateDescription($description) {
        return strlen($description) < 3 || strlen($description) > 1000;
    }

    // Запись в лог
    protected function writeToLog($arMailFields) {
        $logData = date("Y-m-d H:i:s") . ":\n";
        foreach ($arMailFields as $key => $value) {
            $logData .= $key . ": " . mb_convert_encoding($value, 'UTF-8') . "\n";
        }
        file_put_contents($this->logFilePath, "\n" . $logData, FILE_APPEND);
    }

    // Отправка почтового события
    protected function sendEvent($arMailFields) {
        CEvent::Send("BX", SITE_ID, $arMailFields);
    }

    // Добавление элемента в инфоблок
    protected function addToIblock($arMailFields) {
        if ($this->iblockId) {
            $el = new CIBlockElement;
            $arLoadProductArray = [
                "NAME" => "Сообщение об ошибке",
                "ACTIVE" => "Y",
                "IBLOCK_ID" => $this->iblockId,
                "PROPERTY_VALUES" => $arMailFields,
            ];

            if (!$el->Add($arLoadProductArray)) {
                $this->logError("Ошибка при добавлении в инфоблок: " . $el->LAST_ERROR);
            }
        }
    }

    // Получение ID инфоблока
    protected function getIblockId($iblockCode) {
        $res = CIBlock::GetList([], ['CODE' => $iblockCode]);
        if ($iblock = $res->Fetch()) {
            return $iblock['ID'];
        }
        return false;
    }

    // Логирование ошибок
    protected function logError($message) {
        $this->writeToLog(["ERROR_REFERER" => $message]);
    }
}

// Инициализация и вызов обработчика
$handler = new FeedbackHandler();
$handler->handleRequest();
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
<?php
/* добавить в футер, что бы компонент работал:
<?$APPLICATION->IncludeComponent(
    "xetcfeedback",
    "",
    Array()
);?>
*/
?>
