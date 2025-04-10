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


function toggleDateInputs() {
    const filter = document.getElementById('filter-dropdown').value;
    const dateJoinedFilters = document.getElementById('date-joined-filters');
    const dateExpiryFilters = document.getElementById('date-expiry-filters');
    // Hide all date range inputs by default
    dateJoinedFilters.style.display = 'none';
    dateExpiryFilters.style.display = 'none';
    // Show relevant date range inputs based on selected filter
    if (filter === 'date_joined') {
        dateJoinedFilters.style.display = 'block';
    } else if (filter === 'date_expiry') {
        dateExpiryFilters.style.display = 'block';
    }
}
// Call toggleDateInputs on page load to set the correct state
document.addEventListener('DOMContentLoaded', toggleDateInputs);
// Call toggleDateInputs on page load to set the correct state
document.addEventListener('DOMContentLoaded', toggleDateInputs);
// Search bar login ni chuy!
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-bar input[name="search"]');
    const tableRows = document.querySelectorAll('#member-table-body tr');
    const noResultsRow = document.getElementById('no-results');
    searchInput.addEventListener('input', function() {
        const query = searchInput.value.toLowerCase();
        let hasVisibleRows = false;
        tableRows.forEach(row => {
            const fullNameCell = row.querySelector('td:nth-child(2)'); // Full name column
            const fullName = row.getAttribute('data-fullname') || ''; // Ensure no null values
            if (fullName.includes(query)) {
                row.style.display = ''; // Show the row
                hasVisibleRows = true;

                // Highlight the matched portion
                const regex = new RegExp(`(${query})`, 'gi');
                const originalText = fullNameCell.textContent;
                fullNameCell.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
            } else {
                row.style.display = 'none'; // Hide the row
                fullNameCell.innerHTML = fullNameCell.textContent; // Remove previous highlights
            }
        });

        // Show or hide "No results" row
        if (noResultsRow) {
            noResultsRow.style.display = hasVisibleRows ? 'none' : '';
        }
    });
});
function printTable() {
const tableContainer = document.querySelector('.table-container').innerHTML; // Extract table content
const originalContent = document.body.innerHTML;
document.body.innerHTML = `
<div style="padding: 20px;">
    ${tableContainer}
</div>
`;
window.print();
document.body.innerHTML = originalContent;
window.location.reload();
}
function downloadPDF() {
const { jsPDF } = window.jspdf;
// Initialize jsPDF
const pdf = new jsPDF();
// Table Header and Data
const table = document.querySelector('table'); // Get the table
const rows = Array.from(table.querySelectorAll('tr')).map(row => {
return Array.from(row.querySelectorAll('th, td')).slice(0, -3).map(cell => cell.textContent.trim());
});
// Format table using autoTable plugin
pdf.autoTable({
head: [rows[0]], // Use the first row as the header
body: rows.slice(1), // Remaining rows are the body
theme: 'grid',
styles: {
    fontSize: 10, // Adjust font size for better alignment
    cellPadding: 5, // Add padding for readability
},
});
// Save the PDF
pdf.save('Brew+Flex-MembersData- .pdf');
}