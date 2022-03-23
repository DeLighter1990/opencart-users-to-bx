<?php

namespace Delight;

class Users
{

    /** ID товара для импорта */
    const IMPORT_PRODUCT_ID = 2685;

    /**
     * Получает массив с данными пользователей при импорте из Opencart
     *
     * @param string $url
     *
     * @return array
     */
    public static function GetUsersData(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $usersDataStr = curl_exec($ch);
        curl_close($ch);

        return json_decode($usersDataStr, true);
    }

    /**
     * Импортирует пользователей из Opencart
     *
     * @param string $url
     * Ссылка на страницу с JSON-данными пользователей
     *
     * @param string $siteId
     * ID сайта для привязки пользователей
     *
     * @return void
     */
    public static function ImportUsersFromOpencart(string $url, string $siteId)
    {
        $obUser    = new \CUser;
        $usersData = self::GetUsersData($url);

        foreach ($usersData as $user) {
            $formattedPhone = self::FormatPhone($user["telephone"]);
            // Добавляем только пользователей с корректным номером телефона
            $phoneNumber = \Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($formattedPhone);
            if (($phoneNumber->isValid()) AND (check_email($user["email"], true))) {
                $arFields = array(
                    "NAME"                      => trim($user["firstname"]),
                    "LAST_NAME"                 => trim($user["lastname"]),
                    "PERSONAL_PHONE"            => $formattedPhone,
                    "PERSONAL_STATE"            => trim($user["region"]),
                    "PERSONAL_CITY"             => trim($user["city"]),
                    "PERSONAL_ZIP"              => trim($user["postcode"]),
                    "PERSONAL_STREET"           => trim($user["address_1"]),
                    "UF_OPENCART_PASSWORD_HASH" => $user["password"] . ":" . $user["salt"],
                );
                $userRes  = \Bitrix\Main\UserTable::getList(array(
                    'select' => array("*", "UF_OPENCART_PASSWORD_HASH"),
                    'filter' => array('=EMAIL' => $user["email"]),
                    'limit'  => 1
                ));
                if ($bxUser = $userRes->fetch()) {
                    $arUpdateFields = $arFields;
                    if ($arFields["PERSONAL_PHONE"] != $bxUser["PERSONAL_PHONE"]) {
                        $arUpdateFields["PHONE_NUMBER"] = $arFields["PERSONAL_PHONE"];
                    }
                    if ($arUpdateFields["UF_OPENCART_PASSWORD_HASH"] != $bxUser["UF_OPENCART_PASSWORD_HASH"]) {
                        $arUpdateFields["PASSWORD"]         = $user["password"];
                        $arUpdateFields["CONFIRM_PASSWORD"] = $user["password"];
                    }
                    foreach ($arUpdateFields as $key => $arField) {
                        if ($bxUser[$key] == $arField) {
                            unset($arUpdateFields[$key]);
                        }
                    }
                    if ( ! empty($arUpdateFields)) {
                        $obUser->Update($bxUser["ID"], $arUpdateFields);
                    }
                    if ( ! empty($user["orders_total"])) {
                        $orderRes = \Bitrix\Sale\Order::getList(array(
                            "filter" => array("USER_ID" => $bxUser["ID"]),
                            "order"  => array('ID' => 'ASC'),
                            "limit"  => 1
                        ));
                        if ($arOrder = $orderRes->fetch()) {
                            if ((float)$arOrder["PRICE"] != (float)$user["orders_total"]) {
                                $order             = \Bitrix\Sale\Order::load($arOrder["ID"]);
                                $paymentCollection = $order->getPaymentCollection();
                                $paymentCollection[0]->setPaid("N");
                                $paymentCollection[0]->setField("SUM", $user["orders_total"]);
                                $basket = $order->getBasket();
                                $item   = $basket->getExistsItem('catalog', self::IMPORT_PRODUCT_ID);
                                $item->setField("PRICE", $user["orders_total"]);
                                $basket->save();
                                $paymentCollection[0]->setPaid("Y");
                                $order->doFinalAction(true);
                                $order->save();
                            }
                        } else {
                            self::CreateOrderWithSum($bxUser["ID"], $user["orders_total"], $siteId);
                        }
                    }
                } else {
                    $userGroups = array(Config::DEFAULT_USER_GROUP_ID, Config::TRANSFERED_USER_GROUP_ID);
                    if ($user["customer_group_id"] == 4) {
                        $userGroups[] = Config::PROFESSIONAL_USER_GROUP_ID;
                    }
                    $arFields["LANGUAGE_ID"]      = "ru";
                    $arFields["ACTIVE"]           = "Y";
                    $arFields["PERSONAL_COUNTRY"] = 1;              // 1 = Россия
                    $arFields["LID"]              = $siteId;
                    $arFields["EMAIL"]            = trim($user["email"]);
                    $arFields["LOGIN"]            = trim($user["email"]);
                    $arFields["GROUP_ID"]         = $userGroups;
                    $arFields["PASSWORD"]         = $user["password"];
                    $arFields["CONFIRM_PASSWORD"] = $user["password"];
                    $arFields["PHONE_NUMBER"]     = $formattedPhone;

                    $userId = $obUser->add($arFields);
                    if ($userId === false) {
                        echo $user["firstname"] . " " . $user["lastname"] . " - ошибка: " . $obUser->LAST_ERROR;
                        exit;
                    }
                    if (($userId) and ( ! empty($user["orders_total"]))) {
                        self::CreateOrderWithSum($userId, $user["orders_total"], $siteId);
                    }
                }
            }
        }
        echo "Импорт завершён";
    }

    /**
     * Приводит номер телефона к формату +79000000000
     *
     * @param string $phone
     *
     * @return string
     */
    public static function FormatPhone(string $phone): string
    {
        $resultPhone = preg_replace('/[^0-9]/', '', $phone);
        $firstNum    = substr($resultPhone, 0, 1);
        if ($firstNum == 8) {
            $resultPhone = "+7" . substr($resultPhone, 1);
        } elseif ($firstNum == 7) {
            $resultPhone = "+" . $resultPhone;
        } elseif ($firstNum == 9) {
            $resultPhone = "+7" . $resultPhone;
        }

        return $resultPhone;
    }

    /**
     * Выводит список пользователей с некорректными телефонами
     *
     * @param string $url
     *
     * @return void
     */
    public static function GetUsersWithNotValidPhone(string $url)
    {
        $usersData = self::GetUsersData($url);

        $counter = 1;
        foreach ($usersData as $user) {
            $formattedPhone = self::FormatPhone($user["telephone"]);
            $phoneNumber    = \Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($formattedPhone);
            if ( ! $phoneNumber->isValid()) {
                echo $counter . ") ID: " . $user["customer_id"] . " " . $user["firstname"] . " " . $user["lastname"] . " " . $user["telephone"] . "<br/>";
                $counter++;
            }
        }
    }

    /**
     * Выводит список пользователей с некорректными email
     *
     * @param string $url
     *
     * @return void
     */
    public static function GetUsersWithNotValidEmail(string $url)
    {
        $usersData = self::GetUsersData($url);

        $counter = 1;
        foreach ($usersData as $user) {
            if ( ! check_email($user["email"], true)) {
                echo $counter . ") ID: " . $user["customer_id"] . " " . $user["firstname"] . " " . $user["lastname"] . " " . $user["email"] . "<br/>";
                $counter++;
            }
        }
    }

    /**
     * Выводит список пользователей с одинаковыми телефонами
     *
     * @param string $url
     *
     * @return void
     */
    public static function GetUsersWithPhonesDuplicate(string $url)
    {
        $usersData = self::GetUsersData($url);

        $counter = 1;
        foreach ($usersData as $key => $user) {
            $formattedPhone = self::FormatPhone($user["telephone"]);
            $phoneNumber    = \Bitrix\Main\PhoneNumber\Parser::getInstance()->parse($formattedPhone);
            if ($phoneNumber->isValid()) {
                $duplicates = "";
                foreach ($usersData as $keyDup => $userDup) {
                    $formattedPhoneDup = self::FormatPhone($userDup["telephone"]);
                    if (($formattedPhone == $formattedPhoneDup) and ($key != $keyDup)) {
                        unset($usersData[$keyDup]);
                        $duplicates .= "ID: " . $userDup["customer_id"] . " " . $userDup["firstname"] . " " . $userDup["lastname"] . " " . $userDup["telephone"] . "<br/>";
                    }
                }
                if ( ! empty($duplicates)) {
                    $duplicates = $counter . "<br/>ID: " . $user["customer_id"] . " " . $user["firstname"] . " " . $user["lastname"] . " " . $user["telephone"] . "<br/>" . $duplicates;
                    echo $duplicates;
                    $counter++;
                }
            }
            unset($usersData[$key]);
        }
    }

    /**
     * Создаёт заказ с пустым товаром на определенную сумму
     *
     * @param int   $userId
     * @param float $sumPrice
     *
     * @return int
     */
    public static function CreateOrderWithSum(int $userId, float $sumPrice, string $siteId): ?int
    {
        $paySystemId   = 9;                                         // 9 = Наличные
        $orderStatusId = "F";                                       // F = Выполнен

        $currencyCode = \Bitrix\Currency\CurrencyManager::getBaseCurrency();
        $basket       = \Bitrix\Sale\Basket::create($siteId);
        $item         = $basket->createItem("catalog", self::IMPORT_PRODUCT_ID);
        $item->setFields(array(
            "NAME"                   => "Сумма старых заказов",
            "QUANTITY"               => 1,
            "PRICE"                  => $sumPrice,
            "CUSTOM_PRICE"           => "Y",
            "CURRENCY"               => $currencyCode,
            "LID"                    => $siteId,
            "PRODUCT_PROVIDER_CLASS" => "\Bitrix\Catalog\Product\CatalogProvider"
        ));
        $basket->refresh();
        $order = \Bitrix\Sale\Order::create($siteId, $userId);
        $order->setPersonTypeId(1);
        $order->setBasket($basket);
        $order->setFields(array(
            "CURRENCY"  => $currencyCode,
            "STATUS_ID" => $orderStatusId
        ));
        $paymentCollection = $order->getPaymentCollection();
        $payment           = $paymentCollection->createItem();
        $payment->setFields(array(
            "SUM"             => $sumPrice,
            "PAY_SYSTEM_ID"   => $paySystemId,
            "PAY_SYSTEM_NAME" => "Opencart",
            "CURRENCY"        => $order->getCurrency()
        ));
        $payment->setPaid("Y");
        $order->doFinalAction(true);
        $order->save();
        $orderId = $order->getId();

        return $orderId;
    }

    /**
     * Обрабатывает вход пользователей, перенесённых из Opencart
     * (для них пароли хранятся в виде хеша)
     *
     * @param $arFields
     *
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function OnBeforeUserLoginHandler($arFields)
    {
        $userRes = \Bitrix\Main\UserTable::getList(array(
            'select' => array("*", "UF_OPENCART_PASSWORD_HASH"),
            'filter' => array('=LOGIN' => $arFields["LOGIN"]),
            'limit'  => 1
        ));
        if ($bxUser = $userRes->fetch()) {
            if ( ! empty($bxUser["UF_OPENCART_PASSWORD_HASH"])) {
                $arHash  = explode(":", $bxUser["UF_OPENCART_PASSWORD_HASH"]);
                $newHash = sha1($arHash[1] . sha1($arHash[1] . sha1($arFields["PASSWORD"])));
                if ($arHash[0] == $newHash) {
                    $obUser = new \CUser;
                    $obUser->Update($bxUser["ID"], array(
                        "PASSWORD"                  => $arFields["PASSWORD"],
                        "UF_OPENCART_PASSWORD_HASH" => false
                    ));
                } elseif ($arHash[0] == md5($arFields["PASSWORD"])) {
                    $obUser = new \CUser;
                    $obUser->Update($bxUser["ID"], array(
                        "PASSWORD"                  => md5($arFields["PASSWORD"]),
                        "UF_OPENCART_PASSWORD_HASH" => false
                    ));
                }
            }
        }
    }
}