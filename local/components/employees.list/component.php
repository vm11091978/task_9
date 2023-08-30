<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Highloadblock as HL;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Fields\Relations\Reference;

CModule::IncludeModule("highloadblock");

if ($USER->IsAuthorized()) {  
    $positionCode = getPositionCode($USER->GetLogin());

    if (empty($positionCode)) { 
        $arResult['error'] = 'nologin';
    } else {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $_GET['start'] && $_GET['finish']) {
            $startTime = str_replace(':', '', $_GET['start']);
            $endTime = str_replace(':', '', $_GET['finish']);

            if ($_GET['id']) {
                $idEntry = addOrder($_GET['id'], $startTime, $endTime);
                if (isset($idEntry)) {
                    $arResult['carIsOrdered'] = 'OK';
                } else {
                    $arResult['carIsOrdered'] = 'error';
                }
            } else {
                if ($endTime >= $startTime) {
                    $arResult['startTime'] = $_GET['start'];
                    $arResult['finishTime'] = $_GET['finish'];
                    $newStartTime = $startTime;
                    $newEndTime = $endTime;
                } else {
                    $arResult['startTime'] = $_GET['finish'];
                    $arResult['finishTime'] = $_GET['start'];
                    $newStartTime = $endTime;
                    $newEndTime = $startTime;
                }

                $arComfortCategories = getComfortCategories($positionCode);
                $idBookedCar = 0;
                $arResult['allCars'] = getFreeCars($arComfortCategories, $idBookedCar);

                $arIdBookedCars = getIdBookedCars($arComfortCategories, $newStartTime, $newEndTime);
                $arResult['freeCars'] = getFreeCars($arComfortCategories, $arIdBookedCars);
            }
        } else {
            $arComfortCategories = getComfortCategories($positionCode);
            $idBookedCar = 0;
            $arResult['allCars'] = getFreeCars($arComfortCategories, $idBookedCar);
        }
    }
} else {
    $arResult['error'] = 'unauthorized';
}

/**
 * Gets the category of the employee's position by his login
 *
 * @param string $userLogin
 *
 * @return int
 */
function getPositionCode($userLogin)
{
    $hlblock = HL\HighloadBlockTable::getList(
        array('filter' => array('NAME' => 'CompanyEmployee'))
    )->fetch();

    if (isset($hlblock['ID'])) {
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();
        $rsData = $entityDataClass::getList(
            array(
                'select' => array('UF_EMPLOYEE_POST'),
                'filter' => array('UF_EMPLOYEE_LOGIN' => $userLogin)
            )
        );

        $arRows = $rsData->fetch();
        
        return $arRows['UF_EMPLOYEE_POST'];
    }
}

/**
 * Gets a list of car comfort categories for an employee
 *
 * @param int $positionCode
 *
 * @return array
 */
function getComfortCategories($positionCode)
{
    $hlblock = HL\HighloadBlockTable::getList(
        array('filter' => array('NAME' => 'CompanyRule'))
    )->fetch();

    if (isset($hlblock['ID'])) {
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();
        $rsData = $entityDataClass::getList(
            array(
                'select' => array('UF_COMFORT_CATEGORY'),
                'order' => array('ID' => 'ASC'),
                'filter' => array('UF_EMPLOYEE_POST' => $positionCode)
            )
        );

        $arAllRows = $rsData->fetchAll();

        $arResult = array();
        foreach ($arAllRows as $key => $value) {
            $arResult[] = $value['UF_COMFORT_CATEGORY'];
        }
        
        return $arResult;
    }
}

/**
 * Gets a class of cars
 *
 * @return string
 */
function getDataClassCars()
{
    $carsHLBlockAll = HL\HighloadBlockTable::getList(
        array('filter' => array('NAME' => 'CompanyCar'))
    )->fetch();

    if (isset($carsHLBlockAll['ID'])) {
        $carsHLblock = HL\HighloadBlockTable::getById($carsHLBlockAll['ID'])->fetch();
        $cars = HL\HighloadBlockTable::compileEntity($carsHLblock);
        $carsDataClass = $cars->getDataClass();

        return $carsDataClass;
    }
}

/**
 * Gets a class of car models
 *
 * @return string
 */
function getDataClassModels()
{
    $modelsHLBlockAll = HL\HighloadBlockTable::getList(
        array('filter' => array('NAME' => 'CompanyCarModels'))
    )->fetch();

    if (isset($modelsHLBlockAll['ID'])) {
        $modelsHLblock = HL\HighloadBlockTable::getById($modelsHLBlockAll['ID'])->fetch();
        $models = HL\HighloadBlockTable::compileEntity($modelsHLblock);
        $modelsDataClass = $models->getDataClass();

        return $modelsDataClass;
    }
}

/**
 * Gets a class of car comfort categories
 *
 * @return string
 */
function getDataClassCategories()
{
    $categoriesHLBlockAll = HL\HighloadBlockTable::getList(
        array('filter' => array('NAME' => 'CategorizingCarComfort'))
    )->fetch();

    if (isset($categoriesHLBlockAll['ID'])) {
        $categoriesHLblock = HL\HighloadBlockTable::getById($categoriesHLBlockAll['ID'])->fetch();
        $categories = HL\HighloadBlockTable::compileEntity($categoriesHLblock);
        $categoriesDataClass = $categories->getDataClass();

        return $categoriesDataClass;
    }
}

/**
 * Gets a class of car orders
 *
 * @return string
 */
function getDataClassOrders()
{
    $ordersHLBlockAll = HL\HighloadBlockTable::getList(
        array('filter' => array('NAME' => 'CompanyCarOrders'))
    )->fetch();

    if (isset($ordersHLBlockAll['ID'])) {
        $ordersHLblock = HL\HighloadBlockTable::getById($ordersHLBlockAll['ID'])->fetch();
        $orders = HL\HighloadBlockTable::compileEntity($ordersHLblock);
        $ordersDataClass = $orders->getDataClass();

        return $ordersDataClass;
    }
}

/**
 * Gets a list of available cars for a specified period of time
 * If null is passed as the second parameter,
 * it gets a list of all cars potentially available for the current employee
 *
 * @param    array   $arComfortCategories
 * @param array|null $arIdBookedCars
 *
 * @return array
 */
function getFreeCars($arComfortCategories, $arIdBookedCars)
{
    $carsDataClass = getDataClassCars();
    $modelsDataClass = getDataClassModels();
    $categoriesDataClass = getDataClassCategories();

    if (! empty($carsDataClass) && ! empty($modelsDataClass) && ! empty($categoriesDataClass)) {
        $iterator = $carsDataClass::query()
            ->whereIn('CATEGORIES.UF_COMFORT_CATEGORY', $arComfortCategories)
            ->whereNotIn('ID', $arIdBookedCars)
            ->addSelect('ID')
            ->addSelect('UF_DRIVER_NAME', 'DRIVER_NAME')
            ->addSelect('MODELS.UF_CAR_MODELS', 'CAR_MODEL')
            ->addSelect('CATEGORIES.UF_COMFORT_CATEGORY', 'COMFORT_CATEGORY')
            ->registerRuntimeField(new Reference(
                'MODELS',
                $modelsDataClass,
                Join::on('this.UF_CAR_MODEL', 'ref.ID')
            ))
            ->registerRuntimeField(new Reference(
                'CATEGORIES',
                $categoriesDataClass,
                Join::on('this.UF_CAR_MODEL', 'ref.ID')
            ));

        $elements = $iterator->fetchAll();

        return $elements;
    }
}

/**
 * Gets a list of unique IDs of cars booked for a specified period of time
 *
 * @param array $arComfortCategories
 * @param  int  $newStartTime
 * @param  int  $newEndTime
 *
 * @return array
 */
function getIdBookedCars($arComfortCategories, $newStartTime, $newEndTime)
{
    $ordersDataClass = getDataClassOrders();
    $carsDataClass = getDataClassCars();
    $categoriesDataClass = getDataClassCategories();

    if (! empty($ordersDataClass) && ! empty($carsDataClass) && ! empty($categoriesDataClass)) {
        $iterator = $ordersDataClass::query()
            ->whereIn('CATEGORIES.UF_COMFORT_CATEGORY', $arComfortCategories)
            ->setFilter(array(
                array(
                    'LOGIC' => 'AND',
                    'UF_DATA' => date('Y-m-d')
                ),
                array(
                    'LOGIC' => 'OR',
                    array('><UF_TIME_START' => array($newStartTime, $newEndTime)),
                    array('><UF_TIME_FINISH' => array($newStartTime, $newEndTime)),
                    array('<UF_TIME_START' => $newStartTime, '>UF_TIME_FINISH' => $newEndTime)
                )
            ))
            ->addSelect('UF_CAR_ID', 'CAR_ID')
            ->addSelect('CARS.UF_CAR_MODEL', 'CAR_MODEL')
            ->registerRuntimeField(new Reference(
                'CARS',
                $carsDataClass,
                Join::on('this.UF_CAR_ID', 'ref.ID')
            ))
            ->registerRuntimeField(new Reference(
                'CATEGORIES',
                $categoriesDataClass,
                Join::on('this.CAR_MODEL', 'ref.ID')
            ));

        $arAllRows = $iterator->fetchAll();

        $elements = array();
        foreach ($arAllRows as $key => $value) {
            $elements[] = $value['CAR_ID'];
        }

        return array_unique($elements);
    }
}

/**
 * Records a new car order to the database
 *
 * @param int $carId
 * @param int $newStartTime
 * @param int $newEndTime
 *
 * @return int
 */
function addOrder($carId, $newStartTime, $newEndTime)
{
    $ordersDataClass = getDataClassOrders();

    $arElementFields = array(
        'UF_CAR_ID' => $carId,
        'UF_DATA' => date('Y-m-d'),
        'UF_TIME_START' => $newStartTime,
        'UF_TIME_FINISH' => $newEndTime,
    );

    $obResult = $ordersDataClass::add($arElementFields);
    $ID = $obResult->getID();
    
    return $ID;
}
?>

<!doctype html>
<html lang="ru">

<head>
    <meta charSet="utf-8" />
    <meta name='viewport' content='width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, shrink-to-fit=no, viewport-fit=cover'>
    <meta http-equiv='X-UA-Compatible' content='ie=edge'>
    <style>
        html,
        body {
            background: #eee;
        }
        #iframe {
            max-width: 720px;
        }
        td {
            padding-right: 20px;
            padding-bottom: 10px;
        }
    </style>
</head>

<body>
    <?if(isset($arResult['error'])):?>
        <?if($arResult['error'] == 'unauthorized'):?>
            <h4>Неавторизованные пользователи не могут просматривать эту страницу. Войдите.</h4>
        <?endif?>
        <?if($arResult['error'] == 'nologin'):?>
            <h4>Для Вас недоступна услуга "Заказ служебного автомобиля"</h4>
        <?endif?>
    <?elseif(isset($arResult['carIsOrdered'])):?>
        <?if($arResult['carIsOrdered'] == 'OK'):?>
            <h4>Служебный автомобиль успешно заказан</h4>
            <a href="zakazat-avtomobil.php">Выбрать другое время</a>
        <?endif?>
        <?if($arResult['carIsOrdered'] == 'error'):?>
            <h4>При заказе возникла ошибка</h4>
            <a href="zakazat-avtomobil.php">Попробовать ещё раз</a>
        <?endif?>
    <?else:?>
        <p>Вам могут быть доступны для заказа следующие автомобили:</p>

            <div class="table-entries">
                <table>
                    <tr>
                        <td><b>Марка автомобиля</b></td>
                        <td><b>Кат. комфорта</b></td>
                        <td><b>Водитель</b></td>
                    </tr>
                    <?foreach($arResult['allCars'] as $key => $car):?>
                    <tr>
                        <td><?=$car['CAR_MODEL']?></td>
                        <td><?=$car['COMFORT_CATEGORY']?></td>
                        <td><?=$car['DRIVER_NAME']?></td>
                    </tr>
                    <?endforeach;?>
                </table>
            </div>
        <br>
        <?if(isset($arResult['freeCars'])):?>
            <?if(! empty($arResult['freeCars'])):?>
                <p>На выбранное время <?=$arResult['startTime']?> - <?=$arResult['finishTime']?> вам доступны следующие автомобили:</p>

                <div class="table-entries">
                    <table>
                        <tr>
                            <td><b>ID машины</b></td>
                            <td><b>Марка автомобиля</b></td>
                            <td><b>Кат. комфорта</b></td>
                            <td><b>Водитель</b></td>
                            <td></td>
                        </tr>
                        <?foreach($arResult['freeCars'] as $key => $car):?>
                        <tr>
                            <td><?=$car['ID']?></td>
                            <td><?=$car['CAR_MODEL']?></td>
                            <td><?=$car['COMFORT_CATEGORY']?></td>
                            <td><?=$car['DRIVER_NAME']?></td>
                            <td><a href="?id=<?=$car['ID']?>&start=<?=str_replace(':', '%3A', $arResult['startTime']);?>&finish=<?=str_replace(':', '%3A', $arResult['finishTime']);?>">заказать</a></td>
                        </tr>
                        <?endforeach;?>
                    </table>
                </div>
            <?else:?>
                <p>На выбранное время <?=$arResult['startTime']?> - <?=$arResult['finishTime']?> нет доступных для заказа автомобилей</p>
            <?endif?>

            <a href="zakazat-avtomobil.php">Выбрать другое время</a>
        <?else:?>
            <p>Чтобы проверить наличие свободных автомобилей, выберите время, на которое вам нужен автомобиль</p>
            <form action="" method="GET">
                <p>
                    <input type="time" name="start" required="">
                    <input type="time" name="finish" required="">
                </p>
                <p><input type="submit"></p>
            </form>
        <?endif?>
    <?endif?>
</body>

</html>
