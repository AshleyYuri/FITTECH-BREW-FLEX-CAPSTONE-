document.getElementById('editCoachForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    showUpdateConfirmationDialog(); // Display the confirmation dialog
});

// Function to handle form submission after confirmation
async function submitForm() {
    const form = document.getElementById('editCoachForm');
    const formData = new FormData(form);
    const updateButton = document.querySelector('.next-btn');



    try {
        // Send the form data to the server
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();

        if (result.status === 'success') {
            // Show success message modal
            showSuccessMessage(result.message, () => {
                window.location.href = 'coaches.php'; // Redirect after showing the message
            });
        } else {
            // Re-enable the update button if there's an error
            showErrorMessage(result.message);
            updateButton.disabled = false;
            updateButton.innerHTML = 'Update';
        }
    } catch (error) {
        // Handle fetch errors
        showErrorMessage('An error occurred. Please try again.');
        updateButton.disabled = false;
        updateButton.innerHTML = 'Update';
    }
}

// Function to show the confirmation dialog
function showUpdateConfirmationDialog() {
    // Create the modal dynamically
    const dialog = document.createElement('div');
    dialog.classList.add('update-confirmation-dialog');
    dialog.style.display = 'flex'; // Make modal visible
    dialog.innerHTML = `
        <div class="dialog-content">
            <i class="fas fa-question-circle modal-icon"></i> <!-- Icon for confirmation -->
            <h3>Confirm Update</h3>
            <p>Are you sure you want to update this coach's information?</p>
            <div class="dialog-buttons">
                <button id="confirmUpdate" class="confirm-btn">Yes, Update</button>
                <button id="cancelUpdate" class="cancel-btn">Cancel</button>
            </div>
        </div>
    `;
    document.body.appendChild(dialog);

    // Handle confirmation button click
    document.getElementById('confirmUpdate').addEventListener('click', () => {
        dialog.remove(); // Remove dialog
        submitForm(); // Proceed with the form submission
    });

    // Handle cancel button click
    document.getElementById('cancelUpdate').addEventListener('click', () => {
        dialog.remove(); // Remove dialog
    });
}

// Function to show success message modal
function showSuccessMessage(message, callback) {
    const successModal = document.createElement('div');
    successModal.classList.add('success-message-modal');
    successModal.style.display = 'flex'; // Make modal visible
    successModal.innerHTML = `
        <div class="success-dialog-content">
            <h3>Success</h3>
            <p>${message}</p>
            <button id="successCloseButton" class="confirm-btn">OK</button>
        </div>
    `;
    document.body.appendChild(successModal);

    // Close modal and execute callback
    document.getElementById('successCloseButton').addEventListener('click', () => {
        successModal.remove();
        if (callback) callback(); // Execute callback if provided
    });
}

// Function to show error message modal (optional, not styled like confirmation modal)
function showErrorMessage(message) {
    alert(message); // Fallback to simple alert for error messages
}

// Add loader styles dynamically
const style = document.createElement('style');
style.innerHTML = `
    /* Loader Animation */
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

    /* Confirmation & Success Modal */
    .update-confirmation-dialog, .success-message-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        animation: fadeIn 0.3s ease-in-out;
    }

    /* Modal Content */
    .dialog-content, .success-dialog-content {
        background: linear-gradient(to bottom right, #bde3e8, #f2f2f2);
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        width: 320px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.4s ease-in-out;
    }

    /* Modal Icon */
    .dialog-icon {
        font-size: 40px;
        color: #71d4fc;
        margin-bottom: 15px;
    }

    /* Modal Title */
    .dialog-content h3, .success-dialog-content h3 {
        margin-top: 0;
        color: #333;
        font-size: 1.5rem;
        font-weight: bold;
    }

    /* Modal Buttons */
    .dialog-buttons, .success-dialog-content button {
        margin-top: 20px;
    }

    .confirm-btn {
        background-color: #71d4fc;
        color: #ffffff;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        border-radius: 5px;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .confirm-btn:hover {
        background-color: #5bb0d9;
        transform: scale(1.05);
    }

    .cancel-btn {
        background-color: #ccc;
        color: #333;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        border-radius: 5px;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .cancel-btn:hover {
        background-color: #bbb;
        transform: scale(1.05);
    }

    /* Animations */
    @keyframes fadeIn {
        0% { opacity: 0; }
        100% { opacity: 1; }
    }

    @keyframes slideIn {
        0% { transform: translateY(-50px); opacity: 0; }
        100% { transform: translateY(0); opacity: 1; }
    }
`;
document.head.appendChild(style);


// Logout modal handling
function showLogoutModal() {
    document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}

function handleLogout() {
    alert("You have been logged out."); // Alert user before logging out
    window.location.href = "/brew+flex/logout.php"; // Redirect to logout script
}
