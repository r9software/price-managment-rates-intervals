<?php
include_once("config.php");

/**
 * Returns all the prices in the month selected
 * @param $dbh
 * @param $date String date selected
 */
function getAllPrices($dbh, $date)
{
    $d = new DateTime($date);
    $endDate = $d->format('Y-m-t');
    $d->modify('first day of this month');
    $startDate = $d->format('Y-m-j');
    $query = "SELECT * FROM " . PRICE_DATES . " WHERE date_start>='$startDate' AND date_start<='$endDate' ORDER BY date_start ASC";
    $fetchResult = array();
    foreach ($dbh->query($query) as $data) {
        $dateObj = new stdClass();
        $dateObj->id = $data['id'];
        $dateObj->date_start = $data['date_start'];
        $dateObj->date_end = $data['date_end'];
        $dateObj->price = $data['price'];
        array_push($fetchResult, $dateObj);
    }
    print json_encode($fetchResult);
}

/**
 * Validates the endpoint for delete the price range
 * @param $dbh
 * @param $id
 */
function deletePrice($dbh, $id)
{
    $query = "SELECT * FROM " . PRICE_DATES . " WHERE `id`= :price_id ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":price_id" => $id));
    $data = $stmt->fetchAll();
    if (!isset($data[0]))
        printError("Selected Id was not found");
    deleteRow($dbh, $id);
    print json_encode(["Success"]);
    exit;

}

/**
 * Deletes the row
 * @param $dbh
 * @param $id
 * @return bool
 */
function deleteRow($dbh, $id)
{
    $query = "DELETE FROM `" . PRICE_DATES . "` WHERE `id`= :price_id";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":price_id" => $id));
    return true;
}

/**
 * @param $error String returns the error message to the client
 */
function printError($error)
{
    http_response_code(404);
    print json_encode(["error" => $error]);
    exit;
}

/**
 * Inserts a new Price
 * @param $dbh
 */
function insertNewPrice($dbh)
{
    if (isset($_GET['date_start']) && isset($_GET['date_end']) && isset($_GET['price'])) {
        $dateStart = $_GET['date_start'];
        $dateEnd = $_GET['date_end'];
        $price = $_GET['price'];
        $results = array();
        //use case: delete all rows inside of the time frame
        //all elements are smaller
        deleteElementsInSamePeriod($dbh, $dateStart, $dateEnd);
        //use case; Review before to join or adjust dates
        $shouldInsert = processLeftRightSideOfPeriod($dbh, $dateStart, $dateEnd, $price, $results);
        //use case; Review after to join or adjust dates
        $shouldInsert = processCaseWhenPeriodIsInsidePeriod($dbh, $dateStart, $dateEnd, $shouldInsert, $price, $results);
        if ($shouldInsert) {
            $result = insertNewRow($dbh, $dateStart, $dateEnd, $price);
            array_push($results, $result);
            joinAfterAndBeforeIfNeeded($dbh, $dateStart, $dateEnd, $price);
        }
        print json_encode($results);
        exit;
    } else {
        printError("Params are required");
    }
}

/**
 * @param $dbh
 * @param $dateStart
 * @param $dateEnd
 * @param $price
 * @param $results
 *
 * @return $shouldInsert
 */
function processLeftRightSideOfPeriod($dbh, &$dateStart, $dateEnd, $price, &$results)
{

    $shouldInsert = true;
    $joinId = 0;

    $query = "SELECT * FROM " . PRICE_DATES . " WHERE `date_start`< :date_start AND `date_end`>= :date_start AND  `date_end`<=:date_end  ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":date_start" => $dateStart, ":date_end" => $dateEnd));
    $data = $stmt->fetchAll();
    if (isset($data[0])) {
        try {
            $priceRange = $data[0];
            $joinId = $priceRange['id'];
            $priceDB = $priceRange['price'];
            //if price is the same join
            $newEnd = $dateEnd;
            if ($priceDB != $price) {
                $d = new DateTime($dateStart);
                $d->sub(new DateInterval("P1D"));
                $newEnd = $d->format('Y-m-d');
            } else {
                $shouldInsert = false;
                $dateStart = $priceRange['date_start'];
            }
        } catch (Exception $e) {
            printError("Error in interval");
        }
        $sql = "UPDATE " . PRICE_DATES . " SET date_end = :date_end WHERE id = :id";
        $stmt = $dbh->prepare($sql);
        $stmt->execute(array(":date_end" => $newEnd, ":id" => $priceRange['id']));
        $priceRange['date_end'] = $dateEnd;
        $resultObj = getObjectFromRow($priceRange);
        array_push($results, $resultObj);

    }

    //use case; Review after to join or adjust dates
    $query = "SELECT * FROM " . PRICE_DATES . " WHERE `date_start`>= :date_start AND `date_start`< :date_end AND  `date_end`>:date_end  ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":date_start" => $dateStart, ":date_end" => $dateEnd));
    $data = $stmt->fetchAll();
    if (isset($data[0])) {
        $priceRange = $data[0];
        $priceDB = $priceRange['price'];
        //if already joined left and price is the same delete the new selection, and join to the next step
        if (!$shouldInsert && $price == $priceDB) {
            $query = $dbh->prepare("DELETE FROM " . PRICE_DATES . " WHERE `id` = :id_selection ");
            $query->execute(array(":id_selection" => $priceRange['id']));
            $sql = "UPDATE " . PRICE_DATES . " SET date_end = :date_end WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(":date_end" => $priceRange['date_end'], ":id" => $joinId));
        } else {
            try {
                //if price is the same join
                $newStart = $dateStart;
                if ($priceDB != $price) {
                    $d = new DateTime($dateEnd);
                    $d->add(new DateInterval("P1D"));
                    $newStart = $d->format('Y-m-d');
                    $shouldInsert = true;
                } else {
                    $shouldInsert = false;
                    $newStart = $dateStart;
                };
                $sql = "UPDATE " . PRICE_DATES . " SET date_start = :date_start WHERE id = :id";
                $stmt = $dbh->prepare($sql);
                $stmt->execute(array(":date_start" => $newStart, ":id" => $priceRange['id']));
            } catch (Exception $e) {
                printError("Error in interval");
            }

            $priceRange['date_start'] = $dateStart;
            $resultObj = getObjectFromRow($priceRange);
            array_push($results, $resultObj);
        }
    }
    if ($joinId != 0) {
        joinAfterAndBeforeIfNeeded($dbh, $dateStart, $dateEnd, $price);
    }
    return $shouldInsert;
}

/**
 * @param $priceRange
 * @return stdClass
 */
function getObjectFromRow($priceRange)
{
    $resultObj = new stdClass();
    $resultObj->date_start = $priceRange['date_start'];
    $resultObj->date_end = $priceRange['date_end'];
    $resultObj->id = $priceRange['id'];
    $resultObj->price = $priceRange['price'];
    return $resultObj;
}

/**
 *
 * This method checks if after and before the new period inserted is there any period that should join
 * @param $dbh
 * @param $dateStart
 * @param $dateEnd
 * @param $price
 * @param $id
 * @param $results
 */

function joinAfterAndBeforeIfNeeded($dbh, $dateStart, $dateEnd, $price)
{

    $query = "SELECT * FROM " . PRICE_DATES . " WHERE `date_end`= :date_end AND `date_start`=:date_start AND`price`=:price ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":price" => $price, ":date_end" => $dateEnd, ":date_start" => $dateStart));
    $selected = $stmt->fetchAll();
    if (isset($selected[0])) {
        $selected = $selected[0];
        $d = new DateTime($dateStart);
        $d->sub(new DateInterval("P1D"));
        $newEnd = $d->format('Y-m-d');
        $d = new DateTime($dateEnd);
        $d->add(new DateInterval("P1D"));
        $newStart = $d->format('Y-m-d');
        //check left side
        $query = "SELECT * FROM " . PRICE_DATES . " WHERE `date_end`= :date_end AND `price`=:price ORDER BY id DESC";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(":price" => $price, ":date_end" => $newEnd));
        $data = $stmt->fetchAll();
        if (isset($data[0])) {
            $priceRange = $data[0];
            $query = $dbh->prepare("DELETE FROM " . PRICE_DATES . " WHERE `id`=  :id ");
            $query->execute(array(":id" => $priceRange['id']));
            $sql = "UPDATE " . PRICE_DATES . " SET date_start = :date_start WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(":date_start" => $priceRange['date_start'], ":id" => $selected['id']));
        }
        //check right side
        $query = "SELECT * FROM " . PRICE_DATES . " WHERE `date_start`= :date_start AND `price`=:price ORDER BY id DESC";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(":price" => $price, ":date_start" => $newStart));
        $data = $stmt->fetchAll();
        if (isset($data[0])) {
            $priceRange = $data[0];
            $query = $dbh->prepare("DELETE FROM " . PRICE_DATES . " WHERE `id`=  :id ");
            $query->execute(array(":id" => $priceRange['id']));
            $sql = "UPDATE " . PRICE_DATES . " SET date_end = :date_end WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(":date_end" => $priceRange['date_end'], ":id" => $selected['id']));
        }
    }
}

/**
 *
 * This method process the case when the period is inside of a period
 * @param $dbh
 * @param $dateStart
 * @param $dateEnd
 * @param $price
 * @param $results
 * @return $shouldInsert
 */
function processCaseWhenPeriodIsInsidePeriod($dbh, $dateStart, $dateEnd, $defaultValue, $price, &$results)
{
    $query = "SELECT * FROM " . PRICE_DATES . " WHERE `date_start`< :date_start AND  `date_end`>:date_end  ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $shouldInsert = $defaultValue;
    $stmt->execute(array(":date_start" => $dateStart, ":date_end" => $dateEnd));
    $data = $stmt->fetchAll();
    $ids = array();
    if (isset($data[0])) {
        $priceRange = $data[0];
        if ($priceRange['price'] == $price) {
            $shouldInsert = false;
        } else {
            try {
                $d = new DateTime($dateStart);
                $d->sub(new DateInterval("P1D"));
                $newEnd = $d->format('Y-m-d');
                $d = new DateTime($dateEnd);
                $d->add(new DateInterval("P1D"));
                $newStart = $d->format('Y-m-d');
                $shouldInsert = true;
                //update left side
                $sql = "UPDATE " . PRICE_DATES . " SET date_end = :date_end WHERE id = :id";
                $stmt = $dbh->prepare($sql);
                $stmt->execute(array(":date_end" => $newEnd, ":id" => $priceRange['id']));
                $result = new stdClass();
                $result->date_end = $newEnd;
                $result->id = $priceRange['id'];
                $result->date_start = $priceRange['date_start'];
                $result->price = $priceRange['price'];
                array_push($results, $result);
                //update right side
                $insertQuery = "INSERT INTO `" . PRICE_DATES . "` (`date_start`, `date_end`, `price`) VALUES (:date_start, :date_end, :price)";
                $stmt = $dbh->prepare($insertQuery);
                $stmt->execute(array(":date_start" => $newStart, ":date_end" => $priceRange['date_end'], ":price" => $priceRange['price']));
                $result = new stdClass();
                $result->date_end = $priceRange['date_end'];
                $result->id = $dbh->lastInsertId();
                $result->date_start = $newStart;
                $result->price = $priceRange['price'];
                array_push($results, $result);
            } catch (Exception $e) {
            }

        }

    }
    return $shouldInsert;
}

/**
 * @param $dbh
 * @param $dateStart
 * @param $dateEnd
 */
function deleteElementsInSamePeriod($dbh, $dateStart, $dateEnd)
{
    $query = "SELECT id FROM " . PRICE_DATES . " WHERE `date_start`>= :date_start AND `date_end`<= :date_end  ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":date_start" => $dateStart, ":date_end" => $dateEnd));
    $data = $stmt->fetchAll();
    $ids = array();
    if (isset($data[0])) {
        foreach ($data as $selectedIds) {
            $ids[] = (int)$selectedIds['id'];
        }
        $ids = implode(',', $ids);
        $query = $dbh->prepare("DELETE FROM " . PRICE_DATES . " WHERE `id` IN ( :id_selection )");
        $query->execute(array(":id_selection" => $ids));
    }
}

/**
 * This method clears the table
 * @param $dbh
 */
function clearTable($dbh)
{

    $query = $dbh->prepare("DELETE FROM " . PRICE_DATES);
    $query->execute();
    print json_encode(['success' => true]);
    exit;
}

/**
 * Updates the price
 * @param $dbh
 */
function updatePrice($dbh, $price, $id)
{
    $sql = "UPDATE " . PRICE_DATES . " SET price = :price WHERE id = :id";
    $stmt = $dbh->prepare($sql);
    $stmt->execute(array(":price" => $price, ":id" => $id));
    $result = new stdClass();
    $result->price = $price;
    $result->id = $id;
    print json_encode($result);
    exit;
}

/**
 * Inserts the new row with the date from the API call
 * @param $dbh
 * @param $dateStart
 * @param $dateEnd
 * @param $price
 * @param $result
 * @return mixed
 */
function insertNewRow($dbh, $dateStart, $dateEnd, $price)
{
    $result = new stdClass();
    $insertQuery = "INSERT INTO `" . PRICE_DATES . "` (`date_start`, `date_end`, `price`) VALUES (:date_start, :date_end, :price)";
    $stmt = $dbh->prepare($insertQuery);
    $stmt->execute(array(":date_start" => $dateStart, ":date_end" => $dateEnd, ":price" => $price));
    $result->id = $dbh->lastInsertId();
    $result->date_start = $dateStart;
    $result->date_end = $dateEnd;
    $result->price = $price;
    return $result;
}


/**
 * Point of access to the API, depending of the method is what will be executed
 * Since the server is very limited, I could not use POST, PUT, DELETE, so I'm using GET with the method to do the
 * API operations
 */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    //case when date is sent, return the list of prices
    if (isset($_GET['date'])) {
        $date = $_GET['date'];
        getAllPrices($dbh, $date);
    } else if (($_GET['method'] == "delete" || $_GET['method'] == "Delete") && (isset($_GET['id']))) {
        deletePrice($dbh, $_GET['id']);
    } else if (($_GET['method'] == "add" || $_GET['method'] == "Add")) {
        insertNewPrice($dbh);
    } else if (($_GET['method'] == "update" || $_GET['method'] == "Update") && (isset($_GET['id']))) {
        if (isset($_GET['date_changed']) && $_GET['date_changed']) {
            $deleted = deleteRow($dbh, $_GET['id']);
            if ($deleted)
                insertNewPrice($dbh);
            else
                printError("We could not found that period");
        } else {
            updatePrice($dbh, $_GET['price'], $_GET['id']);
        }
    } else if (($_GET['method'] == "clear" || $_GET['method'] == "Clear")) {
        clearTable($dbh);
    } else {
        printError("Unrecognizable method");
    }
} else {
    printError("Unrecognizable method");
}
