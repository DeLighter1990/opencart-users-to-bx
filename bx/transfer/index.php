<?php

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

$site = array(
    "url"     => "https://ваш_сайт_на_opencart/index.php?route=api/getcustomers",
    "site_id" => "s1"
);

if ($_REQUEST["action"] == "import") {
    \Delight\Users::ImportUsersFromOpencart($site["url"], $site["site_id"]);
} elseif ($_REQUEST["action"] == "not_valid_emails") {
    \Delight\Users::GetUsersWithNotValidEmail($site["url"]);
} elseif ($_REQUEST["action"] == "not_valid_phones") {
    \Delight\Users::GetUsersWithNotValidPhone($site["url"]);
} elseif ($_REQUEST["action"] == "duplicate_phones") {
    \Delight\Users::GetUsersWithPhonesDuplicate($site["url"]);
} else {
    ?>
    <a href="?action=import&site=eldan">Импортировать пользователей</a><br/>
    <a href="?action=not_valid_emails&site=eldan">Список пользователей с некорректными email</a><br/>
    <a href="?action=not_valid_phones&site=eldan">Список пользователей с некорректными телефонами</a><br/>
    <a href="?action=duplicate_phones&site=eldan">Список пользователей с дублирующимися телефонами</a><br/>
    <?php
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');