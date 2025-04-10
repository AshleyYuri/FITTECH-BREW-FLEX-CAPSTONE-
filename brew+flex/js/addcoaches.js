document.getElementById('addCoachForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const formData = new FormData(this);
    const addButton = document.querySelector('.next-btn');
    addButton.disabled = true;
    addButton.innerHTML = `Adding... <div class="loader"></div>`; // Show loader animation

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();
        alert(result.message);

        if (result.status === 'success') {
            window.location.href = 'membersuccessfullyadded.php';
        } else {
            addButton.disabled = false;
            addButton.innerHTML = 'Add';
        }
    } catch (error) {
        alert('An error occurred. Please try again.');
        addButton.disabled = false;
        addButton.innerHTML = 'Add';
    }
});

function goToPreviousScreen() {
    window.history.back();
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


function handleLogout() {
    // Close the logout confirmation modal
    closeLogoutModal();

    // Show the custom logout success modal
    document.getElementById('customLogoutSuccessModal').style.display = 'flex';

    // Redirect to the logout page after a short delay
    setTimeout(() => {
        window.location.href = "/brew+flex/logout.php";
    }, 2000); // Adjust delay as needed (2000ms = 2 seconds)
}


