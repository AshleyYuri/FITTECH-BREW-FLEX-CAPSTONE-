<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

// Fetch logged-in user information
$username = $_SESSION["username"];
$usertype = $_SESSION["usertype"];

// Fetch user information
$query = "SELECT username, email, profile_picture FROM users WHERE username = ? AND usertype = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $usertype);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch all members for datalist
$members_query = "SELECT member_id, CONCAT(first_name, ' ', last_name) AS full_name FROM members";
$members_result = $conn->query($members_query);
$members = $members_result->fetch_all(MYSQLI_ASSOC);

// Fetch all coaches for dropdown with expertise
$coaches_query = "SELECT coach_id, CONCAT(first_name, ' ', last_name) AS full_name, expertise FROM coaches";
$coaches_result = $conn->query($coaches_query);
$coaches = $coaches_result->fetch_all(MYSQLI_ASSOC);


date_default_timezone_set('Asia/Manila');


date_default_timezone_set('Asia/Manila'); // Set timezone to Philippines

$currentDateTime = date('Y-m-d\TH:i'); // Format for datetime-local input

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Reset payment-related variables to avoid residual values
    $monthly_amount = 0;
    $monthly_plan_payment_date = null;
    $monthly_plan_expiration_date = null;

    $renewal_amount = 0;
    $membership_renewal_payment_date = null;
    $membership_expiration_date = null;

    $locker_amount = 0;
    $locker_payment_date = null;
    $locker_expiration_date = null;

    $coaching_amount = 0;
    $coaching_payment_date = null;

    // Collect data from the form
    $member_id = $_POST['member_id'];
    $coach_id = $_POST['coach_id'] ?? null;

    $coaching_payment_date = $_POST['coaching_payment_date'] ?? null;
    $monthly_plan_payment_date = $_POST['monthly_plan_payment_date'] ?? null;
    $monthly_plan_expiration_date = $_POST['monthly_plan_expiration_date'] ?? null;
    $membership_renewal_payment_date = $_POST['membership_renewal_payment_date'] ?? null;
    $membership_expiration_date = $_POST['renewal_expiry_date'] ?? null;
    $locker_payment_date = $_POST['locker_payment_date'] ?? null;
    $locker_expiration_date = $_POST['locker_expiration_date'] ?? null;

    $coaching_amount = $_POST['coaching_amount'] ?? 0;
    $monthly_amount = $_POST['monthly_amount'] ?? 0;
    $renewal_amount = $_POST['renewal_amount'] ?? 0;
    $locker_amount = $_POST['locker_amount'] ?? 0;

    // Calculate total_amount
    $total_amount = $coaching_amount + $monthly_amount + $renewal_amount + $locker_amount;

    // Validate member existence
    $stmt = $conn->prepare("SELECT member_id FROM members WHERE member_id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo "Error: Member not found.";
        exit;
    }
    $stmt->close();

  // Process Monthly Plan Payment
if (!empty($_POST['monthly_plan_payment_date'])) {
    $monthly_plan_payment_date = $_POST['monthly_plan_payment_date'];
    $monthly_plan_expiration_date = $_POST['monthly_plan_expiration_date'];
    $monthly_amount = $_POST['monthly_amount'];

    $stmt = $conn->prepare("
        INSERT INTO payments (member_id, monthly_plan_payment_date, monthly_plan_expiration_date, monthly_amount)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        monthly_plan_payment_date = VALUES(monthly_plan_payment_date),
        monthly_plan_expiration_date = VALUES(monthly_plan_expiration_date),
        monthly_amount = VALUES(monthly_amount)
    ");
    $stmt->bind_param("issi", $member_id, $monthly_plan_payment_date, $monthly_plan_expiration_date, $monthly_amount);
    $stmt->execute();
    $stmt->close();
    $total_amount += $monthly_amount;
}

// Process Membership Renewal Payment
if (!empty($_POST['membership_renewal_payment_date'])) {
    $membership_renewal_payment_date = $_POST['membership_renewal_payment_date'];
    $membership_expiration_date = $_POST['renewal_expiry_date'];
    $renewal_amount = $_POST['renewal_amount'];

    $stmt = $conn->prepare("
        INSERT INTO payments (member_id, membership_renewal_payment_date, membership_expiration_date, renewal_amount)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        membership_renewal_payment_date = VALUES(membership_renewal_payment_date),
        membership_expiration_date = VALUES(membership_expiration_date),
        renewal_amount = VALUES(renewal_amount)
    ");
    $stmt->bind_param("issi", $member_id, $membership_renewal_payment_date, $membership_expiration_date, $renewal_amount);
    $stmt->execute();
    $stmt->close();
    $total_amount += $renewal_amount;
}

// Process Locker Payment
if (!empty($_POST['locker_payment_date'])) {
    $locker_payment_date = $_POST['locker_payment_date'];
    $locker_expiration_date = $_POST['locker_expiration_date'];
    $locker_amount = $_POST['locker_amount'];

    $stmt = $conn->prepare("
        INSERT INTO payments (member_id, locker_payment_date, locker_expiration_date, locker_amount)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        locker_payment_date = VALUES(locker_payment_date),
        locker_expiration_date = VALUES(locker_expiration_date),
        locker_amount = VALUES(locker_amount)
    ");
    $stmt->bind_param("issi", $member_id, $locker_payment_date, $locker_expiration_date, $locker_amount);
    $stmt->execute();
    $stmt->close();
    $total_amount += $locker_amount;
}

// Process Coaching Payment
if (!empty($_POST['coaching_payment_date'])) {
    $coaching_payment_date = $_POST['coaching_payment_date'];
    $coach_id = $_POST['coach_id'];
    $coaching_amount = $_POST['coaching_amount'];

    $stmt = $conn->prepare("
        INSERT INTO payments (member_id, coaching_payment_date, coach_id, coaching_amount)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        coaching_payment_date = VALUES(coaching_payment_date),
        coach_id = VALUES(coach_id),
        coaching_amount = VALUES(coaching_amount)
    ");
    $stmt->bind_param("isii", $member_id, $coaching_payment_date, $coach_id, $coaching_amount);
    $stmt->execute();
    $stmt->close();
    $total_amount += $coaching_amount;
}










    // Update Total Amount
    $stmt = $conn->prepare("UPDATE payments SET total_amount = ? WHERE member_id = ?");
    $stmt->bind_param("ii", $total_amount, $member_id);
    $stmt->execute();





    // Execute the statement and check if successful
    if ($stmt->execute()) {
        // Prepare log entries for each payment type based on current inputs
        $log_queries = [];

        if (isset($_POST['monthly_amount']) && !empty($_POST['monthly_plan_payment_date'])) {
            $log_queries[] = [
                'member_id' => $member_id,
                'transaction_type' => 'Monthly Plan Payment',
                'payment_date' => $_POST['monthly_plan_payment_date'], // Current input
                'payment_amount' => (float) $_POST['monthly_amount'], // Current input
                'plan_type' => 'Monthly Plan'
            ];
        }

        if (isset($_POST['renewal_amount']) && !empty($_POST['membership_renewal_payment_date'])) {
            $log_queries[] = [
                'member_id' => $member_id,
                'transaction_type' => 'Membership Renewal Payment',
                'payment_date' => $_POST['membership_renewal_payment_date'], // Current input
                'payment_amount' => (float) $_POST['renewal_amount'], // Current input
                'plan_type' => 'Membership Renewal'
            ];
        }

        if (isset($_POST['locker_amount']) && !empty($_POST['locker_payment_date'])) {
            $log_queries[] = [
                'member_id' => $member_id,
                'transaction_type' => 'Locker Payment',
                'payment_date' => $_POST['locker_payment_date'], // Current input
                'payment_amount' => (float) $_POST['locker_amount'], // Current input
                'plan_type' => 'Locker'
            ];
        }

        if (isset($_POST['coaching_amount']) && !empty($_POST['coaching_payment_date'])) {
            $log_queries[] = [
                'member_id' => $member_id,
                'transaction_type' => 'Coaching Session Payment',
                'payment_date' => $_POST['coaching_payment_date'], // Current input
                'payment_amount' => (float) $_POST['coaching_amount'], // Current input
                'plan_type' => 'Coaching'
            ];
        }

        // Insert logs into transaction_logs table
        $log_query = "INSERT INTO transaction_logs (member_id, transaction_type, payment_date, payment_amount, customer_type, plan_type) 
                      VALUES (?, ?, ?, ?, 'Member', ?)";
        $log_stmt = $conn->prepare($log_query);

        foreach ($log_queries as $log) {
            $log_stmt->bind_param(
                "issds", 
                $log['member_id'], 
                $log['transaction_type'], 
                $log['payment_date'], 
                $log['payment_amount'], 
                $log['plan_type']
            );
            $log_stmt->execute();
        }

        $log_stmt->close();

        echo "Payment saved successfully.";
        header("Location: membersuccessfullyadded.php");
        exit;
    } else {
        echo "Error: " . $stmt->error;
        error_log($stmt->error);
    }
    $stmt->close();
}

// Close the database connection
$conn->close();
?>







<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Brew + Flex Gym</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/brew+flex/css/payment.css">

   
    <style>
.select2-container {
    box-sizing: border-box;
    display: inline-block;
    margin: 0;
    vertical-align: middle
}

.select2-container .select2-selection--single {
    box-sizing: border-box;
    cursor: pointer;
    display: block;
    height: 28px;
    user-select: none;
    -webkit-user-select: none
}

.select2-container .select2-selection--single .select2-selection__rendered {
    display: block;
    padding-left: 8px;
    padding-right: 20px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap
}

.select2-container .select2-selection--single .select2-selection__clear {
    position: relative
}

.select2-container[dir="rtl"] .select2-selection--single .select2-selection__rendered {
    padding-right: 8px;
    padding-left: 20px
}

.select2-container .select2-selection--multiple {
    box-sizing: border-box;
    cursor: pointer;
    display: block;
    min-height: 32px;
    user-select: none;
    -webkit-user-select: none
}

.select2-container .select2-selection--multiple .select2-selection__rendered {
    display: inline-block;
    overflow: hidden;
    padding-left: 8px;
    text-overflow: ellipsis;
    white-space: nowrap
}

.select2-container .select2-search--inline {
    float: left
}

.select2-container .select2-search--inline .select2-search__field {
    box-sizing: border-box;
    border: none;
    font-size: 100%;
    margin-top: 5px;
    padding: 0
}

.select2-container .select2-search--inline .select2-search__field::-webkit-search-cancel-button {
    -webkit-appearance: none
}

.select2-dropdown {
    background-color: white;
    border: 1px solid #aaa;
    border-radius: 4px;
    box-sizing: border-box;
    display: block;
    position: absolute;
    left: -100000px;
    width: 100%;
    z-index: 1051
}

.select2-results {
    display: block
}

.select2-results__options {
    list-style: none;
    margin: 0;
    padding: 0
}

.select2-results__option {
    padding: 6px;
    user-select: none;
    -webkit-user-select: none
}

.select2-results__option[aria-selected] {
    cursor: pointer
}

.select2-container--open .select2-dropdown {
    left: 0
}

.select2-container--open .select2-dropdown--above {
    border-bottom: none;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0
}

.select2-container--open .select2-dropdown--below {
    border-top: none;
    border-top-left-radius: 0;
    border-top-right-radius: 0
}

.select2-search--dropdown {
    display: block;
    padding: 4px
}

.select2-search--dropdown .select2-search__field {
    padding: 4px;
    width: 100%;
    box-sizing: border-box
}

.select2-search--dropdown .select2-search__field::-webkit-search-cancel-button {
    -webkit-appearance: none
}

.select2-search--dropdown.select2-search--hide {
    display: none
}

.select2-close-mask {
    border: 0;
    margin: 0;
    padding: 0;
    display: block;
    position: fixed;
    left: 0;
    top: 0;
    min-height: 100%;
    min-width: 100%;
    height: auto;
    width: auto;
    opacity: 0;
    z-index: 99;
    background-color: #fff;
    filter: alpha(opacity=0)
}

.select2-hidden-accessible {
    border: 0 !important;
    clip: rect(0 0 0 0) !important;
    -webkit-clip-path: inset(50%) !important;
    clip-path: inset(50%) !important;
    height: 1px !important;
    overflow: hidden !important;
    padding: 0 !important;
    position: absolute !important;
    width: 1px !important;
    white-space: nowrap !important
}

.select2-container--default .select2-selection--single {
    background-color: #fff;
    border: 1px solid #aaa;
    border-radius: 4px
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #444;
    line-height: 28px
}

.select2-container--default .select2-selection--single .select2-selection__clear {
    cursor: pointer;
    float: right;
    font-weight: bold
}

.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #999
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 26px;
    position: absolute;
    top: 1px;
    right: 1px;
    width: 20px
}

.select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #888 transparent transparent transparent;
    border-style: solid;
    border-width: 5px 4px 0 4px;
    height: 0;
    left: 50%;
    margin-left: -4px;
    margin-top: -2px;
    position: absolute;
    top: 50%;
    width: 0
}

.select2-container--default[dir="rtl"] .select2-selection--single .select2-selection__clear {
    float: left
}

.select2-container--default[dir="rtl"] .select2-selection--single .select2-selection__arrow {
    left: 1px;
    right: auto
}

.select2-container--default.select2-container--disabled .select2-selection--single {
    background-color: #eee;
    cursor: default
}

.select2-container--default.select2-container--disabled .select2-selection--single .select2-selection__clear {
    display: none
}

.select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
    border-color: transparent transparent #888 transparent;
    border-width: 0 4px 5px 4px
}

.select2-container--default .select2-selection--multiple {
    background-color: white;
    border: 1px solid #aaa;
    border-radius: 4px;
    cursor: text
}

.select2-container--default .select2-selection--multiple .select2-selection__rendered {
    box-sizing: border-box;
    list-style: none;
    margin: 0;
    padding: 0 5px;
    width: 100%
}

.select2-container--default .select2-selection--multiple .select2-selection__rendered li {
    list-style: none
}

.select2-container--default .select2-selection--multiple .select2-selection__clear {
    cursor: pointer;
    float: right;
    font-weight: bold;
    margin-top: 5px;
    margin-right: 10px;
    padding: 1px
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #e4e4e4;
    border: 1px solid #aaa;
    border-radius: 4px;
    cursor: default;
    float: left;
    margin-right: 5px;
    margin-top: 5px;
    padding: 0 5px
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: #999;
    cursor: pointer;
    display: inline-block;
    font-weight: bold;
    margin-right: 2px
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #333
}

.select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice,.select2-container--default[dir="rtl"] .select2-selection--multiple .select2-search--inline {
    float: right
}

.select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice {
    margin-left: 5px;
    margin-right: auto
}

.select2-container--default[dir="rtl"] .select2-selection--multiple .select2-selection__choice__remove {
    margin-left: 2px;
    margin-right: auto
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
    border: solid black 1px;
    outline: 0
}

.select2-container--default.select2-container--disabled .select2-selection--multiple {
    background-color: #eee;
    cursor: default
}

.select2-container--default.select2-container--disabled .select2-selection__choice__remove {
    display: none
}

.select2-container--default.select2-container--open.select2-container--above .select2-selection--single,.select2-container--default.select2-container--open.select2-container--above .select2-selection--multiple {
    border-top-left-radius: 0;
    border-top-right-radius: 0
}

.select2-container--default.select2-container--open.select2-container--below .select2-selection--single,.select2-container--default.select2-container--open.select2-container--below .select2-selection--multiple {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #aaa
}

.select2-container--default .select2-search--inline .select2-search__field {
    background: transparent;
    border: none;
    outline: 0;
    box-shadow: none;
}

.select2-container--default .select2-results>.select2-results__options {
    max-height: 200px;
    overflow-y: auto
}

.select2-container--default .select2-results__option[role=group] {
    padding: 0
}

.select2-container--default .select2-results__option[aria-disabled=true] {
    color: #999
}

.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #ddd
}

.select2-container--default .select2-results__option .select2-results__option {
    padding-left: 1em
}

.select2-container--default .select2-results__option .select2-results__option .select2-results__group {
    padding-left: 0
}

.select2-container--default .select2-results__option .select2-results__option .select2-results__option {
    margin-left: -1em;
    padding-left: 2em
}

.select2-container--default .select2-results__option .select2-results__option .select2-results__option .select2-results__option {
    margin-left: -2em;
    padding-left: 3em
}

.select2-container--default .select2-results__option .select2-results__option .select2-results__option .select2-results__option .select2-results__option {
    margin-left: -3em;
    padding-left: 4em
}

.select2-container--default .select2-results__option .select2-results__option .select2-results__option .select2-results__option .select2-results__option .select2-results__option {
    margin-left: -4em;
    padding-left: 5em
}

.select2-container--default .select2-results__option .select2-results__option .select2-results__option .select2-results__option .select2-results__option .select2-results__option .select2-results__option {
    margin-left: -5em;
    padding-left: 6em
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #5897fb;
    color: white
}

.select2-container--default .select2-results__group {
    cursor: default;
    display: block;
    padding: 6px
}

.select2-container--classic .select2-selection--single {
    background-color: #f7f7f7;
    border: 1px solid #aaa;
    border-radius: 4px;
    outline: 0;
    background-image: -webkit-linear-gradient(top, #fff 50%, #eee 100%);
    background-image: -o-linear-gradient(top, #fff 50%, #eee 100%);
    background-image: linear-gradient(to bottom, #fff 50%, #eee 100%);
    background-repeat: repeat-x;
    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#FFFFFFFF', endColorstr='#FFEEEEEE', GradientType=0)
}

.select2-container--classic .select2-selection--single:focus {
    border: 1px solid #5897fb
}

.select2-container--classic .select2-selection--single .select2-selection__rendered {
    color: #444;
    line-height: 28px
}

.select2-container--classic .select2-selection--single .select2-selection__clear {
    cursor: pointer;
    float: right;
    font-weight: bold;
    margin-right: 10px
}

.select2-container--classic .select2-selection--single .select2-selection__placeholder {
    color: #999
}

.select2-container--classic .select2-selection--single .select2-selection__arrow {
    background-color: #ddd;
    border: none;
    border-left: 1px solid #aaa;
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
    height: 26px;
    position: absolute;
    top: 1px;
    right: 1px;
    width: 20px;
    background-image: -webkit-linear-gradient(top, #eee 50%, #ccc 100%);
    background-image: -o-linear-gradient(top, #eee 50%, #ccc 100%);
    background-image: linear-gradient(to bottom, #eee 50%, #ccc 100%);
    background-repeat: repeat-x;
    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#FFEEEEEE', endColorstr='#FFCCCCCC', GradientType=0)
}

.select2-container--classic .select2-selection--single .select2-selection__arrow b {
    border-color: #888 transparent transparent transparent;
    border-style: solid;
    border-width: 5px 4px 0 4px;
    height: 0;
    left: 50%;
    margin-left: -4px;
    margin-top: -2px;
    position: absolute;
    top: 50%;
    width: 0
}

.select2-container--classic[dir="rtl"] .select2-selection--single .select2-selection__clear {
    float: left
}

.select2-container--classic[dir="rtl"] .select2-selection--single .select2-selection__arrow {
    border: none;
    border-right: 1px solid #aaa;
    border-radius: 0;
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
    left: 1px;
    right: auto
}

.select2-container--classic.select2-container--open .select2-selection--single {
    border: 1px solid #5897fb
}

.select2-container--classic.select2-container--open .select2-selection--single .select2-selection__arrow {
    background: transparent;
    border: none
}

.select2-container--classic.select2-container--open .select2-selection--single .select2-selection__arrow b {
    border-color: transparent transparent #888 transparent;
    border-width: 0 4px 5px 4px
}

.select2-container--classic.select2-container--open.select2-container--above .select2-selection--single {
    border-top: none;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
    background-image: -webkit-linear-gradient(top, #fff 0%, #eee 50%);
    background-image: -o-linear-gradient(top, #fff 0%, #eee 50%);
    background-image: linear-gradient(to bottom, #fff 0%, #eee 50%);
    background-repeat: repeat-x;
    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#FFFFFFFF', endColorstr='#FFEEEEEE', GradientType=0)
}

.select2-container--classic.select2-container--open.select2-container--below .select2-selection--single {
    border-bottom: none;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    background-image: -webkit-linear-gradient(top, #eee 50%, #fff 100%);
    background-image: -o-linear-gradient(top, #eee 50%, #fff 100%);
    background-image: linear-gradient(to bottom, #eee 50%, #fff 100%);
    background-repeat: repeat-x;
    filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#FFEEEEEE', endColorstr='#FFFFFFFF', GradientType=0)
}

.select2-container--classic .select2-selection--multiple {
    background-color: white;
    border: 1px solid #aaa;
    border-radius: 4px;
    cursor: text;
    outline: 0
}

.select2-container--classic .select2-selection--multiple:focus {
    border: 1px solid #5897fb
}

.select2-container--classic .select2-selection--multiple .select2-selection__rendered {
    list-style: none;
    margin: 0;
    padding: 0 5px
}

.select2-container--classic .select2-selection--multiple .select2-selection__clear {
    display: none
}

.select2-container--classic .select2-selection--multiple .select2-selection__choice {
    background-color: #e4e4e4;
    border: 1px solid #aaa;
    border-radius: 4px;
    cursor: default;
    float: left;
    margin-right: 5px;
    margin-top: 5px;
    padding: 0 5px
}

.select2-container--classic .select2-selection--multiple .select2-selection__choice__remove {
    color: #888;
    cursor: pointer;
    display: inline-block;
    font-weight: bold;
    margin-right: 2px
}

.select2-container--classic .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #555
}

.select2-container--classic[dir="rtl"] .select2-selection--multiple .select2-selection__choice {
    float: right;
    margin-left: 5px;
    margin-right: auto
}

.select2-container--classic[dir="rtl"] .select2-selection--multiple .select2-selection__choice__remove {
    margin-left: 2px;
    margin-right: auto
}

.select2-container--classic.select2-container--open .select2-selection--multiple {
    border: 1px solid #5897fb
}

.select2-container--classic.select2-container--open.select2-container--above .select2-selection--multiple {
    border-top: none;
    border-top-left-radius: 0;
    border-top-right-radius: 0
}

.select2-container--classic.select2-container--open.select2-container--below .select2-selection--multiple {
    border-bottom: none;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0
}

.select2-container--classic .select2-search--dropdown .select2-search__field {
    border: 1px solid #aaa;
    outline: 0
}

.select2-container--classic .select2-search--inline .select2-search__field {
    outline: 0;
    box-shadow: none
}

.select2-container--classic .select2-dropdown {
    background-color: #fff;
    border: 1px solid transparent
}

.select2-container--classic .select2-dropdown--above {
    border-bottom: none
}

.select2-container--classic .select2-dropdown--below {
    border-top: none
}

.select2-container--classic .select2-results>.select2-results__options {
    max-height: 200px;
    overflow-y: auto
}

.select2-container--classic .select2-results__option[role=group] {
    padding: 0
}

.select2-container--classic .select2-results__option[aria-disabled=true] {
    color: grey
}

.select2-container--classic .select2-results__option--highlighted[aria-selected] {
    background-color: #3875d7;
    color: #fff
}

.select2-container--classic .select2-results__group {
    cursor: default;
    display: block;
    padding: 6px
}

.select2-container--classic.select2-container--open .select2-dropdown {
    border-color: #5897fb
}

</style>


    <style>

/* Sidebar Styles */
.sidebar {
    width: 270px;
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 40px 0;
}

.admin-info {
    display: flex;
    align-items: center;
    flex-direction: column;
    color: #000;
}

.admin-info img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 15px;
}

.admin-info h3 {
    margin-bottom: 5px;
    font-size: 20px;
    font-weight: normal;
}

.admin-info p {
    margin: 5px 0;
    font-size: 12px;
    color: #333;
}

nav.menu {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    padding: 0;
    margin-top: 30px;
}

nav.menu ul {
    list-style: none;
    padding: 0;
}

nav.menu ul li {
    margin-bottom: 20px;
}

nav.menu ul li a {
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    color: #000;
    padding: 10px 20px;
    transition: background-color 0.2s ease;
}

nav.menu ul li a:hover {
    background-color: #71d4fc;
    border-radius: 10px;
}

nav.menu ul li a i {
    margin-right: 15px;
}

nav.menu ul li.active a {
    background-color: #71d4fc;
    border-radius: 10px;
    font-weight: bold;
}

.logout {
    text-align: center;
    margin-bottom: 40px;
}

.logout a {
    text-decoration: none;
    color: #000;
    display: inline-flex;
    align-items: center;
}

.logout a i {
    margin-right: 10px;
}

/* Sidebar Styles */
.sidebar {
    width: 270px; /* Default width */
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20px 0;
    position: fixed; /* Fixed position */
    top: 0;
    left: 0;
    height: 100vh; /* Full height */
    overflow-y: auto; /* Scroll if content overflows */
    transition: width 0.3s ease;
}

.admin-info {
    display: flex;
    align-items: center;
    flex-direction: column;
    color: #000;
}

.admin-info img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 15px;
}

.admin-info h3, .admin-info p {
    margin: 5px 0;
    font-size: 14px;
    text-align: center;
}

nav.menu {
    flex-grow: 1;
    margin-top: 30px;
}

nav.menu ul {
    list-style: none;
    padding: 0;
}

nav.menu ul li a {
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    color: #000;
    padding: 10px 20px;
    transition: background-color 0.3s ease;
}

nav.menu ul li a:hover,
nav.menu ul li.active a {
    background-color: #71d4fc;
    border-radius: 10px;
    font-weight: bold;
}

nav.menu ul li a i {
    margin-right: 10px;
}

.logout {
    text-align: center;
    margin-bottom: 20px;
}

.logout a {
    text-decoration: none;
    color: #000;
    display: inline-flex;
    align-items: center;
}

.logout a i {
    margin-right: 10px;
}

/* Main Content */
.main-content {
    margin-left: 270px; /* Respect sidebar width */
    flex: 1;
    padding: 20px;
    background-color: #fff;
    overflow-x: auto; /* Prevent content overflow */
    transition: margin-left 0.3s ease;
}

/* Responsive Sidebar */
@media (max-width: 768px) {
    .sidebar {
        width: 180px; /* Smaller sidebar on medium screens */
    }

    .main-content {
        margin-left: 180px; /* Adjust main content */
    }

    .admin-info h3,
    .admin-info p {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100px; /* Narrow sidebar for small screens */
        padding: 10px 0;
    }

    nav.menu ul li a {
        justify-content: center; /* Center icons */
        padding: 10px;
    }

    nav.menu ul li a i {
        margin: 0;
    }

    .admin-info h3,
    .admin-info p {
        display: none; /* Hide text for small screens */
    }

    .main-content {
        margin-left: 100px; /* Adjust content for small sidebar */
    }
}











       .hidden {
    display: none !important;
}

        .payment-header {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 20px;
            color: #333;
            text-decoration: underline;
        }
        
/* Modern Styling for h4 in Sections */
#monthlySection h4, 
#renewalSection h4, 
#coachingSection h4, 
#lockerSection h4 {
    font-size: 20px; /* Slightly larger font size */
    font-weight: 600; /* Semi-bold text */
    color: #333; /* Darker text for contrast */
    background: #71d4fc; /* Subtle gradient */
    padding: 12px 16px; /* Inner spacing */
    margin: 15px auto; /* Center with auto margin horizontally */
    border-left: 5px solid #71d4fc; /* Modern blue border on the left */
    border-radius: 8px; /* Rounded corners */
    text-transform: capitalize; /* Capitalize words for elegance */
    letter-spacing: 0.5px; /* Subtle letter spacing */
    font-family: 'Poppins', Arial, sans-serif; /* Modern font */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15); /* Stronger shadow for depth */
    text-align: center; /* Center the text */
    width: fit-content; /* Adjust the width to fit the content */
    margin-left: auto; /* Center alignment trick */
    margin-right: auto; /* Center alignment trick */
    transition: all 0.3s ease; /* Smooth transition for hover effects */
    text-decoration: none; /* Remove underline */
}

/* Add Hover Effect for Interaction */
#monthlySection h4:hover, 
#renewalSection h4:hover, 
#coachingSection h4:hover, 
#lockerSection h4:hover {
    color: #71d4fc; /* Highlight the text color on hover */
    background: linear-gradient(to right, #f0f8ff, #e6f7ff); /* Light blue gradient */
    border-left: 5px solid #71d4fc; /* Darker blue border on hover */
    transform: translateY(-3px); /* Slight lift effect */
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2); /* Deeper shadow for lift */
    cursor: pointer; /* Change cursor to pointer */
}
/* General Section Styling */
#monthlySection, 
#renewalSection, 
#coachingSection, 
#lockerSection {
    background: #ffffff; /* White background */
    border: 1px solid #ddd; /* Light gray border */
    border-radius: 8px; /* Rounded corners */
    padding: 20px; /* Inner spacing */
    margin: 20px 0; /* Spacing between sections */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
    transition: all 0.3s ease; /* Smooth transitions */
}

/* Section Hover Effect */
#monthlySection:hover, 
#renewalSection:hover, 
#coachingSection:hover, 
#lockerSection:hover {
    transform: translateY(-5px); /* Slight lift on hover */
    box-shadow: 0 8px 12px rgba(0, 0, 0, 0.2); /* Deeper shadow */
    border: 1px solid #71d4fc; /* Highlight border on hover */
}

/* Styling for Form Groups */
.form-group {
    display: flex; /* Align items in a row */
    flex-wrap: wrap; /* Wrap content */
    gap: 15px; /* Spacing between fields */
    margin-bottom: 15px; /* Spacing between groups */
}

.form-group > div {
    flex: 1 1 45%; /* Adjust field width (responsive) */
    min-width: 250px; /* Minimum field width */
}

/* Labels Styling */
label {
    font-weight: 600; /* Make labels bold */
    margin-bottom: 5px; /* Space below labels */
    display: block; /* Labels appear on top */
    color: #333; /* Darker text color */
}

/* Input and Select Styling */
input[type="text"],
input[type="date"],
select {
    width: 100%;
    padding: 10px; /* Add inner spacing */
    font-size: 14px;
    border: 1px solid #ccc; /* Light border */
    border-radius: 5px; /* Rounded corners */
    background: #f9f9f9; /* Light gray background */
    color: #333; /* Text color */
    transition: border 0.3s ease, box-shadow 0.3s ease;
}

input:focus,
select:focus {
    border-color: #71d4fc; /* Highlight border on focus */
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.4); /* Add shadow on focus */
    outline: none; /* Remove default outline */
}

/* Styling for Multiple Select */
select[multiple] {
    height: 100px; /* Fixed height for multiple select */
    overflow-y: auto; /* Add scrollbar if needed */
    background: #f5faff; /* Slightly blue background */
}

/* Styling for Amount Input */
.amount-input {
    text-align: right; /* Right-align the text */
    font-weight: bold; /* Bold text */
}


#totalAmountSection {
    display: inline-flex; /* Align items horizontally without taking full width */
    align-items: center; /* Vertically center the content */
    gap: 5px; /* Small spacing between label and span */
    padding: 10px; /* Small padding inside the container */
    font-size: 18px; /* Adjust font size */
    border: 1px solid #ddd; /* Add a border for visibility */
    background-color: #fafafa; /* Light background color */
    border-radius: 3px; /* Slightly rounded corners */
    width: fit-content; /* Adjust container width to fit content only */
    box-sizing: border-box; /* Ensure padding doesn't affect width */
    margin-top: 10px;
}
/* Prevent total amount div from stretching */
.form-group #totalAmountSection {
    flex: 0 0 auto; /* Disable flex-grow and shrink */
    width: fit-content; /* Fit the container content */
}


#totalAmountSection label {
    margin: 0; /* Remove extra margin */
    font-weight: bold; /* Bold for emphasis */
    white-space: nowrap; /* Prevent text wrapping */
}

#totalamount {
    font-weight: bold; /* Bold for the span */
    color: #000; /* Optional blue color for emphasis */
}








    </style>

</head>
<ssc>
    <div class="container">
        <aside class="sidebar">
            <div class="admin-info">
                <img src="<?php echo htmlspecialchars($user_info['profile_picture'] ?? 'default-profile.png'); ?>" alt="Admin Avatar">
                <h3><?php echo htmlspecialchars($user_info['username']); ?></h3>
                <p><?php echo htmlspecialchars($user_info['email']); ?></p>
            </div>
            <nav class="menu">
                <ul>
                    <li ><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <?php if ($usertype === 'admin'): ?>
                        <li><a href="adminpage.php"><i class="fas fa-cogs"></i> Admin</a></li>
                        <li><a href="managestaff.php"><i class="fas fa-users"></i> Manage Staff</a></li>
                    <?php endif; ?>
                    <li><a href="registration.php"><i class="fas fa-clipboard-list"></i> Registration</a></li>
                    <li><a href="memberspage.php"><i class="fas fa-user-friends"></i> View Members</a></li>
                    <li ><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
                    <li class="active"><a href="payment.php"><i class="fas fa-credit-card"></i> Payment</a></li>
                    <li><a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                    <li><a href="pos.php"><i class="fas fa-money-bill"></i> Point of Sale</a></li>
                    <li><a href="coaches.php"><i class="fas fa-dumbbell"></i> Coaches</a></li>
                    <li><a href="walkins.php"><i class="fas fa-walking"></i> Walk-ins</a></li>
                    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Transaction Logs</a></li>
                    <li><a href="analytics.php"><i class="fas fa-chart-bar"></i>  Analytics</a></li>
                </ul>
                <div class="logout">
    <a href="#" onclick="showLogoutModal(); return true;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
<div class="modal-logouts" id="logoutModal">
    <div class="logouts-content">
        <i class="fas fa-sign-out-alt modal-icon"></i>
        <h3>Are you sure you want to log out?</h3>
        <div class="modal-buttons">
            <button class="confirms-buttons" onclick="handleLogout()">Yes, Log Out</button>
            <button class="cancels-buttons" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>
</aside>
</nav>

        <main class="main-content">
            <div class="header">
                <img src="/brew+flex/assets/brewlogo2.png" class="logo">
            </div>
            <div class="form-container">
                <h3>Add Payment <i class="fas fa-credit-card"></i></h3>
                <form action="payment.php" method="POST">
                    <!-- Member Information -->
                    <div class="form-group">
                        <div>
                            <label for="memberName">Full Name</label>
                            <select id="memberName" required>
                                <option value="" disabled selected>Select member</option>
                                <?php foreach ($members as $member): ?>
                                    <option value="<?php echo htmlspecialchars($member['member_id']); ?>">
                                        <?php echo htmlspecialchars($member['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="memberId">Member ID</label>
                            <input type="text" id="memberId" name="member_id" readonly required>
                        </div>
                    </div>



                    <!-- Payment Type and Validity Selection -->
                    <div class="form-group">
                        <div>
                            <label for="paymentType">Payment for</label>
                            <select id="paymentType" name="payment_type[]" multiple="multiple">
                                <option value="monthly">Monthly Plan Payment</option>
                                <option value="renewal">Membership Renewal Payment</option>
                                <option value="locker">Locker Payment</option>
                                <option value="coaching">Coaching Plan Payment</option>

                            </select>
                        </div>
                        <!-- Validity selections are hidden initially and shown based on type -->



                    </div>




                    <!--Monthly Plan Section -->
                    <div id="monthlySection" class="hidden">
                        <h4 class="payment-header">Monthly Plan</h4>
                        <div class="form-group">

                        <div id="monthlyPaymentDateContainer" class="hidden">
    <label for="monthlyPaymentDate">Date of Payment (Monthly)</label>
    <input type="datetime-local" id="monthlyPaymentDate" name="monthly_plan_payment_date">
</div>

                            <div id="monthlyExpiryContainer" class="hidden">
                                <label for="monthlyExpiryDate">Expiry Date (Monthly)</label>
                                <input type="date" id="monthlyExpiryDate" name="monthly_plan_expiration_date">
                            </div>

                            <div id="monthlyValidity" class="hidden">
                                <label for="monthlyValiditySelect">Monthly Validity</label>
                                <select id="monthlyValiditySelect" name="monthlyValidity">
                                    <option value="" disabled selected>Select validity</option>
                                </select>
                            </div>

                            <div id="monthlyamount" class="hidden">
                                <label for="monthlyamountInput">Monthly Amount</label>
                                <input type="text" id="monthlyamountInput" name="monthly_amount" class="amount-input" value="0">
                            </div>
                        </div>

                    </div>


                    <!--Membership Renewal Plan Section -->
                    <div id="renewalSection" class="hidden">
                        <h4 class="payment-header">Membership Plan</h4>
                        <div class="form-group">

                        <div id="renewalPaymentDateContainer" class="hidden">
    <label for="renewalPaymentDate">Date of Payment (Renewal)</label>
    <input type="datetime-local" id="renewalPaymentDate" name="membership_renewal_payment_date">
</div>

                            <div id="renewalExpiryContainer" class="hidden">
                                <label for="renewalExpiryDate">Expiry Date (Renewal)</label>
                                <input type="date" id="renewalExpiryDate" name="renewal_expiry_date">
                            </div>



                            <div id="renewalValidity" class="hidden">
                                <label for="renewalValiditySelect">Renewal Validity</label>
                                <select id="renewalValiditySelect" name="renewalValidity" >
                                    <option value="" disabled selected>Select validity</option>
                                </select>
                            </div>


                            <div id="renewalamount" class="hidden">
                                <label for="renewalamountInput">Renewal Amount</label>
                                <input type="text" id="renewalamountInput" name="renewal_amount" class="amount-input" value="0">
                            </div>


                        </div>

                    </div>



                    <!--Coaching Plan Section -->

                    <div id="coachingSection" class="hidden">
                        <h4 class="payment-header">Coaching Plan</h4>
                        <div class="form-group">


                        <div id="coachingPaymentDateContainer" class="hidden">
    <label for="coachingPaymentDate">Date of Payment (Coaching)</label>
    <input type="datetime-local" id="coachingPaymentDate" name="coaching_payment_date">
</div>



                            <div id="coachingValidity" class="hidden">
                                <label for="coachingValiditySelect">Coaching Validity</label>
                                <select id="coachingValiditySelect" name="coachingValidity" >
                                    <option value="" disabled selected>Select validity</option>
                                </select>
                            </div>



                            <div id="coachField" class="hidden">
                                <label for="coachName">Select Coach</label>
                                <select id="coachName" name="coach_id">
                                    <option value="" disabled selected>Select coach</option>
                                    <?php foreach ($coaches as $coach): ?>
                                        <option value="<?php echo htmlspecialchars($coach['coach_id']); ?>">
                                            <?php echo htmlspecialchars($coach['full_name'] . ' (' . $coach['expertise'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="coachingamount" class="hidden">
                                <label for="coachingamountInput">Coaching Amount</label>
                                <input type="text" id="coachingamountInput" name="coaching_amount" class="amount-input" value="0">
                            </div>


                        </div>
                    </div>


                    <!--Locker Plan Section -->
                    <div id="lockerSection" class="hidden">
                        <h4 class="payment-header">Locker Plan</h4>

                        <div class="form-group " id="dateFields">


                        <div id="lockerPaymentDateContainer" class="hidden">
    <label for="lockerPaymentDate">Date of Payment (Locker)</label>
    <input type="datetime-local" id="lockerPaymentDate" name="locker_payment_date" disabled>
</div>


                            <div id="lockerExpiryContainer" class="hidden">
                                <label for="lockerExpiryDate">Expiry Date (Locker)</label>
                                <input type="date" id="lockerExpiryDate" name="locker_expiration_date"  disabled>
                            </div>

                            <div id="lockerValidity" class="hidden">
                                <label for="lockerValiditySelect">Locker Validity</label>
                                <select id="lockerValiditySelect" name="lockerValidity"  >
                                    <option value="" disabled selected>Select validity</option>
                                </select>
                            </div>

                            <div id="lockeramount" class="hidden">
                                <label for="lockeramountInput">Locker Amount</label>
                                <input type="text" id="lockeramountInput" name="locker_amount" class="amount-input" value="0">
                            </div>


                        </div>
                    </div>

                    <!-- Total Amount Section -->

                    <div class="form-group">
    <div id="totalAmountSection" class="hidden">
        <label for="totalamount">Total Amount:</label>
        <span id="totalamount" class="totalamount-display">0</span>
        <input type="hidden" id="totalamountInput" name="total_amount">
    </div>
</div>
<style>
/* Confirmation Modal */
.payment-confirmation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none; /* Initially hidden */
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out; /* Animation for smooth appearance */
}

/* Modal Content */
.payment-modal-content {
    background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    width: 320px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.4s ease; /* Slide-in animation for the modal */
    position: relative;
}

.payment-modal-icon {
    font-size: 40px;
    color: #71d4fc;
    margin-bottom: 15px;
}

.payment-modal-title {
    margin-top: 0;
    color: #333;
    font-size: 1.5rem;
    font-weight: bold;
}

.payment-modal-message {
    color: #333;
    font-size: 1rem;
    margin-bottom: 20px;
}

/* Modal Buttons */
.payment-modal-actions {
    margin-top: 20px;
}

.payment-modal-actions button {
    margin: 10px 5px;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

/* Confirm Button */
.payment-modal-confirm-btn {
    background-color: #71d4fc;
    color: #ffffff;
}

.payment-modal-confirm-btn:hover {
    background-color: #71bce0;
    transform: scale(1.05);
}

/* Cancel Button */
.payment-modal-cancel-btn {
    background-color: #ccc;
    color: #333;
}

.payment-modal-cancel-btn:hover {
    background-color: #bbb;
    transform: scale(1.05);
}

/* Modal Animations */
@keyframes fadeIn {
    0% {
        opacity: 0;
    }
    100% {
        opacity: 1;
    }
}

@keyframes slideIn {
    0% {
        transform: translateY(-50px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}


</style>


                    <div class="form-buttons">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="submit" class="next-btn" onclick="return validateForm();">Save</button>
                       

                    </div>
                </form>
            </div>
<!-- Confirmation Modal -->
<div id="paymentConfirmationModal" class="payment-confirmation-modal">
    <div class="payment-modal-content">
    <i class="payment-modal-icon fas fa-question-circle"></i>
    <h3 class="payment-modal-title">Confirm Submission</h3>
        <p class="payment-modal-message">Are you sure you want to submit the payment details?</p>
        <div class="payment-modal-actions">
        <button class="payment-modal-confirm-btn" onclick="confirmFormSubmission()">Confirm</button>
        <button class="payment-modal-cancel-btn" onclick="closeConfirmationModal()">Cancel</button>
        </div>
    </div>
</div>


        </main>
     
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
        

        <script>
      document.addEventListener("DOMContentLoaded", function () {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset()); // Adjust for local time

        const formattedDateTime = now.toISOString().slice(0, 16); // Format for datetime-local

        // Set the default value for all datetime-local fields
        document.getElementById("monthlyPaymentDate").value = formattedDateTime;
        document.getElementById("renewalPaymentDate").value = formattedDateTime;
        document.getElementById("coachingPaymentDate").value = formattedDateTime;
        document.getElementById("lockerPaymentDate").value = formattedDateTime;

        // Function to close the date picker after selecting a date
        function closeDatePicker(event) {
            setTimeout(() => {
                event.target.blur(); // Close the date picker after a short delay
            }, 100); // Small delay ensures the selection registers before closing
        }

        // Attach event listeners to all datetime-local fields
        document.getElementById("monthlyPaymentDate").addEventListener("input", closeDatePicker);
        document.getElementById("renewalPaymentDate").addEventListener("input", closeDatePicker);
        document.getElementById("coachingPaymentDate").addEventListener("input", closeDatePicker);
        document.getElementById("lockerPaymentDate").addEventListener("input", closeDatePicker);
    });



function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function handleLogout() {
    alert("You have been logged out.");
    window.location.href = "/brew+flex/logout.php";
}


// JavaScript to handle the Member Full Name and Member ID
document.getElementById('memberName').addEventListener('change', function () {
    const selectedMemberId = this.value;
    document.getElementById('memberId').value = selectedMemberId;
});

function openConfirmationModal() {
    $('#paymentConfirmationModal').css('display', 'flex').show(); // Instantly show the modal with flex alignment
}

function closeConfirmationModal() {
    $('#paymentConfirmationModal').hide(); // Instantly hide the modal
}

function confirmFormSubmission() {
    closeConfirmationModal();
    $('form').off('submit', preventFormSubmission); // Allow form submission
    $('form').submit(); // Submit the form programmatically
}

function preventFormSubmission(event) {
    event.preventDefault(); // Prevent default submission
    openConfirmationModal(); // Show confirmation modal
}

// Attach the confirmation modal logic to form submission
$('form').on('submit', preventFormSubmission);





$(document).ready(function () {
    const validityData = {
        monthly: {
            validity: [
                { value: 1, text: '1 month', amount: 700 },
                { value: 2, text: '2 months', amount: 800 },
                { value: 3, text: '3 months', amount: 900 },
                { value: 4, text: '4 months', amount: 1000 },
                { value: 5, text: '5 months', amount: 1100 },
                { value: 6, text: '6 months', amount: 1200 },
                { value: 7, text: '7 months', amount: 1300 },
                { value: 8, text: '8 months', amount: 1400 },
                { value: 9, text: '9 months', amount: 1500 },
                { value: 10, text: '10 months', amount: 1600 },
                { value: 11, text: '11 months', amount: 1700 },
                { value: 12, text: '12 months', amount: 1800 },
            ],
        },
        renewal: {
            validity: [
                { value: 1, text: '1 year', amount: 1600 },
                { value: 2, text: '2 years', amount: 1700 },
                { value: 3, text: '3 years', amount: 1800 },
            ],
        },
        locker: {
            validity: [
                { value: 1, text: '1 month', amount: 700 },
                { value: 2, text: '2 months', amount: 800 },
                { value: 3, text: '3 months', amount: 900 },
                { value: 4, text: '4 months', amount: 1000 },
                { value: 5, text: '5 months', amount: 1100 },
                { value: 6, text: '6 months', amount: 1200 },
                { value: 7, text: '7 months', amount: 1300 },
                { value: 8, text: '8 months', amount: 1400 },
                { value: 9, text: '9 months', amount: 1500 },
                { value: 10, text: '10 months', amount: 1600 },
                { value: 11, text: '11 months', amount: 1700 },
                { value: 12, text: '12 months', amount: 1800 },
            ],
        },
        coaching: {
            validity: [
                { value: 1, text: '1 session', amount: 1600 },
                { value: 8, text: '8 sessions', amount: 1700 },
                { value: 12, text: '12 sessions', amount: 1800 },
            ],
        },
    };

    $('#paymentType').select2({
        placeholder: "Select payment type",
        allowClear: true,
    });

   

    function hideAllSections() {
        $('.payment-section').each(function () {
            const section = $(this);
            section.addClass('hidden')
                .find(':input')
                .each(function () {
                    $(this).prop('disabled', true).removeAttr('required');
                });
        });
        $('#totalAmountSection').addClass('hidden');
    }

    function showSection(selector) {
        $(selector).removeClass('hidden')
            .find(':input')
            .each(function () {
                $(this).prop('disabled', false).attr('required', true);
            });
    }

    
// Show and hide sections based on selection
$('#paymentType').on('select2:select', function (e) {
    const selectedValue = e.params.data.id;
    if (selectedValue === 'monthly') {
        $('#monthlySection').removeClass('hidden');
    } else if (selectedValue === 'renewal') {
        $('#renewalSection').removeClass('hidden');
    } else if (selectedValue === 'locker') {
        $('#lockerSection').removeClass('hidden');
    } else if (selectedValue === 'coaching') {
        $('#coachingSection').removeClass('hidden');
    }
});

$('#paymentType').on('select2:unselect', function (e) {
    const removedValue = e.params.data.id;
    if (removedValue === 'monthly') {
        $('#monthlySection').addClass('hidden');
    } else if (removedValue === 'renewal') {
        $('#renewalSection').addClass('hidden');
    } else if (removedValue === 'locker') {
        $('#lockerSection').addClass('hidden');
    } else if (removedValue === 'coaching') {
        $('#coachingSection').addClass('hidden');
    }
});





    function populateValidityOptions(type) {
        const options = validityData[type].validity;
        const select = $('#' + type + 'ValiditySelect');
        select.empty().append('<option value="" disabled selected>Select validity</option>');
        options.forEach((option) => {
            select.append($('<option>').val(option.value).text(option.text).data('amount', option.amount));
        });
        select.prop('disabled', false);
    }
    

    function calculateExpiryDate(validity, type) {
        const today = new Date();
        let expiryDate;
        if (type === 'renewal' || type === 'coaching') {
            expiryDate = new Date(today.setFullYear(today.getFullYear() + validity));
        } else {
            expiryDate = new Date(today.setMonth(today.getMonth() + validity));
        }
        return expiryDate.toISOString().split('T')[0];
    }

    function updateTotalAmount() {
        let total = 0;
        $('.amount-input:visible').each(function () {
            total += parseInt($(this).val()) || 0;
        });
        $('#totalamount').text(total);
        $('#totalamountInput').val(total);
    }

   window.validateForm = function () {
    const selectedOptions = $('#paymentType').val(); // Get selected payment types

    if (!selectedOptions || selectedOptions.length === 0) {
        alert("Please select at least one payment type.");
        $('#paymentType').focus();
        return false;
    }

    let isValid = true;

    // Validate all visible required fields
    $(':input:visible').each(function () {
        if ($(this).prop('required') && !$(this).val()) {
            isValid = false;
            alert(`Please fill out the ${$(this).attr('name')} field.`);
            $(this).focus();
            return false; // Break loop on first error
        }
    });

    return isValid;
};

    $('#paymentType').on('change', function () {
        hideAllSections();
        const selectedOptions = $(this).val() || [];

        selectedOptions.forEach((option) => {
            showSection('#' + option + 'Section');
            showSection('#' + option + 'PaymentDateContainer');
            showSection('#' + option + 'ExpiryContainer');
            showSection('#' + option + 'Validity');
            showSection('#' + option + 'amount');

            if (option === 'coaching') {
                showSection('#coachField');
            }

        
            populateValidityOptions(option);

            const today = new Date().toISOString().split('T')[0];
            $('#' + option + 'PaymentDate').val(today).prop('disabled', false);
            $('#' + option + 'ExpiryDate').prop('disabled', false);

        });

        if (selectedOptions.length > 0) {
            $('#totalAmountSection').removeClass('hidden');
        }
    });



    $('select[id$="ValiditySelect"]').on('change', function () {
        const selectedOption = $(this).find('option:selected');
        if (selectedOption.val()) {
            const amount = selectedOption.data('amount');
            const validity = parseInt(selectedOption.val());
            const type = this.id.replace('ValiditySelect', '');
            const expiryDate = calculateExpiryDate(validity, type);
                    // $('#lockerExpiryDate').val(expiryDate).prop('disabled', false); // Ensure enabled


            $('#' + type + 'ExpiryDate').val(expiryDate);
            $('#' + type + 'amountInput').val(amount).trigger('change');
        }
    });

    $(document).on('change', '.amount-input', function () {
        updateTotalAmount();
    });
    

   



 // Form submit logic
    $('form').on('submit', function (event) {
    const selectedOptions = $('#paymentType').val() || [];


    // Ensure only fields in the selected sections are enabled
    ['monthly', 'renewal', 'locker', 'coaching'].forEach((option) => {
        if (!selectedOptions.includes(option)) {
            $(`#${option}Section :input`).prop('disabled', true).removeAttr('required');

        } else {
            $(`#${option}Section :input`).prop('disabled', false).attr('required', true);
        }
    });

   

console.log($('#lockerExpiryDate').val());


    // Perform validation
    if (!window.validateForm()) {
        event.preventDefault();
    }

    
});





});




</script>







</html>