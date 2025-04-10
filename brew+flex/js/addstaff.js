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

function previewProfilePicture(event) {
    const file = event.target.files[0];
    const reader = new FileReader();

    reader.onload = function () {
        const preview = document.getElementById('profilePicturePreview');
        preview.src = reader.result;
    };

    if (file) {
        reader.readAsDataURL(file);
    }
}



const style = document.createElement('style');
style.innerHTML = `
    .loader {
        display: inline-block;
        margin-left: 10px;
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-top: 2px solid #009acd;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

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
    const webcam = document.getElementById('webcam');

    // Ensure the modal is hidden initially
    if (webcamModal.style.display !== 'none') return;

    webcamModal.style.display = 'flex';

    navigator.mediaDevices.getUserMedia({ video: true })
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
    const webcam = document.getElementById('webcam');

    if (webcamModal) {
        webcamModal.style.display = 'none';
    }

    if (webcam && webcam.srcObject) {
        webcam.srcObject.getTracks().forEach(track => track.stop());
        webcam.srcObject = null;
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

// Ensure webcam modal is hidden and prevent it from flashing on reload
document.addEventListener('DOMContentLoaded', () => {
    const webcamModal = document.getElementById('webcamModal');
    const webcam = document.getElementById('webcam');

    // Explicitly hide the modal immediately
    webcamModal.style.display = 'none';

    // Reset webcam stream if necessary
    if (webcam && webcam.srcObject) {
        webcam.srcObject.getTracks().forEach(track => track.stop());
        webcam.srcObject = null;
    }

    // Reset profile picture preview to default if no value exists
    const preview = document.getElementById('profilePicturePreview');
    if (!preview.src || preview.src === window.location.href) {
        preview.src = 'default-profile.png';
    }

    // Add form validation to require profile picture upload
    const form = document.getElementById('addStaffForm');
    form.addEventListener('submit', (event) => {
        const fileInput = document.getElementById('profile_picture');
        const webcamInput = document.getElementById('webcamImage');
        const errorMessage = document.getElementById('profileError');

        // Check if both fields are empty
        if (!fileInput.files.length && !webcamInput.value) {
            event.preventDefault();

            if (!errorMessage) {
                const error = document.createElement('div');
                error.id = 'profileError';
                error.style.color = 'red';
                error.style.marginTop = '10px';
                error.textContent = 'Please upload a picture or capture one using the webcam.';
                fileInput.closest('.profile-upload').appendChild(error);
            }
        } else if (errorMessage) {
            errorMessage.remove();
        }
    });
});
