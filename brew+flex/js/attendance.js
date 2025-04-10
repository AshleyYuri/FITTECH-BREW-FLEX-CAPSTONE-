let scanner;

// Function to open QR Scanner modal
function openQRScanner() {
    document.getElementById('qrScannerModal').style.display = 'flex';
    startScanner();
}
// Function to close QR Scanner modal
function closeQRScanner() {
    document.getElementById('qrScannerModal').style.display = 'none';
    if (scanner) {
        scanner.stop();
    }
}

// Function to start QR Scanner
function startScanner() {
    scanner = new Instascan.Scanner({ video: document.getElementById('interactive') });

    scanner.addListener('scan', function (content) {
        // Process scanned QR Code
        markAttendanceWithQR(content);
        scanner.stop();
        closeQRScanner();
    });

    Instascan.Camera.getCameras()
        .then(function (cameras) {
            if (cameras.length > 0) {
                scanner.start(cameras[0]);
            } else {
                alert('No cameras found.');
            }
        })
        .catch(function (e) {
            alert('Camera access error: ' + e);
            console.error('camera error', e);
        });
}

// Function to show a modal for success or error messages
function showModal(message, type) {
    const existingModal = document.getElementById('messageModal');
    if (existingModal) {
        existingModal.remove();
    }

    const modal = document.createElement('div');
    modal.id = 'messageModal';
    modal.className = 'modal';

    const modalContent = document.createElement('div');
    modalContent.className = 'modal-content';

    const icon = document.createElement('i');
    icon.className = type === 'success' ? 'fas fa-check-circle success-icon' : 'fas fa-times-circle error-icon';

    const messageText = document.createElement('p');
    messageText.textContent = message;
    messageText.className = type === 'success' ? 'success-message' : 'error-message';

    const okButton = document.createElement('button');
    okButton.textContent = 'OK';
    okButton.className = 'modal-ok-btn';
    okButton.onclick = function () {
        if (type === 'success') {
            location.reload();
        } else {
            modal.style.display = 'none';
        }
    };

    modalContent.appendChild(icon);
    modalContent.appendChild(messageText);
    modalContent.appendChild(okButton);
    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    modal.style.display = 'flex';
}

// Function to mark attendance with QR code
function markAttendanceWithQR(content) {
    const successBeepSound = document.getElementById('successBeepSound');
    const thankyouBeepSound = document.getElementById('thankyouBeepSound');
    const errorBeepSound = document.getElementById('errorBeepSound');

    try {
        const qrData = JSON.parse(content);
        const member_id = qrData.member_id;

        if (!member_id) {
            errorBeepSound.play();
            showModal("Invalid QR Code: Member ID missing.", 'error');
            return;
        }

        const formData = new FormData();
        formData.append('member_id', member_id);

        fetch('attendance.php', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    successBeepSound.play();
                    thankyouBeepSound.play();
                    showModal(data.message, 'success');
                } else {
                    errorBeepSound.play();
                    showModal(data.message, 'error');
                }
            })
            .catch(err => {
                errorBeepSound.play();
                showModal("An error occurred while processing the request.", 'error');
                console.error(err);
            });
    } catch (error) {
        errorBeepSound.play();
        showModal("Invalid QR Code format.", 'error');
        console.error(error);
    }
}

// Function to mark attendance
function markAttendance() {
    const memberId = document.getElementById('selectMember').value;

    if (memberId) {
        const formData = new FormData();
        formData.append('member_id', memberId);

        fetch('attendance.php', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                showModal(data.message, data.status === 'success' ? 'success' : 'error');
            })
            .catch(err => {
                showModal("An error occurred while processing the request.", 'error');
                console.error(err);
            });
    } else {
        showModal("Please select a valid member.", 'error');
    }
}


// Function to dynamically add a row to the table
function addRowToTable(record) {
    const table = document.getElementById('attendanceTable');
    const row = table.insertRow();
    row.innerHTML = `
        <td>${record.member_id}</td>
        <td>${record.first_name} ${record.last_name}</td>
        <td>${record.check_in_date}</td>
    `;
}

function openAttendanceModal() {
    document.getElementById('modalAttendanceSearch').style.display = 'flex';
}

function closeAttendanceModal() {
    document.getElementById('modalAttendanceSearch').style.display = 'none';
}

// Highlight search matches
const searchQuery = "<?php echo htmlspecialchars($search); ?>";
if (searchQuery) {
    const rows = document.querySelectorAll('#attendanceTable tr td:nth-child(2)');
    rows.forEach(cell => {
        const originalText = cell.textContent;
        const regex = new RegExp(`(${searchQuery})`, 'gi');
        cell.innerHTML = originalText.replace(regex, '<span class="highlight">$1</span>');
    });
}

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


// Filter attendance table based on search input
document.getElementById('searchInput').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#attendanceTable tr');
    const noResultsRow = document.getElementById('noResultsRow');

    let resultsFound = false;

    rows.forEach(row => {
        if (row.id === 'noResultsRow') return;

        const memberName = row.cells[1].textContent.toLowerCase();

        if (memberName.includes(filter)) {
            row.style.display = "";
            resultsFound = true;
        } else {
            row.style.display = "none";
        }
    });

    if (!resultsFound && filter !== "") {
        if (!noResultsRow) {
            const newRow = document.createElement('tr');
            newRow.id = 'noResultsRow';
            const td = document.createElement('td');
            td.colSpan = 4;
            td.className = 'no-results';
            td.textContent = 'No results found for "' + filter + '"';
            newRow.appendChild(td);
            document.querySelector('#attendanceTable').appendChild(newRow);
        }
    } else {
        if (noResultsRow) {
            noResultsRow.remove();
        }
    }
});

            // Function to apply the selected date filter
            function applyAttendanceDateFilter() {
                const filterValue = document.getElementById('attendance-date-range').value;
                const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
                const noResultsMessage = document.getElementById('attendance-no-results-message');
                const customRange = document.getElementById('attendance-custom-range');
                const dateRangeText = document.getElementById('date-range-text');

                // Show or hide custom date inputs when 'Custom Range' is selected
                if (filterValue === 'custom') {
                    customRange.style.display = 'block';
                } else {
                    customRange.style.display = 'none';
                }

                let filteredRows = [];
                rows.forEach(row => {
                    const dateCell = row.cells[2]; // Check-In Date is in the 3rd column
                    const checkInDate = dateCell ? new Date(dateCell.textContent) : null;
                    let showRow = false;

                    // Filter based on selected range
                    switch (filterValue) {
                        case 'all':
                            showRow = true; // Show all rows
                            break;
                        case 'today':
                            showRow = isToday(checkInDate);
                            break;
                        case 'yesterday':
                            showRow = isYesterday(checkInDate);
                            break;
                        case 'last3days':
                            showRow = isWithinLastNDays(checkInDate, 3);
                            break;
                        case 'lastweek':
                            showRow = isWithinLastNDays(checkInDate, 7);
                            break;
                        case 'lastmonth':
                            showRow = isWithinLastMonth(checkInDate);
                            break;
                    }

                    row.style.display = showRow ? '' : 'none';
                    if (showRow) filteredRows.push(row); // Collect filtered rows
                });

                // Update attendance totals and show no results message if applicable
                updateAttendanceTotals(filteredRows);
                if (filteredRows.length > 0) {
                    noResultsMessage.style.display = 'none';
                } else {
                    noResultsMessage.style.display = 'block';
                    dateRangeText.textContent = getDateRangeText(filterValue); // Update the range text
                }
            }

           // Function to apply custom date filter
           function applyCustomAttendanceDate() {
            const startDate = document.getElementById('attendance-start-date').value;
            const endDate = document.getElementById('attendance-end-date').value;
            const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
            const noResultsMessage = document.getElementById('attendance-no-results-message');

            // Validate custom date range
            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            const start = new Date(startDate);
            const end = new Date(endDate);
            let filteredRows = [];

            rows.forEach(row => {
                const dateCell = row.cells[2]; // Check-In Date column
                const checkInDate = dateCell ? new Date(dateCell.textContent) : null;

                if (checkInDate >= start && checkInDate <= end) {
                    row.style.display = ''; // Show the row if it's within the date range
                    filteredRows.push(row);
                } else {
                    row.style.display = 'none'; // Hide the row if it's outside the range
                }
            });

                // Update attendance totals and display no results message if applicable
                updateAttendanceTotals(filteredRows);
                if (filteredRows.length > 0) {
                    noResultsMessage.style.display = 'none';
                } else {
                    noResultsMessage.style.display = 'block';
                    document.getElementById('date-range-text').textContent = `${formatDate(start)} to ${formatDate(end)}`; // Update custom range text
                }
            }

            // Helper function to get the date range text
            function getDateRangeText(filterValue) {
                switch (filterValue) {
                    case 'today':
                        return 'today';
                    case 'yesterday':
                        return 'yesterday';
                    case 'last3days':
                        return 'the last 3 days';
                    case 'lastweek':
                        return 'last week';
                    case 'lastmonth':
                        return 'last month';
                    case 'custom':
                        return 'the selected custom range';
                    default:
                        return 'the selected date range';
                }
            }

            // Helper function to check if a date is today
            function isToday(date) {
                const today = new Date();
                return date.getDate() === today.getDate() &&
                    date.getMonth() === today.getMonth() &&
                    date.getFullYear() === today.getFullYear();
            }

            // Helper function to check if a date is yesterday
            function isYesterday(date) {
                const yesterday = new Date();
                yesterday.setDate(yesterday.getDate() - 1);
                return date.getDate() === yesterday.getDate() &&
                    date.getMonth() === yesterday.getMonth() &&
                    date.getFullYear() === yesterday.getFullYear();
            }

            // Helper function to check if a date is within the last N days
            function isWithinLastNDays(date, n) {
                const now = new Date();
                now.setDate(now.getDate() - n);
                return date >= now;
            }

            // Helper function to check if a date is within the last month
            function isWithinLastMonth(date) {
                const now = new Date();
                now.setMonth(now.getMonth() - 1);
                return date >= now;
            }

            // Helper function to format date as MM/DD/YYYY
            function formatDate(date) {
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const day = date.getDate().toString().padStart(2, '0');
                const year = date.getFullYear();
                return `${month}/${day}/${year}`;
            }

            // Function to update the total attendance count
            function updateAttendanceTotals(filteredRows) {
                const totalAttendance = filteredRows.length;
                document.getElementById('totalAttendance').textContent = totalAttendance;
            }

            // Function to fetch and view attendance history
            function viewAttendanceHistory(attendanceId) {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'fetch_attendance_history.php?attendance_id=' + attendanceId, true);

                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4) {
                        const attendanceDetails = document.getElementById('attendanceHistoryDetails');

                        if (xhr.status == 200) {
                            // Insert fetched data into the table body
                            attendanceDetails.innerHTML = xhr.responseText.trim();

                            // Handle empty response
                            if (xhr.responseText.trim() === "") {
                                attendanceDetails.innerHTML = "<tr><td colspan='3' style='text-align: center;'>No attendance history available for this record.</td></tr>";
                                document.getElementById('totalAttendance').textContent = "0"; // Set total attendance to 0
                            } else {
                                // Calculate total attendance
                                updateTotalAttendance();
                            }

                            // Display the modal
                            document.getElementById('attendanceHistoryModal').style.display = 'flex';
                        } else {
                            console.error('Error:', xhr.statusText);
                            attendanceDetails.innerHTML = "<tr><td colspan='3' style='text-align: center; color: red;'>An error occurred while fetching attendance data.</td></tr>";
                        }
                    }
                };
                xhr.send();
            }

            // Function to calculate and update total attendance
            function updateTotalAttendance() {
                const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
                const totalAttendance = rows.length; // Count the number of rows

                // Update the total attendance in the UI
                document.getElementById('totalAttendance').textContent = totalAttendance;
            }

            // Function to close Attendance History Modal and reset filters
            function closeAttendanceHistoryModal() {
                document.getElementById('attendanceHistoryModal').style.display = 'none';

                // Reset the filters when the modal is closed
                resetAttendanceFilter();
            }

            // Function to reset the attendance filters
            function resetAttendanceFilter() {
                // 1. Reset the filter dropdown to 'all' (show all rows)
                document.getElementById('attendance-date-range').value = 'all';

                // 2. Hide the custom date range input fields and clear the date inputs
                document.getElementById('attendance-custom-range').style.display = 'none';
                document.getElementById('attendance-start-date').value = '';
                document.getElementById('attendance-end-date').value = '';

                // 3. Get all the rows and show them (reset the filter to show all rows)
                const rows = document.querySelectorAll('#attendanceHistoryDetails tr');
                rows.forEach(row => {
                    row.style.display = ''; // Show all rows
                });

                // 4. Update the attendance totals (without any filters applied)
                updateAttendanceTotals(rows);

                // 5. Hide the "No results found" message if any rows exist
                const noResultsMessage = document.getElementById('attendance-no-results-message');
                if (rows.length > 0) {
                    noResultsMessage.style.display = 'none'; // Hide "No results found" message
                } else {
                    noResultsMessage.style.display = 'block'; // Display "No results found" if no rows
                }

                // 6. Clear the date range text
                document.getElementById('date-range-text').textContent = ''; // Clear the date range message
            }


            // Function to filter members in the dropdown based on search query
            function filterMembers() {
                const searchQuery = document.getElementById("searchMember").value.toLowerCase();
                const memberList = document.querySelectorAll("#selectMember option");

                memberList.forEach(option => {
                    const memberName = option.textContent.toLowerCase();
                    if (memberName.includes(searchQuery)) {
                        option.style.display = "";
                    } else {
                        option.style.display = "none";
                    }
                });
            }

            // Display selected member details
            function showSelectedMember() {
                const selectedMember = document.getElementById('selectMember');
                const memberDetails = document.getElementById('memberDetailsContent');
                const selectedOption = selectedMember.options[selectedMember.selectedIndex];

                if (selectedOption && selectedOption.dataset.name) {
                    memberDetails.textContent = `Name: ${selectedOption.dataset.name} (ID: ${selectedOption.value})`;
                } else {
                    memberDetails.textContent = "";
                }
            }