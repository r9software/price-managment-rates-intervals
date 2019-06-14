<?php
include_once("config.php");
/**
 * Returns all the contacts in the database
 * @param $dbh
 */
function getAllContacts($dbh)
{
    $query = "SELECT * FROM " . CONTACT_TABLE . " ORDER BY id DESC";
    $fetchResult = array();
    foreach ($dbh->query($query) as $data) {
        $contact = new stdClass();
        $contact->id = $data['id'];
        $contact->name = $data['name'];
        $contact->surnames = $data['surnames'];
        $contact->photo = $data['photo'];
        $phones = array();
        $emails = array();
        $pQuery = "SELECT * FROM " . USER_PHONE_TABLE . " WHERE contact_id=$contact->id ORDER BY id DESC";
        foreach ($dbh->query($pQuery) as $pData) {
            $phone = new stdClass();
            $phone->id = $pData['id'];
            $phone->phone = $pData['phone'];
            array_push($phones, $phone);
        }
        $emailQuery = "SELECT * FROM " . USER_EMAIL_TABLE . " WHERE contact_id=$contact->id ORDER BY id DESC";
        foreach ($dbh->query($emailQuery) as $pData) {
            $email = new stdClass();
            $email->id = $pData['id'];
            $email->phone = $pData['email'];
            array_push($emails, $email);
        }
        $contact->phones = $phones;
        $contact->emails = $emails;
        array_push($fetchResult, $contact);
    }
    print json_encode($fetchResult);
    exit;
}

/**
 * Uploads the photo to the server
 */
function uploadPhoto()
{
    if (empty($_FILES)) {
        return false;
    }
    $currentDir = getcwd();
    $uploadDirectory = "/uploads/";
    $errors = []; // Store all foreseen and unforseen errors here
    $fileExtensions = ['jpeg', 'jpg', 'png']; // Get all the file extensions
    $fileName = $_FILES['photo']['name'];
    $fileSize = $_FILES['photo']['size'];
    $fileTmpName = $_FILES['photo']['tmp_name'];
    $fileType = $_FILES['photo']['type'];
    $fileExtension = strtolower(end(explode('.', $fileName)));

    $uploadPath = $currentDir . $uploadDirectory . basename($fileName);

    if (!in_array($fileExtension, $fileExtensions)) {
        $errors[] = "This file extension is not allowed. Please upload a JPEG or PNG file";
    }

    if ($fileSize > 2000000) {
        $errors[] = "This file is more than 2MB. Sorry, it has to be less than or equal to 2MB";
    }

    if (empty($errors)) {
        $didUpload = move_uploaded_file($fileTmpName, $uploadPath);

        if ($didUpload) {
            return $uploadPath;
        } else {
            echo "An error occurred somewhere. Try again or contact the admin";
        }
    } else {
        printError($errors);
    }
}


function printError($error)
{
    print json_encode(["error" => $error]);
    exit;
}

/**
 * Inserts a new contact
 * @param $dbh
 */
function insertNewContact($dbh)
{
    if (isset($_POST['name']) && isset($_POST['surnames'])) {
        $result = new stdClass();
        $contactQuery = "INSERT INTO `" . CONTACT_TABLE . "` (`name`, `surnames`, `photo`) VALUES (:name, :surnames, :photo)";
        $stmt = $dbh->prepare($contactQuery);
        $firstname = $_POST['name'];
        $surnames = $_POST['surnames'];
        $photo = uploadPhoto();
        if (!$photo)
            $photo = null;
        $stmt->execute(array(":name" => $firstname, ":surnames" => $surnames, ":photo" => $photo));
        $result->id = $dbh->lastInsertId();
        $result->name = $firstname;
        $result->surnames = $surnames;
        $result->photo = $photo;
        if (isset($_POST['phones'])) {
            $phones = json_decode($_POST['phones']);
            $phonesResult = array();
            foreach ($phones as $phone) {
                $phoneQuery = "INSERT INTO `" . USER_PHONE_TABLE . "` ( `phone`, `contact_id`) VALUES (:phone, :contact_id)";
                $stmt = $dbh->prepare($phoneQuery);
                $stmt->execute(array(":phone" => $phone, ":contact_id" => $result->id));
                $pInserted = new stdClass();
                $pInserted->id = $dbh->lastInsertId();
                $pInserted->phone = $phone;
                array_push($phonesResult, $pInserted);
            }
            $result->phones = $phonesResult;
        }

        if (isset($_POST['emails'])) {
            $emails = json_decode($_POST['emails']);
            $emailsResult = array();
            foreach ($emails as $email) {
                $emailQuery = "INSERT INTO `" . USER_EMAIL_TABLE . "` ( `email`, `contact_id`) VALUES (:email, :contact_id)";
                $stmt = $dbh->prepare($emailQuery);
                $stmt->execute(array(":email" => $email, ":contact_id" => $result->id));
                $pInserted = new stdClass();
                $pInserted->id = $dbh->lastInsertId();
                $pInserted->email = $email;
                array_push($emailsResult, $pInserted);
            }
            $result->emails = $emailsResult;
        }
        print json_encode($result);
        exit;
    } else {
        printError("'name' and 'surnames' are required");
    }
}

/**
 * Deletes the contact
 * @param $dbh
 * @param $id
 */
function deleteContact($dbh, $id)
{
    $query = "SELECT * FROM " . CONTACT_TABLE . " WHERE `id`= :contact_id ORDER BY id DESC";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":contact_id" => $id));
    $data = $stmt->fetchAll();
    if (!isset($data[0]))
        printError("Selected Id was not found");
    $query = "DELETE FROM `" . CONTACT_TABLE . "` WHERE `id`= :contact_id";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":contact_id" => $id));
    $query = "DELETE FROM `" . USER_PHONE_TABLE . "` WHERE `contact_id`= :contact_id";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":contact_id" => $id));

    $query = "DELETE FROM `" . USER_EMAIL_TABLE . "` WHERE `contact_id`= :contact_id";
    $stmt = $dbh->prepare($query);
    $stmt->execute(array(":contact_id" => $id));
    print json_encode(["Success"]);
    exit;

}

/**
 * @param $dbh
 */
function updateContact($dbh)
{
    if (isset($_POST['id'])) {
        $id = $_POST['id'];
        $query = "SELECT * FROM " . CONTACT_TABLE . " WHERE `id`= :contact_id ORDER BY id DESC";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(":contact_id" => $id));
        $data = $stmt->fetchAll();
        if (!isset($data[0]))
            printError("Selected Id was not found");

        foreach ($data as $contact) {
            $result = new stdClass();
            $sql = "UPDATE " . CONTACT_TABLE . "
            SET 
              name = :name,
              surnames = :surnames
            WHERE id = :id";
            $stmt = $dbh->prepare($sql);
            if (isset($_POST['name']))
                $firstname = $_POST['name'];
            else
                $firstname = $contact['name'];

            if (isset($_POST['surnames']))
                $surnames = $_POST['surnames'];
            else
                $surnames = $contact['surnames'];

            $photo = uploadPhoto();
            if (!$photo)
                $photo = null;
            $stmt->execute(array(":name" => $firstname, ":surnames" => $surnames, ":photo" => $photo));
            $result->id = $id;
            $result->name = $firstname;
            $result->surnames = $surnames;
            $result->photo = $photo;
            if (isset($_POST['phones'])) {
                $phones = json_decode($_POST['phones']);
                $phonesResult = array();
                foreach ($phones as $phone) {
                    $phoneQuery = "UPDATE `" . USER_PHONE_TABLE . "` SET phone= :phone where id = :id AND contact_id= :contact_id";
                    $stmt = $dbh->prepare($phoneQuery);
                    $stmt->execute(array(":phone" => $phone->phone, ":id" => $phone->id,"contact_id"=>$id));
                    $pInserted = new stdClass();
                    $pInserted->id = $phone->id;
                    $pInserted->phone = $phone->phone;
                    array_push($phonesResult, $pInserted);
                }
                $result->phones = $phonesResult;
            }

            if (isset($_POST['emails'])) {
                $emails = json_decode($_POST['emails']);
                $emailsResult = array();
                foreach ($emails as $email) {
                    $emailQuery = "UPDATE `" . USER_EMAIL_TABLE . "` SET email= :email where id = :id AND contact_id= :contact_id";
                    $stmt = $dbh->prepare($emailQuery);
                    $stmt->execute(array(":email" => $email->email, ":id"=>$email->id,":contact_id" => $result->id));
                    $pInserted = new stdClass();
                    $pInserted->id = $email->id;
                    $pInserted->email = $email->email;
                    array_push($emailsResult, $pInserted);
                }
                $result->emails = $emailsResult;
            }
        }
        print json_encode($result);
        exit;
    } else {
        printError("'name' and 'surnames' are required");
    }

}

/**
 * Point of access to the API, depending of the method is what will be executed
 */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    getAllContacts($dbh);
} else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['method'] == "add" || $_POST['method'] == "Add")) {
    insertNewContact($dbh);
} else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['method'] == "delete" || $_POST['method'] == "Delete")) {
    deleteContact($dbh, $_POST['id']);
} else if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['method'] == "update" || $_POST['method'] == "Update")) {
    updateContact($dbh);
} else {
    printError("Unrecognizable method");
}
