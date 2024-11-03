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
