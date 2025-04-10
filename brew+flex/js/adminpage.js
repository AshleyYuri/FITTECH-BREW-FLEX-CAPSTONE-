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

      // Function to preview the profile picture
      function previewProfilePicture(event) {
        const file = event.target.files[0]; // Get the selected file
    
        if (file) {
            const reader = new FileReader();
    
            // Once the file is loaded, update the preview image
            reader.onload = function(e) {
                const previewImage = document.getElementById('profile_picture_preview');
                previewImage.src = e.target.result; // Set the src of the preview image
            }
    
            reader.readAsDataURL(file); // Read the file as a Data URL
        }
    }
    
       // Function to confirm actions for Update Info and Update Password
    function confirmAction(actionType) {
        let confirmationMessage = "";
    
        // Decide what confirmation message to show based on the action type
        if (actionType === "update_info") {
            confirmationMessage = "Are you sure you want to update your admin information?";
        } else if (actionType === "update_password") {
            confirmationMessage = "Are you sure you want to change the password?";
        }
    
        // Show the confirmation prompt
        return confirm(confirmationMessage); // Returns true if the user clicks 'OK', false otherwise
    }
    
    // Adding validation and confirmation for the Update Info form
    document.querySelector("form.edit-form").onsubmit = function (event) {
        // Check for form validity first
        if (!this.checkValidity()) {
            this.reportValidity(); // Trigger browser's "Please fill out this field" message
            event.preventDefault(); // Prevent the form from submitting
        } else {
            // If the form is valid, confirm the action
            if (!confirmAction("update_info")) {
                event.preventDefault(); // Prevent form submission if user cancels the confirmation
            }
        }
    };
    
    // Adding validation and confirmation for the Change Password form
    document.querySelector("form:not(.edit-form)").onsubmit = function (event) {
        // Check for form validity first
        if (!this.checkValidity()) {
            this.reportValidity(); // Trigger browser's "Please fill out this field" message
            event.preventDefault(); // Prevent the form from submitting
        } else {
            // If the form is valid, confirm the action
            if (!confirmAction("update_password")) {
                event.preventDefault(); // Prevent form submission if user cancels the confirmation
            }
        }
    };
    