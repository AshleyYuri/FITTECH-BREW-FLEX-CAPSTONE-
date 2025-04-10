<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION["username"])) {
    header("Location: /brew+flex/auth/login.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

if (!isset($_GET['qr_code']) || !isset($_GET['member_id']) || !isset($_GET['name'])) {
    echo "Invalid access. Missing data.";
    exit;
}

$member_id = htmlspecialchars($_GET['member_id']);
$name = htmlspecialchars($_GET['name']);
$qr_code = htmlspecialchars($_GET['qr_code']);

$generated_code = isset($_GET['generated_code']) ? htmlspecialchars($_GET['generated_code']) : '';

$sql = "SELECT generated_code FROM members WHERE member_id = :member_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($generated_code) && $result) {
    $generated_code = $result['generated_code'];
}

$qr_data = array(
    'member_id' => $member_id,
    'name' => $name,
    'generated_code' => $generated_code
);

$qr_data_json = json_encode($qr_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brew+Flex Gym - Membership ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/brew+flex/css/membersuccessadded.css">
    <!-- Add this in the <head> section of your HTML to import Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<!-- Add this in the <head> section of your HTML to import Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap" rel="stylesheet">


    <style>
/* General reset */
body {
    font-family: 'Poppins', sans-serif; /* Modern sans-serif font */
    background-color: #f4f7fb;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
}

.id-card {
    width: 350px;
    height: 520px;
    background: linear-gradient(135deg,rgb(202, 218, 224), #71d4fc); /* Modern gradient background */
    color: #fff; /* Light text for better contrast */
    text-align: center;
    padding: 25px;
    border-radius: 20px; /* Rounded corners for a sleek, modern look */
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2); /* Deep shadow for depth */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.gym-name {
    font-family: 'Montserrat', sans-serif; /* Sleek and modern sans-serif font */
    font-size: 36px; /* Larger font size for sleekness and impact */
    font-weight: 700; 
    color: rgba(9, 9, 9, 0.8); /* Light footer text */
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 2px; /* Subtle letter-spacing for a more refined feel */
    text-align: center;
    line-height: 1.2; /* Slightly tighter line-height for a more compact look */
}



.gym-logo {
    width: 80px;
    height: 80px;
    margin-bottom: 20px;
    border-radius: 50%; /* Circular logo for sleekness */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.id-card h2 {
    font-size: 22px;
    color: #fff;
    font-weight: 600;
    margin: 10px 0;
}

.id-card img {
    width: 160px;
    height: 160px;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); /* Stronger shadow for QR code */
    margin-top: 10px;
}

.id-footer {
    font-size: 16px;
    color: rgba(9, 9, 9, 0.8); /* Light footer text */
   
    margin-top: 8px;
   
    
}

.qr-action-buttons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 25px;
    margin-bottom: 20px;
}

.qr-btn, .home-btn {
    background: #00bcd4;
    border: none;
    padding: 12px 28px;
    color: white;
    font-size: 14px;
    border-radius: 40px; /* Even more rounded buttons */
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.qr-btn:hover, .home-btn:hover {
    background: #71d4fc;
}

.qr-btn:focus, .home-btn:focus {
    outline: none;
}

.home-btn {
    margin-top: 15px;
    background: #0077b6;
}

/* Ensuring the ID card is centered in the viewport */
.container {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100vh;
}

.main-content {
    width: 100%;
    text-align: center;
}

.message-box {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100%;
}




    </style>
</head>
<body>
    <div class="container">
        <main class="main-content">
            <div class="message-box">
                <div class="message">
                <div class="id-card" id="idCard">
        <!-- Gym Name in bold at the top -->
        <div class="gym-name">BREW+FLEX GYM</div>

        

<!-- Gym Logo centered and above name -->
<img class="gym-logo" src="/brew+flex/assets/brewlogo1.png" alt="Brew+Flex Gym Logo">
      

        <!-- QR code below the name -->
        <img class="qr-code" id="qrCodeImage" src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?php echo urlencode($qr_data_json); ?>" alt="QR Code for <?php echo $name; ?>">


        <!-- Footer with Member ID -->
        <div class="id-footer">Name: <?php echo $name; ?></div>
        <div class="id-footer">Member ID: <?php echo $member_id; ?></div>
        


   
</div>


                    
                    <div class="qr-action-buttons">
                        <button onclick="printQRCode()" class="qr-btn">Print ID</button>
                        <button onclick="downloadQRCode()" class="qr-btn">Download ID</button>
                    </div>
                    <button class="home-btn" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-home"></i> Home
                    </button>
                </div>
            </div>
        </main>
    </div>
    <script>
        
  function printQRCode() {
    const idCard = document.getElementById("idCard").outerHTML;
    const printWindow = window.open('', '', 'width=350,height=500');  // Set a fixed size for the print window

    // Write the HTML and include necessary styles
    printWindow.document.write(`
        <html>
            <head>
                <title>Print ID Card</title>
                <style>
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        background-color: #1a1a1a;
                    }
                    .id-card {
                        width: 350px;
                        height: 500px;
                        background: linear-gradient(135deg, #1a1a1a, #444);
                        color: white;
                        text-align: center;
                        padding: 20px;
                        border-radius: 15px;
                        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: space-between;
                        margin: 50px auto;
                    }
                    .id-card img {
                        width: 160px;
                        height: 160px;
                        margin-bottom: 20px;
                    }
                </style>
            </head>
            <body>
                ${idCard}
            </body>
        </html>
    `);
    
    printWindow.document.close(); // Close the document to start rendering
    
    // Once the content is loaded, print it
    printWindow.onload = function() {
        printWindow.print();
        printWindow.close();
    };
}

function downloadQRCode() {
    const idCard = document.getElementById("idCard");

    html2canvas(idCard, {
        useCORS: true, // Enable CORS to load external resources (like images)
        logging: true, // Enable logging to help debug issues
    }).then(canvas => {
        // Create a link element to trigger the download
        const link = document.createElement("a");
        link.href = canvas.toDataURL("image/png");  // Convert canvas to image URL
        link.download = "BrewFlex_ID.png";  // Set the download filename
        link.click();  // Simulate click to trigger the download
    }).catch(error => {
        console.error("Error generating the image: ", error);
    });
}

</script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</body>
</html>
