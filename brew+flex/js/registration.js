// Preview uploaded image
function previewProfilePicture(event) {
    const file = event.target.files[0];
    const reader = new FileReader();

    // Reset webcam image value on file upload
    document.getElementById('webcamImage').value = '';

    reader.onload = function() {
        const preview = document.getElementById('profilePicturePreview');
        preview.src = reader.result;
        preview.style.display = 'block'; // Show the image preview
    };

    if (file) {
        reader.readAsDataURL(file); // Read the image file
    }
}
// Webcam functionality
let webcamStream = null;

function startWebcam() {
    const webcamModal = document.getElementById('webcamModal');
    webcamModal.style.display = 'flex';

    const webcam = document.getElementById('webcam');
    navigator.mediaDevices.getUserMedia({
            video: true
        })
        .then((stream) => {
            webcamStream = stream;
            webcam.srcObject = stream;
        })
        .catch((err) => {
            alert('Unable to access webcam: ' + err.message);
        });
}

function closeWebcamModal() {
    const webcamModal = document.getElementById('webcamModal');
    webcamModal.style.display = 'none';
    if (webcamStream) {
        webcamStream.getTracks().forEach(track => track.stop());
        webcamStream = null;
    }
}

function captureWebcamPicture() {
    const beepSound = document.getElementById('beepSound');
    beepSound.play().then(() => {
        console.log('Beep sound played successfully');
    }).catch(error => {
        console.error('Error playing beep sound:', error);
    });

    const webcam = document.getElementById('webcam');
    const canvas = document.getElementById('webcamCanvas');
    const hiddenInput = document.getElementById('webcamImage');

    canvas.width = webcam.videoWidth;
    canvas.height = webcam.videoHeight;

    const context = canvas.getContext('2d');
    context.drawImage(webcam, 0, 0, canvas.width, canvas.height);

    // Set the hidden input value to the captured webcam image
    hiddenInput.value = canvas.toDataURL('image/png');

    // Update the profile picture preview
    document.getElementById('profilePicturePreview').src = hiddenInput.value;

    // Close the webcam modal after capturing the picture
    closeWebcamModal();
}

        // Populate the country select dropdown
        const countries = [
            { name: "Afghanistan", code: "AF" }, { name: "Albania", code: "AL" }, { name: "Algeria", code: "DZ" }, 
            { name: "Andorra", code: "AD" }, { name: "Angola", code: "AO" }, { name: "Antigua and Barbuda", code: "AG" }, 
            { name: "Argentina", code: "AR" }, { name: "Armenia", code: "AM" }, { name: "Australia", code: "AU" },
            { name: "Austria", code: "AT" }, { name: "Azerbaijan", code: "AZ" }, { name: "Bahamas", code: "BS" }, 
            { name: "Bahrain", code: "BH" }, { name: "Bangladesh", code: "BD" }, { name: "Barbados", code: "BB" }, 
            { name: "Belarus", code: "BY" }, { name: "Belgium", code: "BE" }, { name: "Belize", code: "BZ" },
            { name: "Benin", code: "BJ" }, { name: "Bhutan", code: "BT" }, { name: "Bolivia", code: "BO" }, 
            { name: "Bosnia and Herzegovina", code: "BA" }, { name: "Botswana", code: "BW" }, { name: "Brazil", code: "BR" },
            { name: "Brunei", code: "BN" }, { name: "Bulgaria", code: "BG" }, { name: "Burkina Faso", code: "BF" }, 
            { name: "Burundi", code: "BI" }, { name: "Cabo Verde", code: "CV" }, { name: "Cambodia", code: "KH" },
            { name: "Cameroon", code: "CM" }, { name: "Canada", code: "CA" }, { name: "Central African Republic", code: "CF" },
            { name: "Chad", code: "TD" }, { name: "Chile", code: "CL" }, { name: "China", code: "CN" },
            { name: "Colombia", code: "CO" }, { name: "Comoros", code: "KM" }, { name: "Congo (Congo-Brazzaville)", code: "CG" },
            { name: "Costa Rica", code: "CR" }, { name: "Croatia", code: "HR" }, { name: "Cuba", code: "CU" },
            { name: "Cyprus", code: "CY" }, { name: "Czechia (Czech Republic)", code: "CZ" }, { name: "Denmark", code: "DK" },
            { name: "Djibouti", code: "DJ" }, { name: "Dominica", code: "DM" }, { name: "Dominican Republic", code: "DO" },
            { name: "Ecuador", code: "EC" }, { name: "Egypt", code: "EG" }, { name: "El Salvador", code: "SV" },
            { name: "Equatorial Guinea", code: "GQ" }, { name: "Eritrea", code: "ER" }, { name: "Estonia", code: "EE" },
            { name: "Eswatini", code: "SZ" }, { name: "Ethiopia", code: "ET" }, { name: "Fiji", code: "FJ" },
            { name: "Finland", code: "FI" }, { name: "France", code: "FR" }, { name: "Gabon", code: "GA" },
            { name: "Gambia", code: "GM" }, { name: "Georgia", code: "GE" }, { name: "Germany", code: "DE" },
            { name: "Ghana", code: "GH" }, { name: "Greece", code: "GR" }, { name: "Grenada", code: "GD" },
            { name: "Guatemala", code: "GT" }, { name: "Guinea", code: "GN" }, { name: "Guinea-Bissau", code: "GW" },
            { name: "Guyana", code: "GY" }, { name: "Haiti", code: "HT" }, { name: "Honduras", code: "HN" },
            { name: "Hungary", code: "HU" }, { name: "Iceland", code: "IS" }, { name: "India", code: "IN" },
            { name: "Indonesia", code: "ID" }, { name: "Iran", code: "IR" }, { name: "Iraq", code: "IQ" },
            { name: "Ireland", code: "IE" }, { name: "Israel", code: "IL" }, { name: "Italy", code: "IT" },
            { name: "Jamaica", code: "JM" }, { name: "Japan", code: "JP" }, { name: "Jordan", code: "JO" },
            { name: "Kazakhstan", code: "KZ" }, { name: "Kenya", code: "KE" }, { name: "Kiribati", code: "KI" },
            { name: "Kuwait", code: "KW" }, { name: "Kyrgyzstan", code: "KG" }, { name: "Laos", code: "LA" },
            { name: "Latvia", code: "LV" }, { name: "Lebanon", code: "LB" }, { name: "Lesotho", code: "LS" },
            { name: "Liberia", code: "LR" }, { name: "Libya", code: "LY" }, { name: "Liechtenstein", code: "LI" },
            { name: "Lithuania", code: "LT" }, { name: "Luxembourg", code: "LU" }, { name: "Madagascar", code: "MG" },
            { name: "Malawi", code: "MW" }, { name: "Malaysia", code: "MY" }, { name: "Maldives", code: "MV" },
            { name: "Mali", code: "ML" }, { name: "Malta", code: "MT" }, { name: "Marshall Islands", code: "MH" },
            { name: "Mauritania", code: "MR" }, { name: "Mauritius", code: "MU" }, { name: "Mexico", code: "MX" },
            { name: "Micronesia", code: "FM" }, { name: "Moldova", code: "MD" }, { name: "Monaco", code: "MC" },
            { name: "Mongolia", code: "MN" }, { name: "Montenegro", code: "ME" }, { name: "Morocco", code: "MA" },
            { name: "Mozambique", code: "MZ" }, { name: "Myanmar (Burma)", code: "MM" }, { name: "Namibia", code: "NA" },
            { name: "Nauru", code: "NR" }, { name: "Nepal", code: "NP" }, { name: "Netherlands", code: "NL" },
            { name: "New Zealand", code: "NZ" }, { name: "Nicaragua", code: "NI" }, { name: "Niger", code: "NE" },
            { name: "Nigeria", code: "NG" }, { name: "North Korea", code: "KP" }, { name: "North Macedonia", code: "MK" },
            { name: "Norway", code: "NO" }, { name: "Oman", code: "OM" }, { name: "Pakistan", code: "PK" },
            { name: "Palau", code: "PW" }, { name: "Panama", code: "PA" }, { name: "Papua New Guinea", code: "PG" },
            { name: "Paraguay", code: "PY" }, { name: "Peru", code: "PE" }, { name: "Philippines", code: "PH" },
            { name: "Poland", code: "PL" }, { name: "Portugal", code: "PT" }, { name: "Qatar", code: "QA" },
            { name: "Romania", code: "RO" }, { name: "Russia", code: "RU" }, { name: "Rwanda", code: "RW" },
            { name: "Saint Kitts and Nevis", code: "KN" }, { name: "Saint Lucia", code: "LC" }, { name: "Saint Vincent and the Grenadines", code: "VC" },
            { name: "Samoa", code: "WS" }, { name: "San Marino", code: "SM" }, { name: "Sao Tome and Principe", code: "ST" },
            { name: "Saudi Arabia", code: "SA" }, { name: "Senegal", code: "SN" }, { name: "Serbia", code: "RS" },
            { name: "Seychelles", code: "SC" }, { name: "Sierra Leone", code: "SL" }, { name: "Singapore", code: "SG" },
            { name: "Slovakia", code: "SK" }, { name: "Slovenia", code: "SI" }, { name: "Solomon Islands", code: "SB" },
            { name: "Somalia", code: "SO" }, { name: "South Africa", code: "ZA" }, { name: "South Korea", code: "KR" },
            { name: "South Sudan", code: "SS" }, { name: "Spain", code: "ES" }, { name: "Sri Lanka", code: "LK" },
            { name: "Sudan", code: "SD" }, { name: "Suriname", code: "SR" }, { name: "Sweden", code: "SE" },
            { name: "Switzerland", code: "CH" }, { name: "Syria", code: "SY" }, { name: "Taiwan", code: "TW" },
            { name: "Tajikistan", code: "TJ" }, { name: "Tanzania", code: "TZ" }, { name: "Thailand", code: "TH" },
            { name: "Timor-Leste", code: "TL" }, { name: "Togo", code: "TG" }, { name: "Tonga", code: "TO" },
            { name: "Trinidad and Tobago", code: "TT" }, { name: "Tunisia", code: "TN" }, { name: "Turkey", code: "TR" },
            { name: "Turkmenistan", code: "TM" }, { name: "Tuvalu", code: "TV" }, { name: "Uganda", code: "UG" },
            { name: "Ukraine", code: "UA" }, { name: "United Arab Emirates", code: "AE" }, { name: "United Kingdom", code: "GB" },
            { name: "United States of America", code: "US" }, { name: "Uruguay", code: "UY" }, { name: "Uzbekistan", code: "UZ" },
            { name: "Vanuatu", code: "VU" }, { name: "Vatican City", code: "VA" }, { name: "Venezuela", code: "VE" },
            { name: "Vietnam", code: "VN" }, { name: "Yemen", code: "YE" }, { name: "Zambia", code: "ZM" },
            { name: "Zimbabwe", code: "ZW" }
          ];
          

          const countrySelect = document.getElementById("country");

          countries.forEach(country => {
            const option = document.createElement("option");
            option.value = country.name;
      
            // Create the flag icon and country name
            const flagSpan = document.createElement("span");
            flagSpan.classList.add("flag-icon", "flag-icon-" + country.code.toLowerCase());
            flagSpan.style.marginRight = "10px";  // Add space between flag and country name
      
            option.textContent = country.name;
            option.insertBefore(flagSpan, option.firstChild);  // Insert the flag before the country name
      
            countrySelect.appendChild(option);
          });

        function goToNextScreen(currentScreen, nextScreen) {
            const current = document.getElementById(`screen${currentScreen}`);
            const next = document.getElementById(`screen${nextScreen}`);

            // Select only the inputs within the current screen for validation
            const inputs = current.querySelectorAll("input, select, textarea");

            let isValid = true;

            // Check each input's validity within the current screen
            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    isValid = false;
                    input.reportValidity(); // Show validation message for the first invalid input
                    return; // Exit loop on first invalid input
                }
            });

            // If all inputs on current screen are valid, proceed to the next screen
            if (isValid) {
                current.classList.remove('active');
                next.classList.add('active');
            }
        }

        function goToPreviousScreen(currentScreen, previousScreen) {
            const current = document.getElementById(`screen${currentScreen}`);
            const previous = document.getElementById(`screen${previousScreen}`);

            // Remove the active class from the current screen and add it back to the previous screen
            current.classList.remove('active');
            previous.classList.add('active');
        }

       
document.addEventListener('DOMContentLoaded', function () {
    const enrolledDateInput = document.getElementById('MembershipEnrolleddateInput');
    const amountElement = document.getElementById('amount');
    const hiddenAmountInput = document.getElementById('hiddenAmount'); // Hidden input to store the actual amount

    // Get today's date in the Philippines timezone
    const philippinesTime = new Date(); // Use the browser's local time zone, assuming it's already set to Asia/Manila

    // Extract the date components and format to YYYY-MM-DD
    const year = philippinesTime.getFullYear();
    const month = (philippinesTime.getMonth() + 1).toString().padStart(2, '0');
    const day = philippinesTime.getDate().toString().padStart(2, '0');
    const formattedToday = `${year}-${month}-${day}`;

    // Set the input value and lock it to today's date
    enrolledDateInput.value = formattedToday;
    enrolledDateInput.setAttribute('min', formattedToday);
    enrolledDateInput.setAttribute('max', formattedToday);
    enrolledDateInput.setAttribute('readonly', true); // Make the input field readonly to prevent manual edits

    // Calculate expiry date: 1 year after today's date
    const expiryDate = new Date(philippinesTime);
    expiryDate.setFullYear(philippinesTime.getFullYear() + 1);

    // Extract the expiry date components and format to YYYY-MM-DD
    const expiryYear = expiryDate.getFullYear();
    const expiryMonth = (expiryDate.getMonth() + 1).toString().padStart(2, '0');
    const expiryDay = expiryDate.getDate().toString().padStart(2, '0');

    // Set the expiration date in the input field
    document.getElementById('MemberExpiryContainerInput').value = `${expiryYear}-${expiryMonth}-${expiryDay}`;
    // Show the amount (assuming you want to display it for 1-year membership)
    amountElement.textContent = '300'; // You can change the value as needed
    amountElement.style.display = 'block';

    // Update hiddenAmount to 300
    hiddenAmountInput.value = '300';

    // Make the amount editable
    amountElement.setAttribute('contenteditable', true);

    // Capture manual changes to the amount
    amountElement.addEventListener('input', function() {
        const editedAmount = parseFloat(this.textContent);
        if (!isNaN(editedAmount) && editedAmount > 0) {
            // If valid amount, save the edited amount to the hidden input
            hiddenAmountInput.value = editedAmount;
        } else {
            // If invalid, reset to default amount (300)
            hiddenAmountInput.value = '300';
        }
    });
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


            