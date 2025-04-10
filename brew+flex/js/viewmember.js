// Show and close the logout modal
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

// Sort table columns
function sortTable(columnIndex) {
    const table = document.querySelector(".member-table");
    const rows = Array.from(table.rows).slice(1); // Exclude header row

    let ascending = true;
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].innerText.trim();
        const bText = b.cells[columnIndex].innerText.trim();
        
        return ascending ? aText.localeCompare(bText) : bText.localeCompare(aText);
    });

    ascending = !ascending;

    // Append sorted rows back to the table
    rows.forEach(row => table.appendChild(row));
}

// Simple pagination for table
const rowsPerPage = 10;
let currentPage = 1;

function displayPage(page) {
    const rows = Array.from(document.querySelectorAll(".member-table tbody tr"));
    rows.forEach((row, index) => {
        row.style.display = (index >= (page - 1) * rowsPerPage && index < page * rowsPerPage) ? '' : 'none';
    });
    currentPage = page;
    updatePagination();
}

function updatePagination() {
    const totalRows = document.querySelectorAll(".member-table tbody tr").length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    const paginationContainer = document.querySelector(".pagination");

    paginationContainer.innerHTML = '';

    for (let i = 1; i <= totalPages; i++) {
        const button = document.createElement("button");
        button.textContent = i;
        button.classList.add(i === currentPage ? 'active' : '');
        button.addEventListener("click", () => displayPage(i));
        paginationContainer.appendChild(button);
    }
}

// Initialize pagination on page load
window.onload = function() {
    displayPage(currentPage);
};
