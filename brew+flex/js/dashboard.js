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
