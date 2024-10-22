# xetcfeedback
Feedback form component for CMS Bitrix.

-----RUS-----
Компонент для CMS Битрикс, добавляющий форму отзыва, вызваемую сочитанием клавиш CTRL+ENTER.
Форма отправляет сообщение на почту (указанную в почтовом шаблоне) и дублирует запись в инфоблок Контент -> Справочники -> Сообщения об ошибках.

Процесс установки:
1. Залить в директорию /components/ директорию xetcfeedback
2. Из директории migrations компонента файл миграции feedback_migration.php разместить в /local/php_interface/migrations
3. Выполнить миграцию:
   include($_SERVER["DOCUMENT_ROOT"] . "/local/php_interface/migrations/feedback_migration.php");
   up();
4. Разместить код компонента в шаблоне, например в footer.php:
   <?$APPLICATION->IncludeComponent(
			    "xetcfeedback",
			    "",
			    Array()
			);?>  
